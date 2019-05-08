<?php
namespace bamboo\offline\productsync\import\dellamartira;

use bamboo\core\application\AApplication;
use bamboo\core\base\CConfig;
use bamboo\core\base\CFTPClient;
use bamboo\core\db\pandaorm\entities\CEntityManager;
use bamboo\core\exceptions\RedPandaException;
use bamboo\core\exceptions\RedPandaFTPClientException;
use bamboo\core\jobs\ACronJob;
use bamboo\core\utils\amazonPhotoManager\ImageEditor;
use bamboo\domain\entities\CJobExecution;

/**
 * Class CDellaMartiraImport
 * @package bamboo\import\productsync\dellamartira
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 28/05/2015
 * @since 1.0
 */
class CDellaMartiraImport extends ACronJob {

    protected $config;
    protected $log;

    protected $skusF;
    protected $skus;
    protected $mainF;
    protected $main;
    protected $shop;
    protected $err = false;

    protected $mainRows = 0;
    protected $skusRows = 0;

    protected $seenSkus;

    /**
     * @param AApplication $app
     * @param null|CJobExecution $jobExecution
     */
    public function __construct(AApplication $app, $jobExecution)
    {
        parent::__construct($app,$jobExecution);

        /** @var CEntityManager $em */
        $em = $this->app->entityManagerFactory->create('Shop');
        $obc = $em->findBySql("SELECT id FROM Shop WHERE `name` = ?", array('dellamartira'));
        $this->shop = $obc->getFirst();

        $this->config = new CConfig(__DIR__."/import.dellamartira.config.json");
        $this->config->load();
    }

    /**
     * @param null $args
     * @return bool
     * @throws RedPandaFTPClientException
     */
    public function run($args = null)
    {

        try {
            $this->report( "Import Started", "Inizio importazione Della Martira");

            $this->report( "Fetch Files", "Carico i files");
            if($args == null){
                $fetch = $this->fetchFiles();
            } else {
                $fetch = $this->fetchAltFiles($args);
            }

            if($fetch){
                $this->report( "Read Files", "Leggo i files");
                $this->readFiles();

                $this->report( "Read Main", "Leggo il file Main cercando Prodotti");
                $this->readMain();

                $this->report( "Read Sku", "Leggo il file degli Sku");
                $this->readSku();

                $this->report( "Find Zero Skus", "Finding zero skus");
                $this->findZeroSkus();

                $this->report( "Runner", "File import things done");

                $this->report( "Save Files", "salvo i file e li cancello");
                $this->saveFiles();
            } else $this->warning( "Read Files", "File not Found");
        } catch(\Throwable $e){
            $this->error( "Runner Product import", "Failed in working Products/skus",$e);
        }

	    $args = null;
        try{
            if($args !== null) return true;
            $this->report("Runner", "Start photo things");

            $this->report("Fetch Photos", "Download delle foto da remoto");
            sleep(1);
            $this->downloadPhotos();

            $this->report("Transfer Photos", "Upload foto sul server di destinazione");
            $this->sendPhotosToWork();

            $this->report("Runner", "Della Martira finished");
        }catch (\Throwable $e){
            $this->error( "Runner Photo Fetch", "error fetching photos",$e);
        }

        echo 'done';
        return true;
    }

