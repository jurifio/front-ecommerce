<?php

namespace bamboo\ecommerce\offline\productsync\import;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\RedPandaException;

/**
 * Class ABSoftImporter
 * @package bamboo\ecommerce\offline\productsync\import
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 20/06/2020
 * @since 1.0
 */
abstract class  ABSoftImporter extends AProductImporter
{
    protected $skus;
    protected $skusF;
    protected $progressive;
    protected $progressiveF;
    protected $main;
    protected $mainF;
    protected $err = false;

    protected $seenSkus = [];

    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $this->report("Run","Import START","Inizio importazione  The Square Roma" );

        $this->report("Run","Fetch Files","Carico i files");
        $this->fetchFiles();
        $this->report("Run","Read Files","Leggo i files");
        $this->readFiles();
        $this->report("Run","Read Main","Leggo il file Main cercando Prodotti");
        $this->readMain();
        $this->report("Run","Read Sku","Leggo il file degli Sku");
        $this->readProgressive();
        $this->report("Run","Find progressive Skus","Trova i progressvi e gli sku");
        $this->readSku();
        $this->report("Run","Find Zero Skus","Azzero le quantità dei prodotti non elaborati");
        $this->findZeroSkus();
       $this->saveFiles();
       $this->report("Run","Import END","Inizio importazione the Square Roma Pdio "  );

