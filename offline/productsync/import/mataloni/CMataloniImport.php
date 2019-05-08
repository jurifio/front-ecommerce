<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 13/07/2015
 * Time: 12:00
 */

namespace bamboo\offline\productsync\import\mataloni;

use bamboo\core\application\AApplication;
use bamboo\core\base\CConfig;
use bamboo\core\db\pandaorm\entities\CEntityManager;
use bamboo\core\exceptions\RedPandaException;
use bamboo\core\jobs\ACronJob;
use bamboo\ecommerce\offline\productsync\import\IProductImporter;

class CMataloniImport extends ACronJob implements  IProductImporter{

    protected $config;
    protected $log;

    protected $skusF;
    protected $skus;
    protected $mainF;
    protected $main;
    protected $shop;
    protected $err = false;

    protected $seenSkus = [];

    /**
     * @param AApplication $app
     * @param null $jobExecution
     */
    public function __construct(AApplication $app, $jobExecution = null)
    {
        parent::__construct($app,$jobExecution);
        $this->app = $app;

        /** @var CEntityManager $em */
        $em = $this->app->entityManagerFactory->create('Shop');
        $obc = $em->findBySql("SELECT id FROM Shop WHERE `name` = ?", array('mataloni'));
        $this->shop = $obc->getFirst();

        $this->config = new CConfig(__DIR__."/import.mataloni.config.json");
        $this->config->load();
    }


    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $this->report("Run", "Import START", "Inizio importazione " . $this->shop->name);

        $this->report("Run", "Fetch Filse", "Carico i files");
        $this->fetchFiles();

        $this->report("Run", "Read Files", "Leggo i files");
        $this->readFiles();

        $this->report("Run", "Read Main", "Leggo il file Main cercando Prodotti");
        $this->readMain();

        $this->report("Run", "Read Sku", "Leggo il file degli Sku");
        $this->readSku();

        $this->report("Run", "Link New Products", "Inizio la ricerca di prodotti Corrispondenti");
        //$this->linkNewProducts(); //serve??

        $this->report("Run", "Find Zero Skus", "Azzero le quantità dei prodotti non elaborati");
        $this->findZeroSkus();

        $this->saveFiles();