    /**
     * @return bool
     *
     */
    public function fetchFiles()
    {
        /** PRODUCTS */
        $files = glob($this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->config->fetch('files','main')['location'].'/*.CSV');
        if(!isset($files[0])) return false;
        $products = $files[0];
        $time = filemtime($products);
        foreach($files as $file) {
            if(filemtime($file)>$time) {
                $products = $file;
                $time = filemtime($file);
            }
        }
        $this->report( "fetchFiles", "Files usato = ".$products,null);
        $size = filesize($products);
        while($size != filesize($products)) {
            sleep(1);
            $size = filesize($products);
        }
        $f = fopen($products,'r');
        $lines=0;
        while(fgets($f)!= false){
            $lines++;
        }
        fclose($f);
        $this->report('fetchFiles','Lines Counted = '.$lines);
        $this->skusRows = $lines;
        $this->mainRows = $lines;

        $this->main = $this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->shop->name.'/import/main'.rand(0,1000).'.csv';
        $this->skus = $this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->shop->name.'/import/skus'.rand(0,1000).'.csv';

        copy($products, $this->main);
        copy($products, $this->skus);

        foreach($files as $file){
            try{
                unlink($file);
            }catch (\Throwable $e){
                $this->warning('FetchFile', 'Could not unlink file '.$file,$e);
            }
        }
        return true;
    }

    /**
     * @param $args
     * @return bool
     */
    public function fetchAltFiles($args){
        $args = explode(',',$args);
        $this->main = $this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->shop->name.'/import/'.$args[0];
        $this->skus = $this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->shop->name.'/import/'.$args[1];
        return true;
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
        $iterator = 0;
        $newLines = 0;
        while (($values = fgetcsv($main,0, $this->config->fetch('miscellaneous','separator'), '"')) !== false ) {
            try {
                if (count($values) != $this->config->fetch('files', 'main')['columns']) continue;
                $mainMapping = $this->config->fetch('mapping', 'main');
                $mainValues = [];
                foreach ($mainMapping as $key => $val) {
                    $mainValues[$key] = trim($values[$val]);
                }
                $line = implode($this->config->fetch('miscellaneous', 'separator'), $mainValues);
                $md5 = md5($line);
                $mainValues['checksum'] = $md5;
                $exist = $this->app->dbAdapter->selectCount("DirtyProduct", ['checksum' => $md5, 'shopId' => $this->shop->id]);

                /** Already written */
                if ($exist == 1) {
                    continue;
                }
                /** Insert/update */
                if ($exist == 0) {
                    $newLines++;
                    /** RICERCA PER VALORI CHIAVE ESTERNO */
                    $identify['brand'] = $mainValues['brand'];
                    $identify['itemno'] = $mainValues['itemno'];
                    $identify['var'] = $mainValues['var'];
                    $identify['shopId'] = $this->shop->id;

                    $mainValues['price'] = (float)str_replace(',', '.', $mainValues['price']);
                    $mainValues['value'] = (float)str_replace(',', '.', $mainValues['value']);
                    $mainValues['salePrice'] = (float)str_replace(',', '.', $mainValues['salePrice']);

                    $res = $this->app->dbAdapter->select('DirtyProduct', $identify)->fetchAll();
                    /** find if exist the same product and update entities */
                    if (count($res) == 1) {
                        $update = $mainValues;
                        $update['text'] = implode(',', $values);
                        if (!empty($mainValues['photos']) && (empty($res['clipboard']) || (isset($res['clipboard']) && $res['clipboard'] != 'cleared'))) {
                            $update['clipboard'] = $mainValues['photos'];
                        } else {
	                        if(isset($update['clipboard'])) unset($update['clipboard']);
                        }
                        unset($update['photos']);
                        unset($update['details']);
                        $res = $this->app->dbAdapter->update('DirtyProduct', array_diff($update, $identify), $identify);
                        //log
                        continue;
                    } elseif (count($res) == 0) {
                        /** è un nuovo prodotto lo scrivo */
                        $insert = $mainValues;
                        $insert['text'] = implode(',', $values);
                        if (!empty($mainValues['photos'])) {
                            $insert['clipboard'] = $mainValues['photos'];
                        }
                        unset($insert['photos']);
                        unset($insert['details']);
                        $insert['shopId'] = $this->shop->id;
                        $insert['dirtyStatus'] = 'E';
                        $res = $this->app->dbAdapter->insert('DirtyProduct', $insert);
                        if ($res < 0) {
                            continue;
                        }
                    } else {
                        //error
                        //log
                        continue;
                    }
                }
            }catch (\Throwable $e){
                $this->error( 'Error reading Main','read Context',$e);
            }
        }
        $this->report( 'Read Main done', 'read line: '.$newLines,null);
        return $iterator;
    }

