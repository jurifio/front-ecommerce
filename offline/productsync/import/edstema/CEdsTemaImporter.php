<?php

namespace bamboo\offline\productsync\import\edstema;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooFileException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\domain\entities\CDirtySkuHasStoreHouse;
use bamboo\core\exceptions\RedPandaException;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;

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
class CEdsTemaImporter extends ABluesealProductImporter
{

    /**
     *
     */
    public function fetchLocalFiles()
    {
        /** PRODUCTS */
        $files = glob($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/cartechini/PRODUCTS_*.CSV');
        if(count($files) == 0) throw new BambooException('File not found');
        $goodOne = $files[count($files) - 1];

        $size = filesize($goodOne);
        while ($size != filesize($goodOne)) {
            sleep(1);
            $size = filesize($goodOne);
        }

        $mainFilename = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/PRODUCTS_' . time() . '.csv';
        copy($goodOne, $mainFilename);
        $this->mainFilenames[] = $mainFilename;
        for ($i = 0; $i < (count($files) - 1); $i++) {
            unlink($files[$i]);
        }
        /** SKUS */
        $files = glob($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/cartechini/SKUS_*.CSV');
        $goodOne = $files[count($files) - 1];
        $size = filesize($goodOne);
        while ($size != filesize($goodOne)) {
            sleep(1);
            $size = filesize($goodOne);
        }
        $mainFilename = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/SKUS_' . time() . '.csv';
        copy($goodOne, $mainFilename);
        $this->mainFilenames[] = $mainFilename;

        for ($i = 0; $i < (count($files) - 1); $i++) {
            unlink($files[$i]);
        }

        /** ATTRIBUTI */
        $files = glob($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/cartechini/ATTRIBUTI_*.CSV');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    public function readFile($file)
    {
        return true;
    }

    /**
     * @param $file
     * @throws BambooException
     * @throws BambooFileException
     * @throws BambooOutOfBoundException
     */
    public function processFile($file)
    {
        $baseName = pathinfo($file, PATHINFO_BASENAME);

        try {
            $dirtyFile = $this->app->dbAdapter->select('DirtyFile', ['name' => $baseName, 'shopId' => $this->getShop()->id])->fetchAll()[0];
        } catch (\Throwable $e) {
        }

        $fileName = pathinfo($file, PATHINFO_FILENAME);
        if (is_readable($file)) $file = fopen($file, 'r');
        try {
            switch (explode('_', $fileName)[0]) {
                case 'PRODUCTS':
                    $this->report('processFile', 'going to readProduct on ' . $baseName);
                    $return = $this->readMain($file);
                    break;
                case 'SKUS':
                    $this->report('processFile', 'going to readListini on ' . $baseName);
                    $return = $this->readSkus($file);
                    break;
                default:
                    throw new BambooOutOfBoundException('Unknown file %s', [$file]);
            }
        } catch (\Throwable $e) {
            fclose($file);
            throw $e;
        }
        try {
            $this->app->dbAdapter->update('DirtyFile', ['worked' => 1, 'elaborationDate' => date("Y-m-d H:i:s", time())], ['id' => $dirtyFile['id'], 'shopId' => $this->getShop()->id]);
        } catch (\Throwable $e) {
        }


        return $return;
    }

    public function readMain($file)
    {
        $checksums = $this->app->dbAdapter->query("SELECT ifnull(checksum,'') FROM DirtyProduct WHERE shopId = ?", [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
        $checksums = array_flip($checksums);

        /** Isolate values and find good ones */
        $valuesMapping = $this->config->fetch('mapping', 'product');
        $extendMapping = $this->config->fetch('mapping', 'extend');
        $columnNumbers = $this->config->fetch('files', 'main')['columns'];
        $separator = $this->config->fetch('miscellaneous', 'separator');

        $keysMapping = $this->config->fetch('files', 'main')['extKeys'];

        $supplierBlacklist = $this->config->fetch('miscellaneous', 'suppliersBlacklist');
        $this->debug('readMain', 'Supplier Blacklist read', $supplierBlacklist);

        //read main
        while (($values = fgetcsv($file, 0, $separator, '|')) !== false) {
            try {
                if ($values[0][0] == '"') {
                    $values[0] = substr($values[0], 1);
                }

                /** Count columns */
                if (count($values) != $columnNumbers) {
                    $this->error('readMain', 'Columns dosn\'t match with specifics');
                    continue;
                }

                $product = $this->mapValues($values, $valuesMapping);
                $product['itemno'] = explode('_', $product['itemno'])[0];
                $product['text'] = implode($separator, $values);
                $product['checksum'] = md5($product['text']);
                $this->debug('readMain', 'Reading Product ', $product);
                if (isset($checksums[$product['checksum']])) continue;
                $this->debug('readMain', 'checksum do not exists', $product);
                if (in_array($product['supplier'], $supplierBlacklist)) {
                    $this->debug('readMain', 'Skipped product with supplier: ' . $product['supplier']);
                    continue;
                } else {
                    unset($product['supplier']);
                }

                $match = $this->mapKeys($product, $keysMapping);
                $match['shopId'] = $this->shop->id;

                /** find existing product */
                $res = $this->app->dbAdapter->select('DirtyProduct', $match)->fetchAll();
                if (count($res) == 0) {
                    $this->debug('readMain', 'Inserting new Product', $product);
                    /** è un nuovo prodotto lo scrivo */
                    $product['shopId'] = $this->shop->id;
                    $product['dirtyStatus'] = 'F';
                    $res = $this->app->dbAdapter->insert('DirtyProduct', $product);
                    $productExtend = $this->mapValues($values, $extendMapping);
                    $productExtend['dirtyProductId'] = $res;
                    $productExtend['shopId'] = $this->getShop()->id;

                    $res = $this->app->dbAdapter->insert('DirtyProductExtend', $productExtend);


                } elseif (count($res) == 1) {
                    $this->debug('readMain', 'Updating a product ', [$product,$res]);
                    /** update existing product if changed */
                    //exist.. what to do? uhm... update?
                    $this->app->dbAdapter->update('DirtyProduct', array_diff($product, $match), ['id' => $res[0]['id'],
                        'shopId' => $this->getShop()->id]);

                    $productExtend = $this->mapValues($values, $extendMapping);
                    $dirtyProductExtend = $this->app->dbAdapter->select('DirtyProductExtend',
                        ['dirtyProductId' => $res[0]['id'],
                            'shopId' => $this->getShop()->id])->fetchAll();
                    $this->debug('readMain', 'Loocked for Dirty Product Extend',
                        [['dirtyProductId' => $res[0]['id'],'shopId' => $this->getShop()->id],
                            $dirtyProductExtend]);
                    if(count($dirtyProductExtend) == 0) {
                        $this->app->dbAdapter->insert('DirtyProductExtend',
                            $productExtend + [   'dirtyProductId' => $res[0]['id'],
                                'shopId' => $this->getShop()->id]);
                        $this->debug('readMain','inserting Dirty',$productExtend);
                    } else {
                        $this->app->dbAdapter->update('DirtyProductExtend',
                            $productExtend, ['dirtyProductId' => $res[0]['id'],
                                'shopId' => $this->getShop()->id]);
                        $this->debug('readMain','updating Dirty',$productExtend);
                    }



                } else {
                    //error
                    //log
                    continue;
                }
            } catch (\Throwable $e) {
                $this->error('readMain', 'Error while reading Product', $values);
                throw $e;
            }
        }
    }

    public function readSkus($file)
    {

        $checksumsRaw = $this->app->dbAdapter->query('SELECT id,checksum FROM DirtySku WHERE shopId = ?', [$this->getShop()->id])->fetchAll();
        $checksums = [];
        foreach ($checksumsRaw as $row) {
            $checksums[$row['checksum']] = $row['id'];
        }
        unset($checksumsRaw);

        /** Isolate values and find good ones */
        $valuesMapping = $this->config->fetch('mapping', 'skus');
        $columnNumbers = $this->config->fetch('files', 'skus')['columns'];
        $separator = $this->config->fetch('miscellaneous', 'separator');

        $keysMapping = $this->config->fetch('files', 'skus')['extKeys'];

        $thisShopExtId = $this->config->fetch('miscellaneous', 'thisShopExtIds');

        //read SKUS ------------------
        $shopOk = 0;
        $shopKo = 0;

        $seenSkus = [];
        fgets($file);
        while (($values = fgetcsv($file, 0, $separator, '|')) !== false) {
            $this->debug('Read Sku','Cycle skus', $values);
            try {
                if (count($values) != 13) {
                    $this->warning('Columns Count', count($values) . ' columns find, expecting ' . $columnNumbers, $values);
                    continue;
                }



                $sku = $this->mapValues($values, $valuesMapping);
                $sku['text'] = implode($separator, $values);
                $sku['checksum'] = md5($sku['text']);

                $this->debug('Read Sku','Reading Sku',$sku);

                if (isset($checksums[$sku['checksum']])) {
                    $seenSkus[] = $checksums[$sku['checksum']];
                    continue;
                }
                $this->debug('Read Sku','Checksum not found',$sku);

                $sku['shopId'] = $this->getShop()->id;

                $match = $this->mapKeys($sku, $keysMapping);
                $match['shopId'] = $this->getShop()->id;



                $sku['price'] = str_replace('.', '', $sku['price']);
                $sku['salePrice'] = str_replace('.', '', $sku['salePrice']);
                $sku['value'] = str_replace('.', '', $sku['value']);
                $sku['price'] = str_replace(',', '.', $sku['price']);
                $sku['salePrice'] = str_replace(',', '.', $sku['salePrice']);
                $sku['value'] = str_replace(',', '.', $sku['value']);
                $sku['storeHouseId'] = str_replace('0','',$values[8]);


                $dirtyProduct = $this->app->dbAdapter->select('DirtyProduct', $match)->fetchAll();
                if (count($dirtyProduct) != 1) {
                    $this->warning('readSkus', 'Product not found for Sku', [$match,$sku]);
                    continue;
                }

                $this->debug('Read Sku','Product Found for Sku',$dirtyProduct);

                $dirtyProduct = $dirtyProduct[0];

                $res = $this->app->dbAdapter->select('DirtySku', ['dirtyProductId' => $dirtyProduct['id'], 'size' => $sku['size']])->fetchAll();
                /** Update */
                if (count($res) == 1) {
                    $sku['changed'] = 1;
                    $id = $res[0]['id'];
                    /* @var $FindDirtyHasStoreHouse CDirtySkuHasStoreHouse **/
                    $findDirtyHasStoreHouse=\Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->findOneBy([
                        'shopId'=> $this->getShop()->id,
                        'size'=>$sku['size'],
                        'dirtySkuId'=>$id,
                        'dirtyProductId' =>$dirtyProduct['id'],
                        'storeHouseId'=> $sku['storeHouseId']
                    ]);
                    if(count($findDirtyHasStoreHouse)!=1){
                        /* @var $insertDirtySkuHasStoreHouse CDirtySkuHasStoreHouse **/
                        $insertDirtySkuHasStoreHouse=\Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->getEmptyEntity();
                        $insertDirtySkuHasStoreHouse->shopId=$this->getShop()->id;
                        $insertDirtySkuHasStoreHouse->dirtySkuId=$id;
                        $insertDirtySkuHasStoreHouse->storeHouseId= str_replace('0','',$values[8]);
                        $insertDirtySkuHasStoreHouse->size=$sku['size'];
                        $insertDirtySkuHasStoreHouse->dirtyProduct=$dirtyProduct['id'];
                        $insertDirtySkuHasStoreHouse->qty=$values[3];
                        $insertDirtySkuHasStoreHouse->barcode=$values[11];
                        $insertDirtySkuHasStoreHouse->productSizeId= $res[0]['productSizeId'];
                        $insertDirtySkuHasStoreHouse->insert();
                    }else{
                        $findDirtyHasStoreHouse->qty=$values[3];
                        $findDirtyHasStoreHouse->barcode=$values[11];
                        $findDirtyHasStoreHouse->update();
                    }

                    $this->debug('Read Sku','Updating Sku',$sku);
                    $dirtySkuUpdate=\Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['id'=>$id]);
                    $dirtySkuUpdate->value=$sku['price'];
                    $dirtySkuUpdate->salePrice=$sku['salePrice'];
                    $dirtySkuUpdate->price=$sku['price'];
                    $dirtySkuUpdate->storeHouseId=$sku['storeHouseId'];
                    $dirtySkuUpdate->text=$sku['text'];
                    $dirtySkuUpdate->checksum=$sku['checksum'];
                    $dirtySkuUpdate->update();
                    /*$sku['dirtyProductId'] = $dirtyProduct['id'];
                    $sku['storeHouseId'] = str_replace('0','',$values[8]);*/
                    $this->debug('Read Sku','Updating Sku',$sku);
                    /*$res = $this->app->dbAdapter->update('DirtySku', array_diff($sku, $match), ["id" => $id]);*/
                    $seenSkus[] = $id;
                    //check ok
                    /** Insert New */
                } else if (count($res) == 0) {
                    unset($sku['extId']);
                    unset($sku['var']);
                    $sku['dirtyProductId'] = $dirtyProduct['id'];
                    $sku['shopId'] = $this->shop->id;
                    $sku['storeHouseId'] = str_replace('0','',$values[8]);
                    $sku['changed'] = 1;
                    $new = $this->app->dbAdapter->insert('DirtySku', $sku);
                    $seenSkus[] = $new;
                    $findDirtyHasStoreHouse=\Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->findOneBy([
                        'shopId'=> $this->getShop()->id,
                        'size'=>$sku['size'],
                        'dirtySkuId'=>$id,
                        'dirtyProductId' =>$dirtyProduct['id'],
                        'storeHouseId'=> $sku['storeHouseId']
                    ]);
                    /* @var $FindDirtyHasStoreHouse CDirtySkuHasStoreHouse **/
                    if(count($findDirtyHasStoreHouse)!=1){
                        /* @var $insertDirtySkuHasStoreHouse CDirtySkuHasStoreHouse **/
                        $insertDirtySkuHasStoreHouse=\Monkey::app()->repoFactory->create('DirtySkuHasStoreHouse')->getEmptyEntity();
                        $insertDirtySkuHasStoreHouse->shopId=$this->getShop()->id;
                        $insertDirtySkuHasStoreHouse->dirtySkuId=$id;
                        $insertDirtySkuHasStoreHouse->storeHouseId= str_replace('0','',$values[8]);
                        $insertDirtySkuHasStoreHouse->size=$sku['size'];
                        $insertDirtySkuHasStoreHouse->dirtyProduct=$dirtyProduct['id'];
                        $insertDirtySkuHasStoreHouse->qty=$values[3];
                        $insertDirtySkuHasStoreHouse->barcode=$values[11];
                        $insertDirtySkuHasStoreHouse->productSizeId= $res[0]['productSizeId'];
                        $insertDirtySkuHasStoreHouse->insert();
                    }else{
                        $findDirtyHasStoreHouse->qty=$values[3];
                        $findDirtyHasStoreHouse->barcode=$values[11];
                        $findDirtyHasStoreHouse->update();
                    }


                } else {
                    //error
                    continue;
                }

            } catch (\Throwable $e) {
                $this->error('Read Sku', 'Error while reading Sku', $e);
                if (isset($sku)) {
                    $this->error('Read Sku', 'SkuError', $sku);
                }
            }
        }
        $this->findZeroSkus($seenSkus);
    }

    /**
     * Retrive assoc map values by matching an assoc map array to a scalar values array
     * @param array $values
     * @param array $map
     * @return array
     */
    protected function mapValues(array $values, array $map)
    {
        $newValues = [];
        foreach ($map as $key => $val) {
            $newValues[$key] = trim(utf8_encode($values[$val]));
        }

        return $newValues;
    }
}