        $this->report("Run", "Import END", "Fine importazione " . $this->shop->name);
        echo 'done';
    }

    public function fetchFiles()
    {
        /** PRODUCTS */
        $files = glob($this->app->cfg()->fetch('paths','productSync').'/'.$this->app->getName().'/'.$this->shop->name.'/*.ZIP');
        $products = $files[count($files)-1];
        $size = filesize($products);
        while($size != filesize($products)) {
            sleep(1);
            $size = filesize($products);
        }
        $zip = new \ZipArchive();
        if ($zip->open($products) === TRUE) {
            $zip->extractTo($this->app->cfg()->fetch('paths','productSync').'/'.$this->app->getName().'/'.$this->shop->name.'/import/');
            $zip->close();
            echo 'ok<br>';
        } else {
            echo 'failed';
        }
        $tmp = glob($this->app->cfg()->fetch('paths','productSync').'/'.$this->app->getName().'/'.$this->shop->name.'/import/*.csv');

        $this->main = $this->app->cfg()->fetch('paths','productSync').'/'.$this->app->getName().'/'.$this->shop->name.'/import/main'.rand(0,10000).'.csv';
        $this->skus = $this->app->cfg()->fetch('paths','productSync').'/'.$this->app->getName().'/'.$this->shop->name.'/import/skus'.rand(0,10000).'.csv';
        copy($tmp[0], $this->main);
        rename($tmp[0], $this->skus);

        try{
            for($i=0;$i<(count($files)-1);$i++){
                unlink($files[$i]);
            }
        }catch(\Throwable $e){}
    }

    public function readFiles()
    {
        $this->mainF = fopen($this->main,'r');
        $this->skusF = fopen($this->skus,'r');
    }

    public function readMain()
    {
        //read main
        $main = $this->mainF;
        fgets($main);
        $iterator = 0;
        while (($values = fgetcsv($main,0, $this->config->fetch('miscellaneous','separator'), '|')) !== false ) {
            $iterator++;
            if($values[0][0] == '"'){
                $values[0] = substr($values[0],1);
            }
            $line = implode( $this->config->fetch('miscellaneous' ,'separator'), $values);
            $crc32 = md5($line);
            $exist = $this->app->dbAdapter->selectCount("DirtyProduct",['checksum'=>$crc32,'shopId'=>$this->shop->id]);
            /** Already written */
            if($exist == 1) {
                continue;
            }
            /** Insert */
            if($exist == 0) {
                $one = [];
                /** Count columns */

                if(count($values) != $this->config->fetch('files','main')['columns']) {
                    //ERROR
                    continue;
                }
                if($values[3] != 'Occhiali sole' && $values[21]  != 'sole' ){
                    continue;
                }

                /** Isolate values and find good ones */
                $mapping = $this->config->fetch('mapping','main');
                foreach($mapping as $key=>$val){
                    $one[$key] = trim($values[$val]);
                }
                $one['shopId'] = $this->shop->id;
                $one['text'] = $line;
                $one['price'] = str_replace(',','.', $one['price']);
                $one['value'] = str_replace(',','.', $one['value']);
                $one['checksum'] = $crc32;
                $match=['extId'=>$one['extId'],'shopId'=>$this->shop->id];
                /** RICERCA PER CODICE ESTERNO */
                $res = $this->app->dbAdapter->select('DirtyProduct',$match)->fetchAll();
                if(count($res)==1){
                    $res = $this->app->dbAdapter->update('DirtyProduct', array_diff($one, $match),$match);
                    //log
                    continue;
                }
                $keys = $this->config->fetch('files','main')['extKeys'];
                /** find keys */
                $match = [];
                $match['shopId'] = $this->shop->id;
                foreach($keys as $key){
                    $match[$key] = $one[$key];
                }
                /** find existing product */
                $res = $this->app->dbAdapter->select('DirtyProduct',$match)->fetchAll();
                if(count($res) == 0){
                    if($values[26] == 0) continue;
                    /** è un nuovo prodotto lo scrivo */
                    $one['shopId'] = $this->shop->id;
                    $res = $this->app->dbAdapter->insert('DirtyProduct', $one);
                    if($res < 0 ) {
                        continue;
                    }
                } elseif(count($res) == 1){
                    /** update existing product if changed */
                    //exist.. what to do? uhm... update?
                    $res = $this->app->dbAdapter->update('DirtyProduct', array_diff($one,$match),$match);
                } else {
                    //error
                    //log
                    continue;
                }
            }
        }
    }

    public function readSku()
    {
        //read SKUS ------------------
        $skus = $this->skusF;
        while (($values = fgetcsv($skus,0, $this->config->fetch('miscellaneous','separator') ,'|')) !== false ) {
            try {
                if (count($values) != $this->config->fetch('files', 'skus')['columns']) {
                    //ERROR
                    continue;
                }
                $line = implode( $this->config->fetch('miscellaneous' ,'separator'),$values);
                $crc32 = md5($line);
                $exist = $this->app->dbAdapter->select("DirtySku", ['checksum' => $crc32])->fetchAll();

                /** Already written */
                if (count($exist) == 1) {
                    $this->seenSkus[] = $exist[0]['id'];
                    continue;
                }
                /** Insert or Update */
                if (count($exist) == 0) {
                    $sku = [];
                    /** Count columns */

                    if(count($values) != $this->config->fetch('files','skus')['columns']) {
                        //ERROR
                        continue;
                    }
                    if($values[3] != 'Occhiali sole' && $values[21]  != 'sole' ){
                        continue;
                    }

                    /** Isolate values and find good ones */
                    $mapping = $this->config->fetch('mapping','skus');
                    foreach($mapping as $key=>$val){
                        $sku[$key] = trim($values[$val]);
                    }
                    $sku['shopId'] = $this->shop->id;
                    /** RICERCA PER CODICE ESTERNO */

                    $keys = $this->config->fetch('files', 'skus')['extKeys'];

                    /** find keys */
                    $match = [];
                    $match['shopId'] = $this->shop->id;

                    foreach ($keys as $key) {
                        $match[$key] = $sku[$key];
                    }

                    $dirtyProduct = $this->app->dbAdapter->select('DirtyProduct', $match)->fetchAll();
                    if (count($dirtyProduct) != 1) {
                        //error
                        //log
                        continue;
                    }
                    $dirtyProduct = $dirtyProduct[0];
                    $sku['size'] = 'ta';
                    $sku['text'] = $line;
                    $sku['checksum'] = $crc32;
                    $sku['price'] = str_replace('.','', $sku['price']);
                    $sku['value'] = str_replace('.','', $sku['value']);
                    $sku['price'] = str_replace(',','.', $sku['price']);
                    $sku['value'] = str_replace(',','.', $sku['value']);
                    $testPrice = (float) $sku['price'];
                    $sku['salePrice'] = $testPrice - ($testPrice * 0.30);
                    $res = $this->app->dbAdapter->select('DirtySku', ['dirtyProductId' => $dirtyProduct['id'], 'size' => $sku['size']])->fetchAll();
                    /** Update */
                    if (count($res) == 1) {
                        $sku['changed'] = true;
                        $sku['dirtyProductId'] = $dirtyProduct['id'];
                        $id = $res[0]['id'];
                        $res = $this->app->dbAdapter->update('DirtySku', array_diff($sku, $match), ["id" => $id]);
                        $this->seenSkus[] = $id;
                        //check ok
                        /** Insert New */
                    } else if (count($res) == 0) {
                        if($sku['qty'] == 0) continue;
                        $res = $this->app->dbAdapter->select('DirtyProduct', $match)->fetchAll();
                        if (count($res) == 1) {
                            unset($sku['extId']);
                            unset($sku['var']);
                            $sku['dirtyProductId'] = $res[0]['id'];
                            $sku['shopId'] = $this->shop->id;
                            $sku['changed'] = true;
                            $new = $this->app->dbAdapter->insert('DirtySku', $sku);
                            $this->seenSkus[] = $new;
                        } else {
                            //ERROREEEEE BOOOOOO se è meno di 1 ok, se no c'è qualcosa di strano
                            continue;
                        }
                    } else {
                        //error
                        continue;
                    }
                }
            }catch(\Throwable $e){
                //log error
                continue;
            }
        }
    }

    /**
     *
     */
    public function findZeroSkus()
    {
        if(count($this->seenSkus)  == 0){
            throw new RedPandaException('seenSkus contains 0 elements');
        }
        $res = $this->app->dbAdapter->query("SELECT ds.id
                                      FROM DirtySku ds, DirtyProduct dp, ProductSku ps
                                      WHERE ps.productId = dp.productId AND
                                      ps.productVariantId = dp.productVariantId AND
                                      ds.dirtyProductId = dp.id AND
                                      ps.shopId = ds.shopId AND
                                      ds.productSizeId = ps.productSizeId AND
                                      dp.fullMatch = 1 AND
                                      ds.qty <> 0 AND
                                      ps.shopId = ?", [$this->shop->id])->fetchAll();

        $this->report("findZeroSkus", "Product to set 0: " . count($res), []);
        $i = 0;
        foreach ($res as $one) {
            if (!in_array($one['id'], $this->seenSkus)) {
                $qty = $this->app->dbAdapter->update("DirtySku",["qty"=>0,"changed"=>1],$one);
                $i++;
                //$qty = $this->app->dbAdapter->update("ProductSku",["stockQty"=>0,"padding"=>0],$one);
            }
        }
        $this->report("findZeroSkus", "Product set 0: " . $i, []);
    }

    public function saveFiles()
    {
        fclose($this->skusF);
        fclose($this->mainF);
        $dest = $this->err ? "err" : "done";

        $now = new \DateTime();
        $phar = new \PharData($this->app->cfg()->fetch('paths','productSync').'/'.$this->app->getName().'/'.$this->shop->name.'/import/'.$dest.'/'.$now->format('YmdHis').'.tar');

        try{
            $phar->addFile($this->main);
            unlink($this->main);
            $phar->addFile($this->skus);
            unlink($this->skus);
        } catch (\Throwable $e) {}

        if ($phar->count() > 0) {
            $phar->compress(\Phar::GZ);
        }

        unlink($this->app->cfg()->fetch('paths','productSync').'/'.$this->app->getName().'/'.$this->shop->name.'/import/'.$dest.'/'.$now->format('YmdHis').'.tar');
    }
}