    public function readSku()
    {
        //read SKUS ------------------
        $skus = $this->skusF;
        $iterator = 0;
        $changedSku = 0;
        while (($values = fgetcsv($skus,0, $this->config->fetch('miscellaneous','separator') ,'"')) !== false ) {

            try {
                if (count($values) != $this->config->fetch('files', 'skus')['columns']) {
                    //ERROR
                    continue;
                }
                $sku = [];
                /** Isolate values and find good ones */
                $mapping = $this->config->fetch('mapping','skus');
                foreach($mapping as $key=>$val){
                    $sku[$key] = trim($values[$val]);
                }

                $line = implode( $this->config->fetch('miscellaneous' ,'separator'),$sku);
                $md5 = md5($line);
                $exist = $this->app->dbAdapter->select("DirtySku", ['checksum' => $md5])->fetchAll();

                /** Already written */
                if (is_array($exist) && count($exist) === 1) {
                    $this->seenSkus[] = $exist[0]['id'];
                    continue;
                } elseif (is_array($exist) && count($exist) === 0) {
                    /** Insert or Update */
                    $changedSku++;

                    $sku['shopId'] = $this->shop->id;
                    /** RICERCA PER CODICE ESTERNO */

                    $keys = $this->config->fetch('files', 'main')['extKeys'];
                    /** find keys */
                    $matchProduct = [];
                    foreach ($keys as $key) {
                        $matchProduct[$key] = $sku[$key];
                    }
                    $matchProduct['shopId'] = $this->shop->id;

                    /** Find Product  */
                    $dirtyProduct = $this->app->dbAdapter->select('DirtyProduct', $matchProduct)->fetchAll();
                    if (is_array($dirtyProduct) && count($dirtyProduct) !== 1) {
                        //error - PRODUCT not found? are u kidding me? it's the same file!
                        $this->error( 'Reading Skus', 'Dirty Product not found while looking at sku', $values);
                        continue;
                    }
                    $dirtyProduct = $dirtyProduct[0];
                    $sku['text'] = $line;
                    $sku['checksum'] = $md5;
                    /** Adjust prices */
                    $sku['price'] = str_replace(',','.', $sku['price']);
                    $sku['value'] = str_replace(',','.', $sku['value']);
                    $sku['salePrice'] = str_replace(',','.', $sku['salePrice']);
                    $res = $this->app->dbAdapter->select('DirtySku', ['dirtyProductId' => $dirtyProduct['id'], 'size' => $sku['size']])->fetchAll();
                    /** Update */
                    if (is_array($res) && count($res) == 1) {
                        $sku['changed'] = true;
                        $id = $res[0]['id'];
                        $res = $this->app->dbAdapter->update('DirtySku', array_diff($sku, $matchProduct), ["id" =>$id ]);
                        $this->seenSkus[] = $id;
                        //check ok
                        /** Insert New */
                    } else if (is_array($res) && count($res) == 0) {
                        if($sku['qty'] == 0) continue;
                        unset($sku['brand']);
                        unset($sku['itemno']);
                        unset($sku['var']);
                        $sku['dirtyProductId'] = $dirtyProduct['id'];
                        $sku['shopId'] = $this->shop->id;
                        $sku['changed'] = true;
                        $new = $this->app->dbAdapter->insert('DirtySku', $sku);
                        $this->seenSkus[] = $new;
                    } else {
                        $this->error( 'Reading Skus', 'Found more than one DirtySku', $values);
                        continue;
                    }
                }
            }catch(\Throwable $e){
                $this->error('Error Reading Skus','Skus reading error', $e);
                continue;
            }
        }
        $this->report( 'Read Skus done', 'read line: '.$changedSku,null);
        return $iterator;
    }

