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

        $this->report("Run","Fetch Filse","Carico i files");
        $this->fetchFiles();
        $this->report("Run","Read Files","Leggo i files");
        $this->readFiles();
        $this->report("Run","Read Main","Leggo il file Main cercando Prodotti");
        $this->readMain();
        $this->report("Run","Read Progressive","Leggo il file dei Progressivi e inserisco gli sku");
        $this->readProgressive();
        $this->report("Run","Find Zero Skus","Azzero le quantità dei prodotti non elaborati");
        $this->findZeroSkus();
        $this->saveFiles();
        $this->report("Run","Import END","Fine importazione the Square Roma Pdio "  );

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
        $dirtyProduct = [];
        $dirtyProductExtended = [];
        $i = 0;
        while (($values = fgetcsv($main,0,'|')) !== false) {


            $line = implode('|',$values);
            $dirtyProduct[$i]['brand'] = $values[12];
            $dirtyProduct[$i]['var'] = $values[20];
            $dirtyProduct[$i]['itemno'] = $values[0];


            $dirtyProduct[$i]['extId'] = $values[19];
            $dirtyProduct[$i]['value'] = str_replace(',','.',$values[29]);
            $dirtyProduct[$i]['price'] = str_replace(',','.',$values[30]);
            $dirtyProduct[$i]['salePrice'] = str_replace(',','.',$values[31]);
            $dirtyProductExtended[$i]['season'] = $values[6];
            $dirtyProductExtended[$i]['name'] = $values[1];
            $dirtyProductExtended[$i]['description'] = $values[2];
            $dirtyProductExtended[$i]['colorDescription'] = $values[22];
            $dirtyProductExtended[$i]['generalColor'] = $values[22];
            $dirtyProductExtended[$i]['audience'] = $values[18];
            $dirtyProductExtended[$i]['cat1'] = $values[28];
            $dirtyProductExtended[$i]['cat2'] = $values[16];
            $dirtyProductExtended[$i]['cat3'] = $values[14];
            $dirtyProductExtended[$i]['cat4'] = $values[26];
            $dirtyProductExtended[$i]['shopId'] = 60;
            $dirtyCategory= $dirtyProductExtended[$i]['audience'].'-'.$dirtyProductExtended[$i]['cat1']. '-'.$dirtyProductExtended[$i]['cat2'].'-'.$dirtyProductExtended[$i]['cat3'].'-'.$dirtyProductExtended[$i]['cat4'];
            $dirtyCategoryFind=\Monkey::app()->repoFactory->create('DictionaryCategory')->findOneBy(['shopId'=>60,'term'=>$dirtyCategory]);
            if($dirtyCategoryFind==null){
                $dirtyCategoryInsert=\Monkey::app()->repoFactory->create('DictionaryCategory')->getEmptyEntity();
                $dirtyCategoryInsert->shopId=60;
                $dirtyCategoryInsert->term=$dirtyCategory;
                $dirtyCategoryInsert->insert();
            }
            $dirtySeasonFind=\Monkey::app()->repoFactory->create('DictionarySeason')->findOneBy(['shopId'=>60,'term'=>$dirtyProductExtended[$i]['season']]);
            if($dirtySeasonFind==null){
                $dirtySeasonInsert=\Monkey::app()->repoFactory->create('DictionarySeason')->getEmptyEntity();
                $dirtySeasonInsert->shopId=60;
                $dirtySeasonInsert->term=$dirtyProductExtended[$i]['season'];
                $dirtySeasonInsert->insert();
            }
            $dirtyColorGroupFind=\Monkey::app()->repoFactory->create('DictionaryColorGroup')->findOneBy(['shopId'=>60,'term'=>$dirtyProductExtended[$i]['generalColor']]);
            if($dirtyColorGroupFind==null){
                $dirtyColorGroupInsert=\Monkey::app()->repoFactory->create('DictionaryColorGroup')->getEmptyEntity();
                $dirtyColorGroupInsert->shopId=60;
                $dirtyColorGroupInsert->term=$dirtyProductExtended[$i]['generalColor'];
                $dirtyColorGroupInsert->insert();
            }
            $dirtyBrandFind=\Monkey::app()->repoFactory->create('DictionaryBrand')->findOneBy(['shopId'=>60,'term'=> $dirtyProduct[$i]['brand']]);
            if($dirtyBrandFind==null){
                $dirtyBrandInsert =\Monkey::app()->repoFactory->create('DictionaryBrand')->getEmptyEntity();
                $dirtyBrandInsert->shopId=60;
                $dirtyBrandInsert->term=$dirtyProduct[$i]['brand'];
                $dirtyBrandInsert->insert();
            }


            $crc32 = md5($line);
            $exist = $this->app->dbAdapter->selectCount("DirtyProduct",['checksum' => $crc32,'shopId' => 60]);
            /** Already written */

            $dirtyProduct[$i]['text'] = $line;
            $dirtyProduct[$i]['checksum'] = $crc32;

            /** find existing product */
            $res = $this->app->dbAdapter->select('DirtyProduct',['extId'=> $dirtyProduct[$i]['extId'],'shopId' => 60,'var'=>$dirtyProduct[$i]['var']])->fetchAll();
            if (count($res) == 0) {
                /** è un nuovo prodotto lo scrivo */
                $dirtyProduct[$i]['shopId'] = 60;
                $dirtyProduct[$i]['dirtyStatus'] = 'E';
                $dirtyProductInsert=\Monkey::app()->repoFactory->create('DirtyProduct')->getEmptyEntity();
                $dirtyProductInsert->shopId=60;
                $dirtyProductInsert->itemno = $dirtyProduct[$i]['itemno'];
                $dirtyProductInsert->brand= $dirtyProduct[$i]['brand'];
                $dirtyProductInsert->var=$dirtyProduct[$i]['var'];
                $dirtyProductInsert->text=$dirtyProduct[$i]['text'];
                $dirtyProductInsert->value=$dirtyProduct[$i]['value'];
                $dirtyProductInsert->price=$dirtyProduct[$i]['price'];
                $dirtyProductInsert->salePrice= $dirtyProduct[$i]['salePrice'];
                $dirtyProductInsert->checksum = $dirtyProduct[$i]['checksum'];
                $dirtyProductInsert->extId=$dirtyProduct[$i]['extId'];
                $dirtyProductInsert->insert();

                $lastId = $this->app->dbAdapter->query('SELECT max(`id`) as dirtyProductId FROM `DirtyProduct`',[])->fetchAll()[0]['dirtyProductId'];
                $dirtyProductExtended[$i]['dirtyProductId'] = $lastId;
                $dirtyProductExtendInsert=\Monkey::app()->repoFactory->create('DirtyProductExtend')->getEmptyEntity();
                $dirtyProductExtendInsert->shopId=60;
                $dirtyProductExtendInsert->name=$dirtyProductExtended[$i]['name'];
                $dirtyProductExtendInsert->description=$dirtyProductExtended[$i]['description'];
                $dirtyProductExtendInsert->season = $dirtyProductExtended[$i]['season'];
                $dirtyProductExtendInsert->audience=$dirtyProductExtended[$i]['audience'];
                $dirtyProductExtendInsert->cat1 = $dirtyProductExtended[$i]['cat1'];
                $dirtyProductExtendInsert->cat2 = $dirtyProductExtended[$i]['cat2'];
                $dirtyProductExtendInsert->cat3 = $dirtyProductExtended[$i]['cat3'];
                $dirtyProductExtendInsert->cat4 = $dirtyProductExtended[$i]['cat4'];
                $dirtyProductExtendInsert->colorDescription = $dirtyProductExtended[$i]['colorDescription'];
                $dirtyProductExtendInsert->generalColor = $dirtyProductExtended[$i]['generalColor'];
                $dirtyProductExtendInsert->checksum=$dirtyProduct[$i]['checksum'];
                $dirtyProductExtendInsert->insert();


            } elseif (count($res) == 1) {
                /** update existing product if changed */
                //exist.. what to do? uhm... update?
                continue;
                /*$dirtyProductUpdate = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['extId' => $dirtyProduct[$i]['extId'],'var' => $dirtyProduct[$i]['var'],'shopId'=>60]);
                $dirtyProductId = $dirtyProductUpdate->id;
                $dirtyProductUpdate->itemno = $dirtyProduct[$i]['itemno'];
                $dirtyProductUpdate->brand = $dirtyProduct[$i]['brand'];
                $dirtyProductUpdate->price = $dirtyProduct[$i]['price'];
                $dirtyProductUpdate->value = $dirtyProduct[$i]['value'];
                $dirtyProductUpdate->salePrice = $dirtyProduct[$i]['salePrice'];
                $dirtyProductUpdate->update();
                $dirtyProductExtendedUpdate = \Monkey::app()->repoFactory->create('DirtyProductExtend')->findOneBy(['dirtyProductId' => $dirtyProductId,'shopId'=>60]);
                $dirtyProductExtendedUpdate->name = $dirtyProductExtended[$i]['name'];
                $dirtyProductExtendedUpdate->description = $dirtyProductExtended[$i]['description'];
                $dirtyProductExtendedUpdate->season = $dirtyProductExtended[$i]['season'];
                $dirtyProductExtendedUpdate->audience = $dirtyProductExtended[$i]['audience'];
                $dirtyProductExtendedUpdate->cat1 = $dirtyProductExtended[$i]['cat1'];
                $dirtyProductExtendedUpdate->cat2 = $dirtyProductExtended[$i]['cat2'];
                $dirtyProductExtendedUpdate->cat3 = $dirtyProductExtended[$i]['cat3'];
                $dirtyProductExtendedUpdate->cat4 = $dirtyProductExtended[$i]['cat4'];
                $dirtyProductExtendedUpdate->shopId = $dirtyProductExtended[$i]['shopId'];
                $dirtyProductExtendedUpdate->generalColor = $dirtyProductExtended[$i]['generalColor'];
                $dirtyProductExtendedUpdate->colorDescription = $dirtyProductExtended[$i]['colorDescription'];
                $dirtyProductExtendedUpdate->update();*/
            } else {
                //error
                //log
                continue;
            }

            $i++;
        }
    }

    public function readProgressive()
    {
        $skus = $this->skusF;
        $shopOk = 0;
        $shopKo = 0;
        $dirtySku = [];
        $k = 0;
        while (($values = fgetcsv($skus,0,'|')) !== false) {


            $dirtySku[$k]['extSizeId'] = $values[2];
            $dirtySku[$k]['size'] = $values[3];
            $k++;

        }


        //read Progressive ------------------
        $progressives = $this->progressiveF;
        $shopOk = 0;
        $shopKo = 0;
        $dirtySkus = [];
        $i = 0;
        $dirtyProductRepo = \Monkey::app()->repoFactory->create('DirtyProduct');
        while (($values = fgetcsv($progressives,0,'|')) !== false) {
            try {


                $dirtySkus[$i]['extSkuId'] = $values[0];
                $dirtySkus[$i]['extSizeId'] = $values[1];
                $dirtySkus[$i]['qty'] = $values[2];
                foreach($dirtySku as $key=>$value){
                    if($value['extSizeId']==$dirtySkus[$i]['extSizeId']){
                        $dirtySkus[$i]['size']=$value['size'];
                        break;
                    }
                }

                $dirtySkus[$i]['shopId'] = 60;
                $dirtyProduct = $dirtyProductRepo->findOneBy(['extId' => $dirtySkus[$i]['extSkuId'],'shopId'=>$dirtySkus[$i]['shopId']]);
                if ($dirtyProduct == null) {
                    continue;
                }
                $dirtyProductExtend=\Monkey::app()->repoFactory->create('DirtyProductExtend')->findOneBy(['dirtyProductId'=>$dirtyProduct->id]);
                if($dirtyProductExtend!=null){
                    $linedirty=$dirtyProductExtend->audience.'-'.$dirtyProductExtend->cat1.'-'.$dirtyProductExtend->cat2.'-'.$dirtyProductExtend->cat3.'-'.$dirtyProductExtend->cat4;
                      $dirtySizeFind=\Monkey::app()->repoFactory->create('DictionarySize')->findOneBy(['shopId'=>60,'term'=> $dirtySkus[$i]['size'],'categoryFriend'=>$linedirty]);
                      if($dirtySizeFind==null){
                          $dirtySizeInsert=\Monkey::app()->repoFactory->create('DictionarySize')->getEmptyEntity();
                          $dirtySizeInsert->term=$dirtySkus[$i]['size'];
                          $dirtySizeInsert->shopId=60;
                          $dirtySizeInsert->categoryFriend=$linedirty;
                          $dirtySizeInsert->insert();
                      }
                }
                $dirtySkus[$i]['dirtyProductId'] = $dirtyProduct->id;
                $dirtySkus[$i]['value'] = $dirtyProduct->value;
                $dirtySkus[$i]['price'] = $dirtyProduct->price;
                $dirtySkus[$i]['salePrice'] = $dirtyProduct->salePrice;
                $line = implode('|',$dirtySkus[$i]);

                $crc32 = md5($line);
                $dirtySkus[$i]['checksum'] = $crc32;
                $exist = $this->app->dbAdapter->select("DirtySku",['checksum'=>$crc32,'shopId' =>$dirtySkus[$i]['shopId']])->fetchAll();

                /** Already written */
                if (count($exist) == 0) {
                    $dirtySkuInsert=\Monkey::app()->repoFactory->create('DirtySku')->getEmptyEntity();
                    $dirtySkuInsert->extSkuId= $dirtySkus[$i]['extSkuId'];
                    $dirtySkuInsert->size=$dirtySkus[$i]['size'];
                    $dirtySkuInsert->extSizeId= $dirtySkus[$i]['extSizeId'];
                    $dirtySkuInsert->shopId=60;
                    $dirtySkuInsert->qty=$dirtySkus[$i]['qty'];
                    $dirtySkuInsert->value=$dirtySkus[$i]['value'];
                    $dirtySkuInsert->dirtyProductId= $dirtySkus[$i]['dirtyProductId'];
                    $dirtySkuInsert->price=$dirtySkus[$i]['price'];
                    $dirtySkuInsert->salePrice=$dirtySkus[$i]['salePrice'];
                    $dirtySkuInsert->checksum=$dirtySkus[$i]['checksum'];
                    $dirtySkuInsert->insert();
                    $this->debug('processFile','Sku don\'t Exist, insert',$dirtySkus[$i]['dirtyProductId']);

                } elseif (count($exist) == 1) {
                    $dirtySkuUpdate=\Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['id'=>$exist[0]['id']]);

                    $dirtySkuUpdate->qty=$dirtySkus[$i]['qty'];
                    $dirtySkuUpdate->value=$dirtySkus[$i]['value'];
                    $dirtySkuUpdate->price=$dirtySkus[$i]['price'];
                    $dirtySkuUpdate->salePrice=$dirtySkus[$i]['salePrice'];
                    $dirtySkuUpdate->update();
                    $this->debug('processFile','Sku Exist, update',$exist[0]['id']);
                    $this->seenSkus[] = $exist[0]['id'];

                } else throw new BambooException('More than 1 sku found to update');




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
        $dirtySku = [];
        $i = 0;
        $dirtySkuRepo=\Monkey::app()->repoFactory->create('DirtySku');
        while (($values = fgetcsv($skus,0,'|')) !== false) {
            try {


                $dirtySku[$i]['extSizeId'] = $values[2];
                $dirtySku[$i]['size'] = $values[3];
                $exist = $this->app->dbAdapter->select("DirtySku",['extSizeId' => $dirtySku[$i]['extSizeId'],'shopId' => 60])->fetchAll();

                /** Already written */
                if (count($exist) == 1) {
                    $this->seenSkus[] = $exist[0]['id'];
                    $updateDirtySku=$dirtySkuRepo->findOneBy(['id'=>$exist[0]['id']]);
                    $updateDirtySku->size=$dirtySku[$i]['size'];
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
