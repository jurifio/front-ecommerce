<?php

namespace bamboo\offline\productsync\import\stylecommerce;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\domain\entities\CDirtySkuHasStoreHouse;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;

/**
 * Class CStylecommerceImporter
 * @package bamboo\offline\productsync\import\stylecommerce
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 16/07/2021
 * @since 1.0
 */
class CStylecommerceImporter extends ABluesealProductImporter
{

    public function readFile($file)
    {
        return true;
    }

    public function processFile($file)
    {
        $productKeys = $this->config->fetch('keys', 'product');

        $rows = \Monkey::app()->dbAdapter->query('SELECT keysChecksum, id, checksum FROM DirtyProduct WHERE shopId = ? AND keysChecksum IS NOT NULL', [$this->getShop()->id])->fetchAll();
        $keysChecksums = [];
        $checksums = [];

        $seenSkus = [];

        foreach ($rows as $one) {
            $keysChecksums[$one['keysChecksum']] = $one['id'];
            $checksums[$one['checksum']] = $one['id'];
        }

        $rows = \Monkey::app()->dbAdapter->query('SELECT checksum, id FROM DirtySku WHERE shopId = ? and qty > 0', [$this->getShop()->id])->fetchAll();
        $skusChecksums = [];
        foreach ($rows as $row) {
            $skusChecksums[$row['checksum']] = $row['id'];
        }

        $rawData = json_decode(file_get_contents($file),true);
        $this->report('processFile', 'Elements: '. count($rawData));

        $productSkus = 0;
        foreach ($rawData as $rawSkus) {
            foreach ($rawSkus['items'] as $rawSku) {

                $productSkus++;
                if ($productSkus % 200 == 0) {
                    $this->report('Cycle', 'Working skus: ' . $productSkus);
                }
                try {
                    $dirtyProduct = [];
                    $this->debug('Cycle', 'start work', $rawSku);
                    $rawProduct = $rawSku;
                    unset($rawProduct['skuID']);
                    unset($rawProduct['size']);
                    unset($rawProduct['stock']);
                    unset($rawProduct['salePrice']);
                    unset($rawProduct['marketPrice']);
                    $dirtyProduct['checksum'] = md5(json_encode($rawProduct));
                    unset($rawProduct);

                    if (isset($checksums[$dirtyProduct['checksum']])) {
                        $dirtyProduct['id'] = $checksums[$dirtyProduct['checksum']];
                        $this->debug('Cycle', 'product checksum exists', $dirtyProduct);
                    } else {

                        \Monkey::app()->repoFactory->beginTransaction();
                        $dirtyProductExtend = [];

                        $this->debug('Cycle', 'product checksum don\'t exists', $dirtyProduct);
                        //populate dirties
                        $dirtyProduct['extId'] = $rawSku['product_id'];
                        $dirtyProduct['brand'] = $rawSku['brand'];
                        $dirtyProduct['itemno'] = $rawSku['product_reference'];
                        $dirtyProduct['var'] = $rawSku['color_reference'];
                        $dirtyProduct['price']=$rawSku['retail_price'];
                        $dirtyProduct['value']=$rawSku['price'];
                        $dirtyProduct['keysChecksum'] = md5(implode('::', $this->mapKeys($dirtyProduct, $productKeys)));

                        $dirtyProductExtend['audience'] = $rawSku['gender'];
                        $dirtyProductExtend['cat1'] = $rawSku['first_category'];
                        $dirtyProductExtend['cat2'] = $rawSku['second_category'];
                        $dirtyProductExtend['generalColor'] = $rawSku['color'];
                        $dirtyProductExtend['sizeGroup'] = 'IT';
                        $dirtyProductExtend['name'] = $rawSku['item_name'];
                        $dirtyProductExtend['description'] = $rawSku['item_description'];
                        $dirtyProductExtend['season'] = $rawSku['season_reference'].$rawSku['season_year'];
                        $collectDetails = explode(',', $rawSku['item_description']);
                        //Filling Details
                        array_count_values($collectDetails);

                        $details = [];
                        foreach ($collectDetails as $collectDetail) {
                            array_push($details, $collectDetail);

                        }

                        //Filling Details
                        /*  $details = [
                              'det1' => $rawSku['material'],
                              'det2' => $rawSku['color'],
                              'det3' => $rawSku['categoryName']
                          ];*/

                        if (isset($keysChecksums[$dirtyProduct['keysChecksum']])) {
                            $this->debug('Cycle', 'product exists, update', $dirtyProduct);
                            //product already Existing UPDATE
                            $dirtyProductId = $keysChecksums[$dirtyProduct['keysChecksum']];
                            \Monkey::app()->dbAdapter->update('DirtyProduct', $dirtyProduct, [
                                    'id' => $dirtyProductId,
                                    'shopId' => $this->getShop()->id
                                ]
                            );
                            $dirtyProduct['id'] = $dirtyProductId;
                            $dirtyProduct['shopId'] = $this->getShop()->id;

                            \Monkey::app()->dbAdapter->update('DirtyProductExtend', $dirtyProductExtend, [
                                'dirtyProductId' => $dirtyProduct['id'],
                                'shopId' => $this->getShop()->id
                            ]);

                            $checksums[$dirtyProduct['checksum']] = $dirtyProduct['id'];
                        } else {
                            $this->debug('Cycle', 'product don\'t exist, insert', $dirtyProduct);
                            //INSERT
                            $dirtyProduct['shopId'] = $this->getShop()->id;
                            $dirtyProduct['dirtyStatus'] = 'F';
                            $dirtyProduct['id'] = \Monkey::app()->dbAdapter->insert('DirtyProduct', $dirtyProduct);

                            $dirtyProductExtend['shopId'] = $this->getShop()->id;
                            $dirtyProductExtend['dirtyProductId'] = $dirtyProduct['id'];
                            \Monkey::app()->dbAdapter->insert('DirtyProductExtend', $dirtyProductExtend);

                            $checksums[$dirtyProduct['checksum']] = $dirtyProduct['id'];
                            $keysChecksums[$dirtyProduct['keysChecksum']] = $dirtyProduct['id'];
                        }

                        $this->debug('Cycle', 'product checking details', $details);
                        $dirtyDetails = \Monkey::app()->dbAdapter->select('DirtyDetail', ['dirtyProductId' => $dirtyProduct['id']])->fetchAll();
                        $insertDet = 'det';
                        $countInsertDet = 1;
                        foreach ($details as $detail) {
                            if (empty(trim($detail))) continue;
                            foreach ($dirtyDetails as $dirtyDetail) {
                                if ($detail == $dirtyDetail['content']) continue 2;
                            }
                            $key = $insertDet . $countInsertDet;
                            \Monkey::app()->dbAdapter->insert('DirtyDetail', [
                                'dirtyProductId' => $dirtyProduct['id'],
                                'label' => $key,
                                'content' => $detail
                            ]);
                            $countInsertDet = $countInsertDet + 1;
                        }

                        $this->debug('Cycle', 'product checking item_imgs', $rawSku['item_images']);
                        $dirtyPhotos = \Monkey::app()->dbAdapter->select('DirtyPhoto', ['dirtyProductId' => $dirtyProduct["id"]])->fetchAll();
                        $position = 0;
                        $this->report('processImage', 'array image: ' );
                        $imgs =  $rawSku['item_images']['full'];

                        foreach ($imgs as $img) {
                            if (empty(trim($img))) {
                                continue;
                            }
                            foreach ($dirtyPhotos as $exImg) {
                                if ($exImg['url'] == $img) continue 2;
                            }
                            $position++;
                            \Monkey::app()->dbAdapter->insert('DirtyPhoto', [
                                'dirtyProductId' => $dirtyProduct["id"],
                                'url' => $img,
                                'location' => 'url',
                                'position' => $position,
                                'worked' => 0,
                                'shopId' => $this->getShop()->id
                            ]);
                        }

                        \Monkey::app()->repoFactory->commit();
                    }
                } catch (\Throwable $e) {
                    \Monkey::app()->repoFactory->rollback();
                    $this->error('processFile', 'Error reading Product: ' . json_encode($rawSku), $e);
                    continue;
                }

                try {

                    $this->debug('processFile', 'Going with Sku');
                    foreach($rawSku['variants'] as $rawDirtySku) {
                        $dirtySku = [];
                        $dirtySku['dirtyProductId'] = $dirtyProduct['id'];
                        $dirtySku['shopId'] = $this->getShop()->id;
                        $dirtySku['size'] = $rawDirtySku['size'];
                        $dirtySku['extSkuId'] = $rawSku['product_reference'].'_'.$rawDirtySku['size'];
                        $dirtySku['qty'] = $rawDirtySku['quantity'];
                        $dirtySku['price'] = $rawSku['retail_price'];
                        $dirtySku['value'] = $rawSku['price'];
                        $dirtySku['barcode']=$rawDirtySku['barcodes'][0];
                        $dirtySku['storeHouseId']=1;


                        $dirtySku['checksum'] = md5(json_encode($dirtySku));
                        if (isset($skusChecksums[$dirtySku['checksum']])) {
                            $dirtySku['id'] = $skusChecksums[$dirtySku['checksum']];
                            $this->debug('processFile','Sku checksum Exist, save it',$dirtySku);
                        } else {
                            $dirtySku['changed'] = 1;

                            $existingSku = \Monkey::app()->dbAdapter->select('DirtySku',[
                                'shopId' => $this->getShop()->id,
                                'dirtyProductId' => $dirtyProduct['id'],
                                'extSkuId' => $dirtySku['extSkuId']
                            ])->fetchAll();

                            if (count($existingSku) == 0) {
                                $dirtySku['id'] = \Monkey::app()->dbAdapter->insert('DirtySku',$dirtySku);
                                $this->debug('processFile','Sku don\'t Exist, insert',$dirtySku);

                            } elseif (count($existingSku) == 1) {
                                \Monkey::app()->dbAdapter->update('DirtySku',$dirtySku,['id' => $existingSku[0]['id']]);
                                $dirtySku['id'] = $existingSku[0]['id'];
                                /* @var CDirtySkuHasStoreHouse $FindDirtyHasStoreHouse  **/
                                $findDirtyHasStoreHouse=\Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->findOneBy([
                                    'shopId'=> $this->getShop()->id,
                                    'size'=>$dirtySku['size'],
                                    'dirtySkuId'=>$id,
                                    'dirtyProductId' =>$dirtyProduct['id'],
                                    'storeHouseId'=> 1
                                ]);
                                if(!$findDirtyHasStoreHouse){
                                    /* @var CDirtySkuHasStoreHouse $insertDirtySkuHasStoreHouse  **/
                                    $insertDirtySkuHasStoreHouse=\Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->getEmptyEntity();
                                    $insertDirtySkuHasStoreHouse->shopId=$this->getShop()->id;
                                    $insertDirtySkuHasStoreHouse->dirtySkuId= $dirtySku['id'];
                                    $insertDirtySkuHasStoreHouse->storeHouseId= 1;
                                    $insertDirtySkuHasStoreHouse->size=$dirtySku['size'];
                                    $insertDirtySkuHasStoreHouse->dirtyProductId=$dirtyProduct['id'];
                                    $insertDirtySkuHasStoreHouse->productVariantId=$dirtyProduct['productVariantId'];
                                    $insertDirtySkuHasStoreHouse->qty=$dirtySku['qty'];
                                    $insertDirtySkuHasStoreHouse->productSizeId= $existingSku[0]['productSizeId'];
                                    $insertDirtySkuHasStoreHouse->insert();
                                }else{
                                    $findDirtyHasStoreHouse->dirtyProductId=$dirtyProduct['id'];
                                    $findDirtyHasStoreHouse->productId=$dirtyProduct['productId'];
                                    $findDirtyHasStoreHouse->productVariantId=$dirtyProduct['productVariantId'];
                                    $findDirtyHasStoreHouse->productSizeId=$existingSku[0]['productSizeId'];
                                    $findDirtyHasStoreHouse->qty=$sku['qty'];
                                    $findDirtyHasStoreHouse->update();
                                }
                                $this->debug('processFile','Sku Exist, update',$dirtySku);

                            } else throw new BambooException('More than 1 sku found to update');
                        }

                        $seenSkus[] = $dirtySku['id'];
                    }
                } catch (\Throwable $e) {
                    $this->error('processFile', 'Error reading Sku: ' . json_encode($rawSku), $e);
                }
            }
        }

        $this->findZeroSkus($seenSkus);
    }
}