    /**
     *
     */
    public function findZeroSkus()
    {
        if(count($this->seenSkus)  == 0){
            throw new RedPandaException('seenSkus contains 0 elements');
        }
        $res = $this->app->dbAdapter->query("SELECT distinct ds.id
                                      FROM DirtySku ds, DirtyProduct dp, ProductSku ps
                                      WHERE
                                          ds.dirtyProductId = dp.id AND
                                          ps.productId = dp.productId AND
                                          ps.productVariantId = dp.productVariantId AND
                                          ps.shopId = ds.shopId AND
                                          ps.productSizeId = ds.productSizeId AND
                                          dp.fullMatch = 1 AND
                                          ds.qty != 0 AND
                                          ps.shopId = ?", [$this->shop->id])->fetchAll();

        $this->report( "findZeroSkus", "Seen Skus: " . count($this->seenSkus), []);
        $this->report( "findZeroSkus", "Product not at 0: " . count($res), []);
        $i = 0;

        if($this->seenSkus < ($this->skusRows*0.99) ) throw new RedPandaException('troppa differenza tra skus letti e registrati');
        foreach ($res as $one) {
            if (!in_array($one['id'], $this->seenSkus)) {
                $qty = $this->app->dbAdapter->update("DirtySku",["qty"=>0,"changed"=>1,"checksum"=>null],$one);
                $i+= $qty;
                //$qty = $this->app->dbAdapter->update("ProductSku",["stockQty"=>0,"padding"=>0],$one);
            }
        }
        $this->report( "findZeroSkus", "Product set 0: " . $i, []);
    }

    public static function getDummyPic($dirtyProductId, AApplication $app)
    {
        $config = new CConfig(__DIR__."/import.dellamartira.config.json");
        $config->load();
        $ftp = new CFTPClient($app,$config->fetch('miscellaneous','photoFTPClient'));
        $ieditor = new ImageEditor();
        $dummyFolder = $app->rootPath().$app->cfg()->fetch('paths','dummyFolder').'/';

        $photoList = $ftp->nList();
        $photoListSearch = array_map('strtolower', $photoList);
        $product = $app->dbAdapter->query("SELECT * FROM DirtyProduct WHERE id = ?",[$dirtyProductId])->fetchAll()[0];
        $photo = explode('|',$product['clipboard'])[0];
        $fileName = pathinfo($photo);

        if(($id = array_search(str_replace(' ','_',strtolower('./'.$photo)), $photoListSearch))=== false) {
            return false;
        }

        $name = $product['productId'].'-'.$product['productVariantId'].'__'.$fileName['filename'].'.'.$fileName['extension'];
        if (($got = $ftp->get(__DIR__.'/temp/photos/'.$name,$photoList[$id]))) {
            $ieditor->load(__DIR__.'/temp/photos/'.$name);
            $ieditor->resizeToWidth(500);
            $dummyName = rand(0,9999999999).'.'.$fileName['extension'];
            $ieditor->save($dummyFolder.'/'.$dummyName);
            return $dummyFolder.$dummyName;
        }

        return false;
    }

    /**
     *
     */
    public function downloadPhotos()
    {
        $widht = 500;
        $products = $this->app->dbAdapter->query("SELECT *
													FROM DirtyProduct
													WHERE productId IS NOT NULL AND
														productVariantId IS NOT NULL AND
														shopId = ? AND
														clipboard IS NOT NULL AND
														clipboard <> 'cleared' ",[$this->shop->id])->fetchAll();
        if(count($products) == 0) {
            //log nothing to do
            return;
        }
        $dummyFolder = $this->app->rootPath().$this->app->cfg()->fetch('paths','dummyFolder').'/';
        $this->app->vendorLibraries->load("amazon2723");
        $imager = new ImageEditor();
        $this->report('PhotoDownload','Downloading photo for '.count($products).' products',null);
        $ftpSource = new CFTPClient($this->app,$this->config->fetch('miscellaneous','photoFTPClient'));
        $done = 0;
        $counter = 0;
	    $photoList = $ftpSource->nList();
	    $photoListSearch = array_map('strtolower', $photoList);

        foreach($products as $dp) {
            if(strpos($dp['clipboard'], '|')){
                $photos = explode('|',$dp['clipboard']);
            } else {
                $photos= [];
            }
            $first = true;

            foreach ($photos as $key => $photo) {
                try {
                    if ($counter % 50 == 0) {
                        $this->report('PhotoDownload','Got '.$counter.' photos till now',null);
                    }
                    $fileName = pathinfo($photo);
	                if(($id = array_search(str_replace(' ','_',strtolower('./'.$photo)), $photoListSearch))=== false) continue;
                    $name = $dp['productId'].'-'.$dp['productVariantId'].'__'.$fileName['filename'].'.'.$fileName['extension'];
                    $counter++;
                    if (($got = $ftpSource->get(__DIR__.'/temp/photos/'.$name,$photoList[$id]))) {
                        if ($first){
                            $imager->load(__DIR__.'/temp/photos/'.$name);
                            $imager->resizeToWidth($widht);
                            $dummyName = rand(0,9999999999).'.'.$fileName['extension'];
                            $imager->save($dummyFolder.'/'.$dummyName);
                            $this->app->dbAdapter->update('Product',['dummyPicture'=>$dummyName],['id'=>$dp['productId'],'productVariantId'=>$dp['productVariantId']]);
                            $this->report( 'PhotoDownload', 'Set dummyPicture: '.$dummyName);
                            $first = false;
                        }
                        unset($photos[$key]);
                    }
                } catch(\Throwable $e){
                    $this->error('PhotoDownload','error while working photo '.$photo , $e);
                }
            }
            if(count($photos) == 0) {
                $done++;
                $left = 'cleared';
            } else {
                $left = implode('|',$photos);
            }
            $this->app->dbAdapter->update('DirtyProduct',['clipboard'=>$left],['id'=>$dp['id']]);
        }
        $this->report('PhotoDownload','Dowloaded photo for '.$done.' products',null);
    }

    /**
     *
     */
    public function sendPhotosToWork()
    {
        sleep(1);
        $ftpDestination = new CFTPClient($this->app, $this->config->fetch('miscellaneous', 'destFTPClient'));
        if(!$ftpDestination->changeDir('/shootImport/incoming/DellaMartira')){
            throw new RedPandaFTPClientException('Could not change dir');
        };
        $photos = glob($this->app->rootPath().$this->app->cfg()->fetch('paths','image-temp-folder').'/*');
        $got = 0;
        $this->report('PhotoUpload','Photos to send: '.count($photos).'',null);
        foreach($photos as $photo){
            if($got % 100 == 0) {
                $this->report('PhotoUpload','Got '.$got.' photos till now',null);
            }
            try{
                if($ftpDestination->put($photo,basename($photo))){
                    $got++;
                    unlink($photo);
                }
            }catch (\Throwable $e){ $this->warning('PhotoUpload','Error while putting/deleting photo',$e); }
        }
        $this->report('PhotoUpload','Photos sent: '.$got.'',null);
    }

    public function saveFiles()
    {
        $dest = $this->err ? "err" : "done";

        fclose($this->mainF);
        fclose($this->skusF);

        $now = new \DateTime();
        $phar = new \PharData($this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->shop->name.'/import/'.$dest.'/'.$now->format('YmdHis').'.tar');

        $phar->addFile($this->main);
        $phar->addFile($this->skus);
        if ($phar->count() > 0) {
            $phar->compress(\Phar::GZ);
        }

        unlink($this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->shop->name.'/import/'.$dest.'/'.$now->format('YmdHis').'.tar');
        unlink($this->main);
        unlink($this->skus);
    }
}