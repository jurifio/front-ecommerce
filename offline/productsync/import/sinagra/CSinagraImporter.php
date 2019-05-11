<?php

namespace bamboo\offline\productsync\import\sinagra;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;

/**
 * Class CSinagraImporter
 * @package bamboo\offline\productsync\import\sinagra
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 11/04/2018
 * @since 1.0
 */
class CSinagraImporter extends ABluesealProductImporter
{

    public function readFile($file)
    {
        return true;
    }

    /**
     * @param $file
     * @throws BambooLogicException
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    public function processFile($file)
    {

        $countNewDirtyProduct = 0;
        $countUpdatedDirtyProduct = 0;
        $countNewDirtySku = 0;
        $countUpdatedDirtySku = 0;
        $seenSkus = [];

        $rawData = json_decode(file_get_contents($file),true);
        $this->report('processFile', 'Elements: '. count($rawData));

        foreach ($rawData as $one) {

            $newDirtyProduct = [];
            $newDirtyProductExtend = [];
            $newDirtySku = [];

            //DIRTY PRODUCT
            try {
                $this->report('processFile', 'Process DirtyProduct');

                \Monkey::app()->repoFactory->beginTransaction();

                $newDirtyProduct["shopId"] = $this->getShop()->id;
                $newDirtyProduct["brand"] = $one["marchio"];
                $newDirtyProduct["itemno"] = $one["articolo"];
                $newDirtyProduct["value"] = floatval(str_replace(',','.',$one["PrAcquisto"]));
                $newDirtyProduct["price"] = floatval(str_replace(',','.',$one["prListino"]));
                $newDirtyProduct["var"] = $one["colore"];
                $newDirtyProduct["text"] = implode(',', $newDirtyProduct);

                $newDirtyProduct["checksum"] = md5(implode(',', $newDirtyProduct));

                $newDirtyProduct["dirtyStatus"] = "F";

                $newDirtyProductExtend["season"] = $one["stagione"];
                $newDirtyProductExtend["audience"] = $one["reparto"];
                $newDirtyProductExtend["cat1"] = $one["categoria"];
                $newDirtyProductExtend["generalColor"] = $one["colore"];


                $existingDirtyProduct = \Monkey::app()->dbAdapter->selectCount("DirtyProduct", ['checksum' => $newDirtyProduct['checksum']]);

                $mainKey = [];
                if ($existingDirtyProduct == 0) {
                    //se non esiste lo cerco con l'articolo

                    $mainKey["itemno"] = $newDirtyProduct["itemno"];
                    $mainKey["var"] = $newDirtyProduct["var"];
                    $mainKey["shopId"] = $this->getShop()->id;


                    $existProductWithMainKey = \Monkey::app()->dbAdapter->select('DirtyProduct', $mainKey)->fetch();

                    //lo trovo --> qualcosa è cambiato presumibilmente il value o il price
                    if ($existProductWithMainKey) {
                        \Monkey::app()->dbAdapter->update('DirtyProduct', [
                            'value' => $newDirtyProduct["value"],
                            'price' => $newDirtyProduct["price"],
                            'text' => $newDirtyProduct["text"],
                            'checksum' => $newDirtyProduct["checksum"]
                        ], $mainKey);

                        $countUpdatedDirtyProduct++;

                        //aggiorno DirtyProductExtend
                        $existingDirtyProductExtend = \Monkey::app()->dbAdapter->select('DirtyProductExtend', ['dirtyProductId' => $existProductWithMainKey["id"]])->fetch();

                        if ($existingDirtyProductExtend) {
                            \Monkey::app()->dbAdapter->update('DirtyProductExtend', $newDirtyProductExtend, ['dirtyProductId' => $existProductWithMainKey["id"]]);
                        } else {
                            $this->error('DirtyProductExtend', 'Error while looking at dirtyProductId: ' . $existProductWithMainKey["id"] . ' on DirtyProductExtend table');
                        }
                    } else {

                        //inserisco il prodotto
                        $newDirtyProductExtend["dirtyProductId"] = \Monkey::app()->dbAdapter->insert('DirtyProduct', $newDirtyProduct);



                        //inserisco dirty product extend
                        $newDirtyProductExtend["shopId"] = $this->getShop()->id;

                        \Monkey::app()->dbAdapter->insert('DirtyProductExtend', $newDirtyProductExtend);
                        $countNewDirtyProduct++;
                    }
                } else if ($existingDirtyProduct > 1){
                    $this->error('Multiple dirty product founded', 'Procedure has founded '.$existingDirtyProduct.' dirty product');
                    continue;
                }



                \Monkey::app()->repoFactory->commit();
            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                $this->error('processFile', 'Error reading Product: ' . json_encode($one), $e);
                continue;
            }

            //DIRTY SKU
            try {
                $this->report('processFile', 'Process DirtySku');

                $dirtySku = [];
                $mainKeyForSku = [];
                $mainKeyForSku["itemno"] = $one["articolo"];
                $mainKeyForSku["var"] = $one["colore"];
                $mainKeyForSku["shopId"] = $this->getShop()->id;

                $dirtyProduct = $existingDirtyProductExtend = \Monkey::app()->dbAdapter->select('DirtyProduct', $mainKeyForSku)->fetch();

                if(!$dirtyProduct){
                    $this->error( 'Reading Skus', 'Dirty Product not found while looking at sku', json_encode($one));
                    continue;
                }


                $newDirtySku["size"] = $one["taglia"];
                $newDirtySku["shopId"] = $this->getShop()->id;
                $newDirtySku["dirtyProductId"] = $dirtyProduct["id"];
                $newDirtySku["value"] = floatval(str_replace(',','.',$one["PrAcquisto"]));
                $newDirtySku["price"] = floatval(str_replace(',','.',$one["prListino"]));
                $newDirtySku["qty"] = $one["esistenza"];
                $newDirtySku["barcode"] = $one["barcode"];
                $newDirtySku["text"] = implode(',', $newDirtySku);
                $newDirtySku["checksum"] = md5(implode(',', $newDirtySku));

                //cerco lo sku con il checksum
                $existDirtySku = \Monkey::app()->dbAdapter->selectCount('DirtySku', ['checksum' => $newDirtySku["checksum"]]);

                if($existDirtySku == 0){

                    $existDirtySkuWithMainKey = \Monkey::app()->dbAdapter->select('DirtySku', [
                        'dirtyProductId' =>  $newDirtySku["dirtyProductId"],
                        'shopId' => $newDirtySku["shopId"],
                        'size' => $newDirtySku["size"]
                    ])->fetch();

                    if($existDirtySkuWithMainKey){
                        //update
                        \Monkey::app()->dbAdapter->update('DirtySku', [
                            'value' => $newDirtySku["value"],
                            'price' => $newDirtySku["price"],
                            'qty' => $newDirtySku["qty"],
                            'changed' => 1,
                            'text' => $newDirtySku["text"],
                            'checksum' => $newDirtySku["checksum"]
                        ], [
                            'dirtyProductId' =>  $existDirtySkuWithMainKey["dirtyProductId"],
                            'shopId' => $existDirtySkuWithMainKey["shopId"],
                            'size' => $existDirtySkuWithMainKey["size"]
                        ]);

                        $dirtySku["id"] = $existDirtySkuWithMainKey["id"];
                        $seenSkus[] = $dirtySku['id'];
                        $countUpdatedDirtySku++;
                    } else {
                        //INSERT
                        $dirtySku["id"] = \Monkey::app()->dbAdapter->insert('DirtySku', $newDirtySku);
                        $seenSkus[] = $dirtySku['id'];
                        $countNewDirtySku++;
                    }

                } else if ($existDirtySku > 1){
                    $this->error('Multiple dirty sku founded', 'Procedure has founded '.$existDirtySku.' dirty sku');
                    continue;
                } else if ($existDirtySku == 1){
                    $noChangedSku = \Monkey::app()->dbAdapter->select('DirtySku', ['checksum' => $newDirtySku["checksum"]])->fetch();
                    $seenSkus[] = $noChangedSku['id'];
                }
                $this->debug('Cycle','product checking item_imgs',$one['img']);
                $dirtyPhotos = \Monkey::app()->dbAdapter->select('DirtyPhoto', ['dirtyProductId' =>  $dirtyProduct["id"]])->fetchAll();
                $position = 0;
                foreach ($one['img'] as $img) {
                    if(empty(trim($img))) continue;
                    foreach ($dirtyPhotos as $exImg) {
                        if ($exImg['url'] == $img) continue 2;
                    }
                    $position++;
                    \Monkey::app()->dbAdapter->insert('DirtyPhoto', [
                        'dirtyProductId' =>  $dirtyProduct["id"],
                        'shopId' => $this->getShop()->id,
                        'url' => $img,
                        'location' => 'url',
                        'position' => $position,
                        'worked' => 0
                    ]);
                }


            } catch (\Throwable $e){
                $this->error('processFile', 'Error reading Product: ' . json_encode($one), $e);
                continue;
            }

        }

        $this->report('processFile', 'End of reading and writing dirty product: New Dirty Product: '.$countNewDirtyProduct.' Updated Dirty product: '.$countUpdatedDirtyProduct);
        $this->report('processFile', 'End of reading and writing dirty skus: New Dirty Sku: '.$countNewDirtySku.' Updated Dirty product: '.$countUpdatedDirtySku);

        $this->findZeroSkus($seenSkus);
    }
}