        echo 'done';
    }

    /**
     *
     */
    public function fetchFiles()
    {
        /** PRODUCTS */
        $files = glob($this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/articoli.txt');
        $products = $files[count($files) - 1];
        if(file_exists($this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/articoli.txt')){
        $this->report("AbsoftImporter","Read Main","file Trovato");
    }else{
        $this->report("AbsoftImporter","Read Main","file Non Trovato");
    }

        $size = filesize($products);
        while ($size != filesize($products)) {
            sleep(1);
            $size = filesize($products);
        }
        $this->main = $this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/import/main' . rand(0,1000) . '.csv';
        copy($products,$this->main);
        for ($i = 0; $i < (count($files) - 1); $i++) {
            unlink($files[$i]);
        }


        /** TAGLIE */
        $files = glob($this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/taglie.txt');
        $products = $files[count($files) - 1];
        $size = filesize($products);
        while ($size != filesize($products)) {
            sleep(1);
            $size = filesize($products);
        }
        $this->skus = $this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/import/skus' . rand(0,1000) . '.csv';
        copy($products,$this->skus);
        for ($i = 0; $i < (count($files) - 1); $i++) {
            unlink($files[$i]);
        }
        /** PROGRESSIVI */
        $files = glob($this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/progressivi.txt');
        $products = $files[count($files) - 1];
        $size = filesize($products);
        while ($size != filesize($products)) {
            sleep(1);
            $size = filesize($products);
        }
        $this->progressive = $this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/import/progressive' . rand(0,1000) . '.csv';
        copy($products,$this->progressive);
        for ($i = 0; $i < (count($files) - 1); $i++) {
            unlink($files[$i]);
        }
    }

    public function readFiles()
    {
        $this->mainF = fopen($this->main,'r');
        $this->skusF = fopen($this->skus,'r');
        $this->progressiveF = fopen($this->progressive,'r');
    }

    public function readMain()
    {
        //read main
        $main = $this->mainF;
        fgets($main);

        $i = 0;
        try {
            while (($values = fgetcsv($main,0,$this->config->fetch('miscellaneous','separator'),'|')) !== false) {
                $dirtyProduct = [];
                $dirtyProductExtended = [];

                $line = implode($this->config->fetch('miscellaneous','separator'),$values);
                $dirtyProduct['brand'] = $values[12];
                $dirtyProduct['var'] = $values[20];
                $dirtyProduct['itemno'] = $values[0];

                $dirtyProduct['extId'] = $values[19];
                $dirtyProduct['value'] = str_replace(',','.',$values[29]);
                $dirtyProduct['price'] = str_replace(',','.',$values[30]);
                $dirtyProduct['salePrice'] = str_replace(',','.',$values[31]);
                $dirtyProduct['text']=$line;
                $dirtyProductExtended['season'] = $values[6];
                $dirtyProductExtended['name'] = $values[1];
                $dirtyProductExtended['description'] = $values[2];
                $dirtyProductExtended['colorDescription'] = $values[22];
                $dirtyProductExtended['generalColor'] = $values[22];
                $dirtyProductExtended['audience'] = $values[18];
                $dirtyProductExtended['cat1'] = $values[28];
                $dirtyProductExtended['cat2'] = $values[16];
                $dirtyProductExtended['cat3'] = $values[14];
                $dirtyProductExtended['cat4'] = $values[26];
                $dirtyProductExtended['shopId'] = 60;

                $crc32 = md5($line);

                /** Insert */

                    $dirtyProduct['text'] = $line;
                    $dirtyProduct['checksum'] = $crc32;

                    $keys = $this->config->fetch('files','main')['extKeys'];


                    /** find existing product */
                    $res = $this->app->dbAdapter->select('DirtyProduct',['checksum' => $crc32,'extId' => $dirtyProduct['extId'],'shopId' => 60,'var' => $dirtyProduct['var']])->fetchAll();
                    if (count($res) == 0) {
                        /** è un nuovo prodotto lo scrivo */
                        $dirtyProduct['shopId'] = 60;
                        $dirtyProduct['dirtyStatus'] = 'E';
                        $dirtyProductInsert = \Monkey::app()->repoFactory->create('DirtyProduct')->getEmptyEntity();
                        $dirtyProductInsert->shopId = 60;
                        $dirtyProductInsert->itemno = $dirtyProduct['itemno'];
                        $dirtyProductInsert->brand = $dirtyProduct['brand'];
                        $dirtyProductInsert->var = $dirtyProduct['var'];
                        $dirtyProductInsert->value = $dirtyProduct['value'];
                        $dirtyProductInsert->text = $dirtyProduct['text'];
                        $dirtyProductInsert->price = $dirtyProduct['price'];
                        $dirtyProductInsert->salePrice = $dirtyProduct['salePrice'];
                        $dirtyProductInsert->checksum = $dirtyProduct['checksum'];
                        $dirtyProductInsert->extId = $dirtyProduct['extId'];
                        $dirtyProductInsert->insert();

                        $lastId = $this->app->dbAdapter->query('SELECT max(`id`) as dirtyProductId FROM `DirtyProduct`',[])->fetchAll()[0]['dirtyProductId'];
                        $dirtyProductExtended['dirtyProductId'] = $lastId;
                        $dirtyProductExtendInsert = \Monkey::app()->repoFactory->create('DirtyProductExtend')->getEmptyEntity();
                        $dirtyProductExtendInsert->shopId = 60;
                        $dirtyProductExtendInsert->name = $dirtyProductExtended['name'];
                        $dirtyProductExtendInsert->description = $dirtyProductExtended['description'];
                        $dirtyProductExtendInsert->season = $dirtyProductExtended['season'];
                        $dirtyProductExtendInsert->audience = $dirtyProductExtended['audience'];
                        $dirtyProductExtendInsert->cat1 = $dirtyProductExtended['cat1'];
                        $dirtyProductExtendInsert->cat2 = $dirtyProductExtended['cat2'];
                        $dirtyProductExtendInsert->cat3 = $dirtyProductExtended['cat3'];
                        $dirtyProductExtendInsert->cat4 = $dirtyProductExtended['cat4'];
                        $dirtyProductExtendInsert->insert();


                    } elseif (count($res) == 1) {
                        /** update existing product if changed */
                        //exist.. what to do? uhm... update?

                        $dirtyProductUpdate = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['extId' => $dirtyProduct['extId'],'var' => $dirtyProduct['var'],'shopId' => 60]);
                        $dirtyProductId = $dirtyProductUpdate->id;
                        $dirtyProductUpdate->itemno = $dirtyProduct['itemno'];
                        $dirtyProductUpdate->brand = $dirtyProduct['brand'];
                        $dirtyProductUpdate->price = $dirtyProduct['price'];
                        $dirtyProductUpdate->value = $dirtyProduct['value'];
                        $dirtyProductUpdate->salePrice = $dirtyProduct['salePrice'];
                        $dirtyProductUpdate->update();
                        $dirtyProductExtendedUpdate = \Monkey::app()->repoFactory->create('DirtyProductExtend')->findOneBy(['dirtyProductId' => $dirtyProductId,'shopId' => 60]);
                        $dirtyProductExtendedUpdate->name = $dirtyProductExtended['name'];
                        $dirtyProductExtendedUpdate->description = $dirtyProductExtended['description'];
                        $dirtyProductExtendedUpdate->season = $dirtyProductExtended['season'];
                        $dirtyProductExtendedUpdate->audience = $dirtyProductExtended['audience'];
                        $dirtyProductExtendedUpdate->cat1 = $dirtyProductExtended['cat1'];
                        $dirtyProductExtendedUpdate->cat2 = $dirtyProductExtended['cat2'];
                        $dirtyProductExtendedUpdate->cat3 = $dirtyProductExtended['cat3'];
                        $dirtyProductExtendedUpdate->cat4 = $dirtyProductExtended['cat4'];
                        $dirtyProductExtendedUpdate->shopId = $dirtyProductExtended['shopId'];
                        $dirtyProductExtendedUpdate->generalColor = $dirtyProductExtended['generalColor'];
                        $dirtyProductExtendedUpdate->colorDescription = $dirtyProductExtended['colorDescription'];
                        $dirtyProductExtendedUpdate->update();
                    } else {
                        //error
                        //log
                        continue;
                    }

                $i++;
            }
            $this->log('log','AbsoftImporter','count line',$i);
        }catch(\Throwable $e){
            $this->log('Error','AbsoftImporter',$e->getMessage(),$e->getLine());
        }
    }

    public function readProgressive()
    {
        //read SKUS ------------------
        $progressives = $this->progressiveF;
        $shopOk = 0;
        $shopKo = 0;

        $i = 0;
        $dirtyProductRepo = \Monkey::app()->repoFactory->create('DirtyProduct');
        while (($values = fgetcsv($progressives,0,$this->config->fetch('miscellaneous','separator'),'|')) !== false) {
            try {
                $dirtySkus = [];
                $line = implode($this->config->fetch('miscellaneous','separator'),$values);
                $dirtySkus['extSkuId'] = $values[0];
                $dirtySkus['extSizeId'] = $values[1];
                $dirtySkus['qty'] = $values[2];
                $dirtySkus['size'] = 'TU';
                $dirtySkus['shopId'] = 60;
                $dirtyProduct = $dirtyProductRepo->findOneBy(['var' => $dirtySkus['extSkuId'],'shopId'=>$dirtySkus['shopId']]);

                $dirtySkus['dirtyProductId'] = $dirtyProduct->id;
                $dirtySkus['value'] = $dirtyProduct->value;
                $dirtySkus['price'] = $dirtyProduct->price;
                $dirtySkus['salePrice'] = $dirtyProduct->salePrice;

                $crc32 = md5($dirtySkus[0]);
                $dirtySkus[$i]['checksum'] = $crc32;
                $exist = $this->app->dbAdapter->select("DirtySku",['checksum' => $crc32,'shopId' =>$dirtySkus['shopId']])->fetchAll();

                /** Already written */
                if (count($exist) == 0) {
                    $dirtySkuInsert=\Monkey::app()->repoFactory->create('DirtySku')->getEmptyEntity();
                    $dirtySkuInsert->extSizeId= $dirtySkus['extSkuId'];
                    $dirtySkuInsert->size=$dirtySkus['size'];
                    $dirtySkuInsert->shopId=60;
                    $dirtySkuInsert->qty=$dirtySkus['qty'];
                    $dirtySkuInsert->value=$dirtySkus['value'];
                    $dirtySkuInsert->dirtyProductId= $dirtySkus['dirtyProductId'];
                    $dirtySkuInsert->price=$dirtySkus['value'];
                    $dirtySkuInsert->salePrice=$dirtySkus['salePrice'];
                    $dirtySkuInsert->checksum=$dirtySkus['checksum'];
                    $dirtySkuInsert->insert();
                    $this->debug('processFile','Sku don\'t Exist, insert',$dirtySkus['dirtyProductId']);

                } elseif (count($exist) == 1) {
                    $dirtySkuUpdate=\Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['id'=>$exit[0]['id']]);
                    $dirtySkuUpdate->extSizeId= $dirtySkus['extSkuId'];
                    $dirtySkuUpdate->size=$dirtySkus['size'];
                    $dirtySkuUpdate->shopId=60;
                    $dirtySkuUpdate->qty=$dirtySkus['qty'];
                    $dirtySkuUpdate->value=$dirtySkus['value'];
                    $dirtySkuUpdate->dirtyProductId= $dirtySkus['dirtyProductId'];
                    $dirtySkuUpdate->price=$dirtySkus['value'];
                    $dirtySkuUpdate->salePrice=$dirtySkus['salePrice'];
                    $dirtySkuUpdate->checksum=$dirtySkus['checksum'];
                    $dirtySkuUpdate->update();
                    $this->debug('processFile','Sku Exist, update',$exit[0]['id']);

                } else throw new BambooException('More than 1 sku found to update');

                $seenSkus[] = $dirtySkus['id'];


            } catch (\Throwable $e) {
                $this->error('Read Sku','Error while reading Sku',$e);

            }
            $i++;
        }
    }


    public function readSku()
    {
        //read SKUS ------------------
        $skus = $this->skusF;
        $shopOk = 0;
        $shopKo = 0;

        $i = 0;
        $dirtySkuRepo=\Monkey::app()->repoFactory->create('DirtySku');
        while (($values = fgetcsv($skus,0,$this->config->fetch('miscellaneous','separator'),'|')) !== false) {
            try {
                $dirtySku = [];

                $dirtySku['extSizeId'] = $values[2];
                $dirtySku['size'] = $values[1].'-'.$values[3];
                $exist = $this->app->dbAdapter->select("DirtySku",['extSizeId' => $dirtySku['extSizeId'],'shopId' => 60])->fetchAll();

                /** Already written */
                if (count($exist) == 1) {
                    $this->seenSkus[] = $exist[0]['id'];
                    $updateDirtySku=$dirtySkuRepo->findOneBy(['id'=>$exist[0]['id']]);
                    $updateDirtySku->size=$dirtySku['size'];
                    $updateDirtySku->update();
                }else{
                    continue;
                }

            } catch (\Throwable $e) {
                $this->error('Read Sku','Error while update Sku Size',$e);
            }
            $i++;
        }
    }

    /**
     *
     */
    public function findZeroSkus()
    {

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
	                                      ps.shopId = ?",[60])->fetchAll();

        $this->report("findZeroSkus","Product to set 0: " . count($res),[]);
        $this->report("findZeroSkus","Product not at 0: " . count($res),[]);
        $i = 0;

        foreach ($res as $one) {
            if (!in_array($one['id'],$this->seenSkus)) {
                $qty = $this->app->dbAdapter->update("DirtySku",["qty" => 0,"changed" => 1,"checksum" => null],$one);
                //$qty = $this->app->dbAdapter->update("ProductSku",["stockQty"=>0,"padding"=>0],$one);
            }
        }
        $this->report("findZeroSkus","Product set 0: " . $i,[]);
    }

    public function saveFiles()
    {
        fclose($this->skusF);
        fclose($this->mainF);
        fclose($this->progressiveF);
        $dest = $this->err ? "err" : "done";

        $now = new \DateTime();
        $phar = new \PharData($this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/import/' . $dest . '/' . $now->format('YmdHis') . '.tar');


        $phar->addFile($this->main);
        $phar->addFile($this->skus);
        $phar->addFile($this->progressive);

        if ($phar->count() > 0) {
            $phar->compress(\Phar::GZ);
        }

        unlink($this->main);
        unlink($this->skus);
        unlink($this->progressive);
        unlink($this->app->rootPath() . $this->app->cfg()->fetch('paths','productSync') . '/thesquareroma/import/' . $dest . '/' . $now->format('YmdHis') . '.tar');
    }
}
