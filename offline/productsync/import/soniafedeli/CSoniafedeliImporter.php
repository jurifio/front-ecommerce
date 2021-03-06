<?php

namespace bamboo\offline\productsync\import\soniafedeli;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\domain\entities\CDirtySkuHasStoreHouse;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

/**
 * Class CBarbagalloImporter
 * @package bamboo\offline\productsync\import\alducadaosta
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
class CSoniafedeliImporter extends ABluesealProductImporter
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
        \Monkey::app()->vendorLibraries->load("woocommerce");
        $countNewDirtyProduct = 0;
        $countUpdatedDirtyProduct = 0;
        $countNewDirtySku = 0;
        $countUpdatedDirtySku = 0;
        $seenSkus = [];
        $countProduct=0;
        $woocommerce = new Client(
            'http://www.soniafedeliparrucchieria.it/',
            'ck_bf857ee767ad48d531aa47971c8f431cecffb871',
            'cs_95770adaf9a657c856ce7ba9ef0cfe6c8980847d',
            [
                'wp_api' => true,
                'version' => 'wc/v3',
                'query_string_auth' => true
            ]
        );
        $productSeason = \Monkey::app()->dbAdapter->query('select max(id) as productSeasonId from ProductSeason ',[])->fetchAll();
        foreach ($productSeason as $val) {
            $productSeasonId = $val['productSeasonId'];
        }

        $page=10;
        $i=1;
        $countProduct=0;
        $this->report('processFile','Elements: ' ,'');
        for ($i = 1; $i < $page; $i++) {
            // Array of response results.
            $results = $woocommerce->get('products',array('page' => $i,'per_page' => 100));






            foreach ($results as $one) {

                $newDirtyProduct = [];
                $newDirtyProductExtend = [];
                $newDirtySku = [];

                //DIRTY PRODUCT
                try {
                    $this->report('processFile','Process DirtyProduct');

                    \Monkey::app()->repoFactory->beginTransaction();

                    $newDirtyProduct["shopId"] = $this->getShop()->id;
                    $newDirtyProduct["brand"] = $one->name;
                    $newDirtyProduct["itemno"] = $one->sku;
                    $newDirtyProduct["value"] = floatval(str_replace(',','.',$one->price));
                    $newDirtyProduct["price"] = floatval(str_replace(',','.',$one->price));
                    $newDirtyProduct["var"] = $one->slug;
                    $newDirtyProduct["text"] = implode(',',$newDirtyProduct);

                    $newDirtyProduct["checksum"] = md5(implode(',',$newDirtyProduct));

                    $newDirtyProduct["dirtyStatus"] = "F";
                    $newDirtyProductExtend["name"] = $one->slug;
                    $newDirtyProductExtend["season"] = $productSeasonId;
                    $newDirtyProductExtend["description"] = $one->description;


                    $newDirtyProductExtend["cat1"] = 'beauty Products';
                    $z=0;
                    foreach($one->categories as $category){
                        if($z==0){
                            $newDirtyProductExtend["cat2"] = $category->name;
                        }
                        if($z==1){
                            $newDirtyProductExtend["cat3"] = $category->name;
                        }
                        $z++;
                    }



                    $existingDirtyProduct = \Monkey::app()->dbAdapter->selectCount("DirtyProduct",['checksum' => $newDirtyProduct['checksum']]);

                    $mainKey = [];
                    if ($existingDirtyProduct == 0) {
                        //se non esiste lo cerco con l'articolo

                        $mainKey["itemno"] = $newDirtyProduct["itemno"];
                        $mainKey["var"] = $newDirtyProduct["var"];
                        $mainKey["shopId"] = $this->getShop()->id;

                        $existProductWithMainKey = \Monkey::app()->dbAdapter->select('DirtyProduct',$mainKey)->fetch();

                        //lo trovo --> qualcosa è cambiato presumibilmente il value o il price
                        if ($existProductWithMainKey) {
                            \Monkey::app()->dbAdapter->update('DirtyProduct',[
                                'value' => $newDirtyProduct["value"],
                                'price' => $newDirtyProduct["price"],
                                'text' => $newDirtyProduct["text"],
                                'checksum' => $newDirtyProduct["checksum"]
                            ],$mainKey);

                            $countUpdatedDirtyProduct++;

                            //aggiorno DirtyProductExtend
                            $existingDirtyProductExtend = \Monkey::app()->dbAdapter->select('DirtyProductExtend',['dirtyProductId' => $existProductWithMainKey["id"]])->fetch();

                            if ($existingDirtyProductExtend) {
                                \Monkey::app()->dbAdapter->update('DirtyProductExtend',$newDirtyProductExtend,['dirtyProductId' => $existProductWithMainKey["id"]]);
                            } else {
                                $this->error('DirtyProductExtend','Error while looking at dirtyProductId: ' . $existProductWithMainKey["id"] . ' on DirtyProductExtend table');
                            }
                        } else {

                            //inserisco il prodotto
                            $newDirtyProductExtend["dirtyProductId"] = \Monkey::app()->dbAdapter->insert('DirtyProduct',$newDirtyProduct);

                            //inserisco dirty product extend
                            $newDirtyProductExtend["shopId"] = $this->getShop()->id;

                            \Monkey::app()->dbAdapter->insert('DirtyProductExtend',$newDirtyProductExtend);
                            $countNewDirtyProduct++;
                        }
                    } else if ($existingDirtyProduct > 1) {
                        $this->error('Multiple dirty product founded','Procedure has founded ' . $existingDirtyProduct . ' dirty product');
                        continue;
                    }

                    \Monkey::app()->repoFactory->commit();
                } catch (\Throwable $e) {
                    \Monkey::app()->repoFactory->rollback();
                    $this->error('processFile','Error reading Product: ' . $one->sku,$e->getLine().'-'.$e->getMessage());
                    continue;
                }

                //DIRTY SKU
                try {
                    $this->report('processFile','Process DirtySku');

                    $dirtySku = [];
                    $mainKeyForSku = [];
                    $mainKeyForSku["itemno"] = $one->sku;
                    $mainKeyForSku["var"] = $one->slug;
                    $mainKeyForSku["shopId"] = $this->getShop()->id;

                    $dirtyProduct = $existingDirtyProductExtend = \Monkey::app()->dbAdapter->select('DirtyProduct',$mainKeyForSku)->fetch();

                    if (!$dirtyProduct) {
                        $this->error('Reading Skus','Dirty Product not found while looking at sku',json_encode($one));
                        continue;
                    }


                    $newDirtySku["size"] = 'TU';
                    $newDirtySku["shopId"] = $this->getShop()->id;
                    $newDirtySku["dirtyProductId"] = $dirtyProduct["id"];
                    $newDirtySku["value"] = floatval(str_replace(',','.',$one->price));
                    $newDirtySku["price"] = floatval(str_replace(',','.',$one->price));
                    $newDirtySku["qty"] = $one->stock_quantity;
                    $newDirtySku["text"] = implode(',',$newDirtySku);
                    $newDirtySku["checksum"] = md5(implode(',',$newDirtySku));

                    //cerco lo sku con il checksum
                    $existDirtySku = \Monkey::app()->dbAdapter->selectCount('DirtySku',['checksum' => $newDirtySku["checksum"]]);

                    if ($existDirtySku == 0) {

                        $existDirtySkuWithMainKey = \Monkey::app()->dbAdapter->select('DirtySku',[
                            'dirtyProductId' => $newDirtySku["dirtyProductId"],
                            'shopId' => $newDirtySku["shopId"],
                            'size' => $newDirtySku["size"]
                        ])->fetch();

                        if ($existDirtySkuWithMainKey) {
                            //update
                            \Monkey::app()->dbAdapter->update('DirtySku',[
                                'value' => $newDirtySku["value"],
                                'price' => $newDirtySku["price"],
                                'qty' => $newDirtySku["qty"],
                                'changed' => 1,
                                'text' => $newDirtySku["text"],
                                'checksum' => $newDirtySku["checksum"]
                            ],[
                                'dirtyProductId' => $existDirtySkuWithMainKey["dirtyProductId"],
                                'shopId' => $existDirtySkuWithMainKey["shopId"],
                                'size' => $existDirtySkuWithMainKey["size"]
                            ]);


                            $dirtySku["id"] = $existDirtySkuWithMainKey["id"];
                            $seenSkus[] = $dirtySku['id'];
                            $countUpdatedDirtySku++;
                            /* @var CDirtySkuHasStoreHouse $FindDirtyHasStoreHouse * */
                            $findDirtyHasStoreHouse = \Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->findOneBy([
                                'shopId' => $this->getShop()->id,
                                'size' => $existDirtySkuWithMainKey["size"],
                                'dirtySkuId' => $existDirtySkuWithMainKey['id'],
                                'dirtyProductId' => $existDirtySkuWithMainKey["dirtyProductId"],
                                'storeHouseId' => 1
                            ]);
                            if (!$findDirtyHasStoreHouse) {
                                /* @var CDirtySkuHasStoreHouse $insertDirtySkuHasStoreHouse * */
                                $insertDirtySkuHasStoreHouse = \Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->getEmptyEntity();
                                $insertDirtySkuHasStoreHouse->shopId = $this->getShop()->id;
                                $insertDirtySkuHasStoreHouse->dirtySkuId = $existDirtySkuWithMainKey["id"];
                                $insertDirtySkuHasStoreHouse->storeHouseId = 1;
                                $insertDirtySkuHasStoreHouse->size = $existDirtySkuWithMainKey['size'];
                                $insertDirtySkuHasStoreHouse->dirtyProductId = $existDirtySkuWithMainKey['dirtyProductId'];
                                $insertDirtySkuHasStoreHouse->productVariantId = $dirtyProduct['productVariantId'];
                                $insertDirtySkuHasStoreHouse->qty = $one->stock_quantity;
                                $insertDirtySkuHasStoreHouse->productSizeId = $existDirtySkuWithMainKey['productSizeId'];
                                $insertDirtySkuHasStoreHouse->insert();
                            } else {
                                $findDirtyHasStoreHouse->dirtyProductId = $existDirtySkuWithMainKey['id'];
                                $findDirtyHasStoreHouse->productId = $dirtyProduct['productId'];
                                $findDirtyHasStoreHouse->productVariantId = $dirtyProduct['productVariantId'];
                                $findDirtyHasStoreHouse->productSizeId = $existDirtySkuWithMainKey['productSizeId'];
                                $findDirtyHasStoreHouse->qty = $one->stock_quantity;
                                $findDirtyHasStoreHouse->update();
                            }

                        } else {
                            //INSERT
                            $dirtySku["id"] = \Monkey::app()->dbAdapter->insert('DirtySku',$newDirtySku);
                            $seenSkus[] = $dirtySku['id'];
                            $countNewDirtySku++;
                        }

                    } else if ($existDirtySku > 1) {
                        $this->error('Multiple dirty sku founded','Procedure has founded ' . $existDirtySku . ' dirty sku');
                        continue;
                    } else if ($existDirtySku == 1) {
                        $noChangedSku = \Monkey::app()->dbAdapter->select('DirtySku',['checksum' => $newDirtySku["checksum"]])->fetch();
                        $seenSkus[] = $noChangedSku['id'];
                        /* @var CDirtySkuHasStoreHouse $FindDirtyHasStoreHouse * */
                        $findDirtyHasStoreHouse = \Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->findOneBy([
                            'shopId' => $this->getShop()->id,
                            'size' => $noChangedSku["size"],
                            'dirtySkuId' => $noChangedSku['id'],
                            'dirtyProductId' => $noChangedSku["dirtyProductId"],
                            'storeHouseId' => 1
                        ]);
                        if (!$findDirtyHasStoreHouse) {
                            /* @var CDirtySkuHasStoreHouse $insertDirtySkuHasStoreHouse * */
                            $insertDirtySkuHasStoreHouse = \Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->getEmptyEntity();
                            $insertDirtySkuHasStoreHouse->shopId = $this->getShop()->id;
                            $insertDirtySkuHasStoreHouse->dirtySkuId = $noChangedSku["id"];
                            $insertDirtySkuHasStoreHouse->storeHouseId = 1;
                            $insertDirtySkuHasStoreHouse->size = $noChangedSku['size'];
                            $insertDirtySkuHasStoreHouse->dirtyProductId = $noChangedSku['dirtyProductId'];
                            $insertDirtySkuHasStoreHouse->productVariantId = $dirtyProduct['productVariantId'];
                            $insertDirtySkuHasStoreHouse->qty = $one->stock_quantity;
                            $insertDirtySkuHasStoreHouse->productSizeId = $noChangedSku['productSizeId'];
                            $insertDirtySkuHasStoreHouse->insert();
                        } else {
                            $findDirtyHasStoreHouse->dirtyProductId = $noChangedSku['id'];
                            $findDirtyHasStoreHouse->productId = $dirtyProduct['productId'];
                            $findDirtyHasStoreHouse->productVariantId = $dirtyProduct['productVariantId'];
                            $findDirtyHasStoreHouse->productSizeId = $noChangedSku['productSizeId'];
                            $findDirtyHasStoreHouse->qty = $one->stock_quantity;
                            $findDirtyHasStoreHouse->update();
                        }
                    }
                    $this->debug('Cycle','product checking item_imgs',$one['img']);
                    $dirtyPhotos = \Monkey::app()->dbAdapter->select('DirtyPhoto',['dirtyProductId' => $dirtyProduct["id"]])->fetchAll();
                    $position = 0;
                    foreach($one->images as $image) {
                        foreach ($image->name as $img) {
                            if (empty(trim($img))) continue;
                            foreach ($dirtyPhotos as $exImg) {
                                if ($exImg['url'] == $img) continue 2;
                            }
                            $position++;
                            \Monkey::app()->dbAdapter->insert('DirtyPhoto',[
                                'dirtyProductId' => $dirtyProduct["id"],
                                'shopId' => $this->getShop()->id,
                                'url' => $img,
                                'location' => 'url',
                                'position' => $position,
                                'worked' => 0
                            ]);
                        }
                    }


                } catch (\Throwable $e) {
                    $this->error('processFile','Error reading Product: ' . json_encode($one),$e->getLine().'-'.$e->getMessage());
                    continue;
                }

            }
        }

        $this->report('processFile', 'End of reading and writing dirty product: New Dirty Product: '.$countNewDirtyProduct.' Updated Dirty product: '.$countUpdatedDirtyProduct);
        $this->report('processFile', 'End of reading and writing dirty skus: New Dirty Sku: '.$countNewDirtySku.' Updated Dirty product: '.$countUpdatedDirtySku);

        $this->findZeroSkus($seenSkus);
    }
}