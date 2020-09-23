<?php

namespace bamboo\ecommerce\offline\productsync\import;

use bamboo\core\exceptions\RedPandaException;

/**
 * Class AEdsTemaImporter
 * @package bamboo\ecommerce\offline\productsync\import
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
abstract class AEdsTemaImporter extends AProductImporter
{
    protected $skus;
    protected $skusF;
    protected $main;
    protected $mainF;
    protected $err = false;

    protected $seenSkus = [];

    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $this->report( "Run", "Import START", "Inizio importazione " . $this->shop->name);

        $this->report( "Run", "Fetch Filse", "Carico i files");
        $this->fetchFiles();
        $this->report( "Run", "Read Files", "Leggo i files");
        $this->readFiles();
        $this->report( "Run", "Read Main", "Leggo il file Main cercando Prodotti");
        $this->readMain();
        $this->report( "Run", "Read Sku", "Leggo il file degli Sku");
        $this->readSku();
        $this->report( "Run", "Find Zero Skus", "Azzero le quantità dei prodotti non elaborati");
        $this->findZeroSkus();
        $this->saveFiles();
        $this->report( "Run", "Import END", "Inizio importazione " . $this->shop->name);

        echo 'done';
    }

    /**
     *
     */
    public function fetchFiles()
    {
        /** PRODUCTS */
        $files = glob($this->app->rootPath().$this->app->cfg()->fetch('paths', 'productSync') . '/cartechini/PRODUCTS_*.CSV');
        $products = $files[count($files) - 1];

        $size = filesize($products);
        while ($size != filesize($products)) {
            sleep(1);
            $size = filesize($products);
        }
        $this->main = $this->app->rootPath().$this->app->cfg()->fetch('paths', 'productSync')  . '/' . $this->shop->name . '/import/main' . rand(0, 1000) . '.csv';
        copy($products, $this->main);
        for ($i = 0; $i < (count($files) - 1); $i++) {
            unlink($files[$i]);
        }
        /** SKUS */
        $files = glob($this->app->rootPath().$this->app->cfg()->fetch('paths', 'productSync') . '/cartechini/SKUS_*.CSV');
        $products = $files[count($files) - 1];
        $size = filesize($products);
        while ($size != filesize($products)) {
            sleep(1);
            $size = filesize($products);
        }
        $this->skus = $this->app->rootPath().$this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/sku' . rand(0, 1000) . '.csv';
        copy($products, $this->skus);
        for ($i = 0; $i < (count($files) - 1); $i++) {
            unlink($files[$i]);
        }
        /** ATTRIBUTI */
        $files = glob($this->app->rootPath().$this->app->cfg()->fetch('paths', 'productSync') . '/cartechini/ATTRIBUTI_*.CSV');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function readFiles()
    {
        $this->mainF = fopen($this->main, 'r');
        $this->skusF = fopen($this->skus, 'r');
    }

    public function readMain()
    {
        //read main
        $main = $this->mainF;
        fgets($main);
        while (($values = fgetcsv($main, 0, $this->config->fetch('miscellaneous', 'separator'), '|')) !== false) {
            if ($values[0][0] == '"') {
                $values[0] = substr($values[0], 1);
            }

            $line = implode($this->config->fetch('miscellaneous', 'separator'), $values);

            $crc32 = md5($line);
            $exist = $this->app->dbAdapter->selectCount("DirtyProduct", ['checksum' => $crc32, 'shopId' => $this->shop->id]);
            /** Already written */
            if ($exist == 1) {
                continue;
            }
            /** Insert */
            if ($exist == 0) {
                $one = [];
                /** Count columns */
                if (count($values) != $this->config->fetch('files', 'main')['columns']) {
                    //ERROR
                    continue;
                }
                /** Isolate values and find good ones */
                $mapping = $this->config->fetch('mapping', 'main');
                foreach ($mapping as $key => $val) {
                    $one[$key] = trim($values[$val]);
                }
                $one['text'] = $line;
                $one['checksum'] = $crc32;

                $keys = $this->config->fetch('files', 'main')['extKeys'];

                /** find keys */
                $match = [];
                $match['shopId'] = $this->shop->id;
                foreach ($keys as $key) {
                    $match[$key] = $one[$key];
                }

                /** find existing product */
                $res = $this->app->dbAdapter->select('DirtyProduct', $match)->fetchAll();
                if (count($res) == 0) {
                    /** è un nuovo prodotto lo scrivo */
                    $one['shopId'] = $this->shop->id;
                    $one['dirtyStatus'] = 'E';
                    $res = $this->app->dbAdapter->insert('DirtyProduct', $one);
                    if ($res < 0) {
                        continue;
                    }
                } elseif (count($res) == 1) {
                    /** update existing product if changed */
                    //exist.. what to do? uhm... update?
                    $res = $this->app->dbAdapter->update('DirtyProduct', array_diff($one, $match), $match);
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
        $shopOk = 0;
        $shopKo = 0;
        while (($values = fgetcsv($skus, 0, $this->config->fetch('miscellaneous', 'separator'), '|')) !== false) {
            try{
                if ($values[0][0] == '"') {
                    $values[0] = substr($values[0], 1);
                }

                if (count($values) != $this->config->fetch('files', 'skus')['columns']) {
                    $this->warning('Columns Count',count($values).' columns find, expecting '.$this->config->fetch('files', 'skus')['columns'],$values);
                    continue;
                }
                $line = implode($this->config->fetch('miscellaneous', 'separator'), $values);
                $crc32 = md5($line);
                $exist = $this->app->dbAdapter->select("DirtySku", ['checksum' => $crc32, 'shopId'=>$this->shop->id])->fetchAll();

                /** Already written */
                if (count($exist) == 1) {
                    $this->seenSkus[] = $exist[0]['id'];
                    continue;
                }
                /** Insert or Update */
                if (count($exist) == 0) {
                    $mapping = $this->config->fetch('mapping', 'skus');
                    $sku = [];

                    foreach ($mapping as $key => $val) {
                        $sku[$key] = trim($values[$val]);
                    }
                    if (!in_array(($sku['shopId']), $this->config->fetch('miscellaneous', 'thisShopExtIds'))) {
                        $shopKo++;
                        continue;
                    } else {
                        $shopOk++;
                    }
                    $sku['shopId'] = $this->shop->id;
                    $keys = $this->config->fetch('files', 'skus')['extKeys'];

                    /** find keys */
                    $match = [];
                    $match['shopId'] = $this->shop->id;

                    foreach ($keys as $key) {
                        $match[$key] = $sku[$key];
                    }

                    $sku['price'] = str_replace('.', '', $sku['price']);
                    $sku['salePrice'] = str_replace('.', '', $sku['salePrice']);
                    $sku['value'] = str_replace('.', '', $sku['value']);
                    $sku['price'] = str_replace(',', '.', $sku['price']);
                    $sku['salePrice'] = str_replace(',', '.', $sku['salePrice']);
                    $sku['value'] = str_replace(',', '.', $sku['value']);
                    $sku['storeHouseId'] =str_replace('0', '',  $sku['storeHouseId']);
                    $dirtyProduct = $this->app->dbAdapter->select('DirtyProduct', $match)->fetchAll();
                    if (count($dirtyProduct) != 1) {
                        //error
                        //log
                        continue;
                    }
                    $dirtyProduct = $dirtyProduct[0];
                    $sku['text'] = $line;
                    $sku['checksum'] = $crc32;
                    $res = $this->app->dbAdapter->select('DirtySku', ['dirtyProductId' => $dirtyProduct['id'], 'size' => $sku['size']])->fetchAll();
                    /** Update */
                    if (count($res) == 1) {
                        $sku['changed'] = 1;
                        $id = $res[0]['id'];
                        $sku['dirtyProductId'] = $dirtyProduct['id'];
                        $res = $this->app->dbAdapter->update('DirtySku', array_diff($sku, $match), ["id" => $id]);
                        $this->seenSkus[] = $id;
                        //check ok
                        /** Insert New */
                    } else if (count($res) == 0) {
                        $res = $this->app->dbAdapter->select('DirtyProduct', $match)->fetchAll();
                        if (count($res) == 1) {
                            unset($sku['extId']);
                            unset($sku['var']);
                            $sku['dirtyProductId'] = $res[0]['id'];
                            $sku['shopId'] = $this->shop->id;
                            $sku['changed'] = 1;
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
            } catch(\Throwable $e){
                $this->error( 'Read Sku', 'Error while reading Sku', $e);
                if(isset($sku)){
                    $this->error( 'Read Sku', 'SkuError', $sku);
                }
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
                                      WHERE
	                                      ps.productId = dp.productId AND
	                                      ps.productVariantId = dp.productVariantId AND
	                                      ds.dirtyProductId = dp.id AND
	                                      ps.shopId = ds.shopId AND
	                                      ds.productSizeId = ps.productSizeId AND
	                                      dp.fullMatch = 1 AND
	                                      ds.qty != 0 AND
	                                      ps.shopId = ?", [$this->shop->id])->fetchAll();

        $this->report( "findZeroSkus", "Product to set 0: " . count($res), []);
	    $this->report( "findZeroSkus", "Product not at 0: " . count($res), []);
	    $i = 0;

        foreach ($res as $one) {
            if (!in_array($one['id'], $this->seenSkus)) {
                $qty = $this->app->dbAdapter->update("DirtySku",["qty"=>0,"changed"=>1,"checksum"=>null],$one);
                //$qty = $this->app->dbAdapter->update("ProductSku",["stockQty"=>0,"padding"=>0],$one);
            }
        }
        $this->report( "findZeroSkus", "Product set 0: " . $i, []);
    }

    public function saveFiles()
    {
        fclose($this->skusF);
        fclose($this->mainF);
        $dest = $this->err ? "err" : "done";

        $now = new \DateTime();
        $phar = new \PharData($this->app->rootPath().$this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/' . $dest . '/' . $now->format('YmdHis') . '.tar');


        $phar->addFile($this->main);
        $phar->addFile($this->skus);

        if ($phar->count() > 0) {
            $phar->compress(\Phar::GZ);
        }

        unlink($this->main);
        unlink($this->skus);
        unlink($this->app->rootPath().$this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/' . $dest . '/' . $now->format('YmdHis') . '.tar');
    }
}
