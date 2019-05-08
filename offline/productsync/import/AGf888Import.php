<?php

namespace bamboo\ecommerce\offline\productsync\import;

use bamboo\core\base\CFTPClient;
use bamboo\core\exceptions\RedPandaFTPClientException;
use bamboo\core\utils\amazonPhotoManager\ImageEditor;
use bamboo\domain\entities\CProduct;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;

/**
 * Class CGf888Import
 * @package bamboo\import\productsync\dellamartira
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
abstract class AGf888Import extends ABluesealProductImporter
{
    protected $config;
    protected $log;

    protected $skusF;
    protected $skus;
    protected $mainF;
    protected $main;
    protected $sizesF;
    protected $sizes;

    protected $shop;
    protected $err = false;

    protected $mainRows = 0;
    protected $skusRows = 0;

    protected $sizesTable = [];

    protected $seenSkus;

    public function processFile($file)
    {
    }

    public function readFile($file)
    {
    }

    /**
     * @param $args
     * @return bool
     */
    public function fetchAltFiles($args)
    {
        $args = explode(',', $args);
        $this->main = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->owner . '/import/' . $args[0];
        $this->skus = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->owner . '/import/' . $args[1];

        return true;
    }

    public function insertPhotos($id, array $photos)
    {
        $i = 1;
        foreach ($photos as $photo) {
            $this->app->dbAdapter->insert('DirtyPhoto', ['dirtyProductId' => $id, 'url' => $photo, 'position' => $i, 'worked' => 0, 'shopId' => $this->getShop()->id]);
            $i++;
        }
    }

    /**
     *
     */
    public function fetchPhotos()
    {
        $ftpDestination = new CFTPClient($this->app, $this->config->fetch('miscellaneous', 'destFTPClient'));
        if (!$ftpDestination->changeDir('/shootImport/incoming/' . $this->getShop()->name)) {
            throw new RedPandaFTPClientException('Could not change dir');
        };
        $imager = new ImageEditor();
        $widht = 500;
        $dummyFolder = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'dummyFolder') . '/';
        $photosLocation = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->config->fetch('miscellaneous', 'photos') . '/';
        foreach (glob($photosLocation . '*') as $val) {
            try {
                $exist = $this->app->dbAdapter->selectCount('DirtyPhoto', ['url' => $val, 'shopId' => $this->getShop()->id, 'worked' => 1]);
                if ($exist > 0) continue;

                $fileName = pathinfo($val);

                $dirtyPhoto = $this->app->dbAdapter->select('DirtyPhoto', ['url' => $val, 'shopId' => $this->getShop()->id, 'worked' => 0])->fetchAll();
                if (count($dirtyPhoto) == 0) {
                    $parts = explode('-', $fileName['filename']);
                    if (count($parts) < 3) {
                        $this->warning('fetchPhotos', 'file with less than 3 "-" parts', $parts);
                        continue;
                    }
                    $cpf = [];
                    if ($parts[count($parts) - 1][0] != 0 && is_numeric($parts[count($parts) - 1]) && count($parts) > 3 && $parts[count($parts) - 1] < 10) {
                        for ($o = 1; $o < (count($parts) - 2); $o++) {
                            $cpf[] = $parts[$o];
                        }
                        $cpf = implode('-', $cpf);
                        $var = $parts[count($parts) - 2];
                    } else {
                        for ($o = 1; $o < (count($parts) - 1); $o++) {
                            $cpf[] = $parts[$o];
                        }
                        $cpf = implode('-', $cpf);
                        $var = $parts[count($parts) - 1];
                    }
                    $prod = $this->app->dbAdapter->query("SELECT dp.*,p.dummyPicture
													FROM DirtyProduct dp, Product p
													WHERE
													shopId = ? AND
													dp.productId = p.id AND
											        dp.productVariantId = p.productVariantId AND
												    dp.itemno LIKE ? AND var LIKE ?", [$this->getShop()->id, $cpf, $var])->fetchAll();
                    if (count($prod) == 1) {
                        $prod = $prod[0];
                        $photoId = $this->app->dbAdapter->insert('DirtyPhoto',
                            ["dirtyProductId" => $prod['id'],
                                "url" => $val,
                                "location" => 'path',
                                "worked" => 0,
                                "shopId" => $this->getShop()->id]);
                    } elseif (count($prod) > 1) {
                        $this->report('PhotoDownload', 'Found more than one product for ' . $val);
                        continue;
                    } else {
                        continue;
                    }

                } else {
                    $dirtyPhoto = $dirtyPhoto[0];
                    $prod = $this->app->dbAdapter->query("SELECT dp.*,p.dummyPicture
													FROM DirtyProduct dp, Product p
													WHERE
													shopId = ? AND
													dp.productId = p.id AND
											        dp.productVariantId = p.productVariantId AND
												    dp.id = ?", [$this->getShop()->id, $dirtyPhoto['dirtyProductId']])->fetchAll();
                    if (count($prod) == 1) {
                        $prod = $prod[0];
                        $photoId = $dirtyPhoto['id'];
                    } else {
                        $this->report('PhotoDownload', 'Found more than one product for ' . $val);
                        continue;
                    }
                }


                /** @var CProduct $product */
                $product = \Monkey::app()->repoFactory->create('Product')->findOneBy(['id'=>$prod['productId'], 'productVariantId'=>$prod['productVariantId']]);
                $name = $product->getAztecCode() . '__' . $fileName['filename'] . '.' . $fileName['extension'];
                //$name = $prod['productId'] . '-' . $prod['productVariantId'] . '__' . $fileName['filename'] . '.' . $fileName['extension'];
                if ($ftpDestination->put($val, $name, true)) {
                    $this->report('Send Photo', 'Photo ' . $name . ' sent to ftp');
                    $this->app->dbAdapter->update('DirtyPhoto', ['worked' => 1], ['id' => $photoId]);
                } else {
                    $this->error('Send Photo', 'Error sending photo ' . $name . ' sent to ftp');
                    continue;
                }
                if ($prod['dummyPicture'] == 'bs-dummy-16-9.png') {
                    $imager->load($val);
                    $imager->resizeToWidth($widht);
                    $dummyName = rand(0, 9999999999) . '.' . $fileName['extension'];
                    $imager->save($dummyFolder . '/' . $dummyName);
                    $this->app->dbAdapter->update('Product', ['dummyPicture' => $dummyName], ['id' => $prod['productId'], 'productVariantId' => $prod['productVariantId']]);
                    $this->report('PhotoDownload', 'Set dummyPicture: ' . $dummyName);
                }
            } catch (\Throwable $e) {
                $this->error('Fetch Photo', 'Photo ' . $val . ' thorws error', $e);
            }
        }
    }

    public function sendPhotos()
    {
        //return true
    }

    protected function importDataIntoDirty($args = null)
    {
        $this->report('Runner', 'fetchFiles launch');
        // get files containing data we need to import
        $fetch = $this->fetchFiles();
        $this->report('Runner', 'fetchFiles end');

        try {
            if ($fetch) {
                $this->report("Read Files", "Leggo i files");
                $this->readFiles();

                $this->report("Read Sizes", "Leggo le taglie e faccio un anagrafice");
                $this->readSizes();

                $this->report("Read Main", "Leggo il file Main cercando Prodotti");
                $this->readMain();

                $this->report("Read Sku", "Leggo il file degli Sku");
                $this->readSku();

                $this->report("Runner", "File import things done");

                $this->report("Save Files", "salvo i file e li cancello");
                $this->saveFiles();
            } else $this->warning("Read Files", "File not Found");
        } catch (\Throwable $e) {
            $this->error("Runner Product import", "Failed in working Products/skus", $e);
        }
    }

    /**
     * @return bool
     *
     */
    public function fetchFiles()
    {
        /** PRODUCTS */
        $path = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->config->fetch('files', 'main')['location'];
        $files = glob($path);
        $this->report('fetchFiles', 'path: ' . $path, $files);
        if (!isset($files[0])) return false;
        $products = $files[0];
        $time = filemtime($products);
        foreach ($files as $file) {
            if (filemtime($file) > $time) {
                $products = $file;
                $time = filemtime($file);
            }
        }
        $this->report("fetchFiles", "Files usato = " . $products, null);
        $size = filesize($products);
        while ($size != filesize($products)) {
            sleep(1);
            $size = filesize($products);
        }
        $this->main = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->owner . '/import/main' . rand(0, 1000) . '.csv';
        copy($products, $this->main);

        foreach ($files as $file) {
            try {
                unlink($file);
            } catch (\Throwable $e) {
                $this->warning('FetchFile', 'Could not unlink file ' . $file, $e);
            }
        }

        /** skus */
        $files = glob($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->config->fetch('files', 'skus')['location']);
        if (!isset($files[0])) return false;
        $skus = $files[0];
        $time = filemtime($skus);
        foreach ($files as $file) {
            if (filemtime($file) > $time) {
                $skus = $file;
                $time = filemtime($file);
            }
        }
        $this->report("fetchFiles", "Files usato = " . $skus, null);
        $size = filesize($skus);
        while ($size != filesize($skus)) {
            sleep(1);
            $size = filesize($skus);
        }
        $this->skus = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->owner . '/import/skus' . rand(0, 1000) . '.csv';
        copy($skus, $this->skus);

        foreach ($files as $file) {
            try {
                unlink($file);
            } catch (\Throwable $e) {
                $this->warning('FetchFile', 'Could not unlink file ' . $file, $e);
            }
        }

        /** sizes */
        $files = glob($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->config->fetch('files', 'sizes')['location']);
        if (!isset($files[0])) return false;
        $sizes = $files[0];
        $time = filemtime($sizes);
        foreach ($files as $file) {
            if (filemtime($file) > $time) {
                $sizes = $file;
                $time = filemtime($file);
            }
        }
        $this->report("fetchFiles", "Files usato = " . $sizes, null);
        $size = filesize($sizes);
        while ($size != filesize($sizes)) {
            sleep(1);
            $size = filesize($sizes);
        }
        $this->sizes = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->owner . '/import/sizes' . rand(0, 1000) . '.csv';
        copy($sizes, $this->sizes);

        foreach ($files as $file) {
            try {
                unlink($file);
            } catch (\Throwable $e) {
                $this->warning('FetchFile', 'Could not unlink file ' . $file, $e);
            }
        }

        return true;
    }

    public function readFiles()
    {
        $this->mainF = fopen($this->main, 'r');
        $this->skusF = fopen($this->skus, 'r');
        $this->sizesF = fopen($this->sizes, 'r');
    }

    public function readSizes()
    {
        //read main
        $whole = [];
        $sizes = $this->sizesF;
        $sizesMapping = $this->config->fetch('mapping', 'sizes');
        while (($values = fgetcsv($sizes, 0, $this->config->fetch('miscellaneous', 'separator'), '"')) !== false) {
            try {
                if (count($values) != $this->config->fetch('files', 'sizes')['columns']) continue;
                $sizesValues = [];
                foreach ($sizesMapping as $key => $val) {
                    $sizesValues[$key] = trim(utf8_encode($values[$val]));
                }

                $this->sizesTable[$sizesValues['extSizeId']] = $sizesValues['sizeName'];

            } catch (\Throwable $e) {
            }
        }
    }

    public function readMain()
    {
        //read main
        $main = $this->mainF;
        $iterator = 0;
        $newLines = 0;
        $mainMapping = $this->config->fetch('mapping', 'main');
        while (($values = fgetcsv($main, 0, $this->config->fetch('miscellaneous', 'separator'), '"')) !== false) {
            try {
                $iterator++;
                if ($iterator % 200 == 0) $this->report('Reading Products', 'Cycling Products: ' . $iterator);
                if (count($values) != $this->config->fetch('files', 'main')['columns']) continue;

                $mainValues = [];
                foreach ($mainMapping as $key => $val) {
                    $mainValues[$key] = trim($values[$val]);
                }
                $line = implode($this->config->fetch('miscellaneous', 'separator'), $mainValues);
                $md5 = md5($line);
                $mainValues['checksum'] = $md5;
                $exist = $this->app->dbAdapter->selectCount("DirtyProduct", ['checksum' => $md5, 'shopId' => $this->getShop()->id]);

                /** Already written */
                if ($exist == 1) {
                    continue;
                }
                /** Insert/update */
                if ($exist == 0) {
                    $newLines++;
                    /** RICERCA PER VALORI CHIAVE ESTERNO */
                    $identify['extId'] = $mainValues['extId'];
                    $identify['shopId'] = $this->getShop()->id;
                    $mainValues['shopId'] = $this->getShop()->id;
                    $mainValues['price'] = (float)str_replace(',', '.', $mainValues['price']);
                    $mainValues['value'] = (float)str_replace(',', '.', $mainValues['value']);
                    $mainValues['salePrice'] = (float)str_replace(',', '.', $mainValues['salePrice']);

                    $res = $this->app->dbAdapter->select('DirtyProduct', $identify)->fetchAll();
                    /** find if exist the same product and update entities */
                    if (count($res) == 1) {
                        $update = $mainValues;
                        $compress['description'] = $update['description'];
                        $compress['season'] = $update['season'];
                        $compress['category2'] = $update['category2'];
                        $compress['category1'] = $update['category1'];
                        $compress['description'] = $update['description'];
                        $compress['color'] = $update['color'];
                        $compress['name'] = $update['name'];
                        $compress['fabric'] = $update['fabric'];
                        $compress['detail'] = $update['detail'];
                        $compress['category3'] = $update['category3'];
                        $compress['target'] = $update['target'];
                        $update['text'] = implode(' - ', $compress);

                        $res = $this->app->dbAdapter->update('DirtyProduct', array_diff($update, $identify, $compress), $identify);
                        //log
                        continue;
                    } elseif (count($res) == 0) {
                        /** è un nuovo prodotto lo scrivo */
                        $insert = $mainValues;
                        $compress['description'] = $insert['description'];
                        $compress['season'] = $insert['season'];
                        $compress['category2'] = $insert['category2'];
                        $compress['category1'] = $insert['category1'];
                        $compress['description'] = $insert['description'];
                        $compress['name'] = $insert['name'];
                        $compress['color'] = $insert['color'];
                        $compress['fabric'] = $insert['fabric'];
                        $compress['detail'] = $insert['detail'];
                        $compress['category3'] = $insert['category3'];
                        $compress['target'] = $insert['target'];
                        $insert['text'] = implode(' - ', $compress);
                        $insert['dirtyStatus'] = 'F';
                        $res = $this->app->dbAdapter->insert('DirtyProduct', array_diff($insert, $compress));
                        if ($res < 0) {
                            continue;
                        }
                        $this->fillProduct($res, $mainValues);
                    } else {
                        //error
                        //log
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                $this->error('Error reading Main', 'read Context', $e);
            }
        }
        $this->report('Read Main done', 'read line: ' . $newLines, null);

        return $iterator;
    }

    public function fillProduct($id, $values)
    {
        //$fullMapping = $this->config->fetch('mapping', 'full');
        //$fullValues = [];
        //foreach ($fullMapping as $key => $val) {
        //	$fullValues[$key] = trim($values[$val]);
        //}
        $this->app->dbAdapter->update('DirtyProduct', ['brand' => $values['brand']], ['id' => $id]);
        $this->app->dbAdapter->insert('DirtyProductExtend', ['dirtyProductId' => $id,
            'shopId' => $this->getShop()->id,
            'name' => empty($values['name']) ? '*' : $values['name'],
            'description' => $values['description'],
            'season' => $values['season'],
            'audience' => $values['target'],
            'cat1' => $values['category1'],
            'cat2' => $values['category2'],
            'cat3' => $values['category3'],
            'generalColor' => $values['color']
        ]);
        $this->insertDetails($id, [$values['fabric'], $values['detail']]);
        //$this->insertPhotos($id,explode('|',$values['photos']));
    }

    public function insertDetails($id, array $details)
    {
        foreach ($details as $detail) {
            $this->app->dbAdapter->insert('DirtyDetail', ['dirtyProductId' => $id, 'content' => $detail]);
        }
    }

    public function readSku()
    {
        $columnCount = $this->config->fetch('files', 'skus')['columns'];
        $separator = $this->config->fetch('miscellaneous', 'separator');
        $keys = $this->config->fetch('files', 'main')['extKeys'];

        //read SKUS ------------------
        $skus = $this->skusF;
        $iterator = 0;
        $changedSku = 0;

        $checksums = [];
        $rawChecksums = $this->app->dbAdapter->query("SELECT checksum, id FROM DirtySku WHERE shopId = ?", [$this->getShop()->id])->fetchAll();
        foreach ($rawChecksums as $checksum) $checksums[$checksum['checksum']] = $checksum['id'];

        $this->report('Reading Skus', 'Read ' . count($checksums) . ' checksums');

        while (($values = fgetcsv($skus, 0, $separator, '"')) !== false) {
            $iterator++;
            if ($iterator % 1000 == 0) $this->report('Reading Skus', 'Cycling skus: ' . $iterator);
            try {
                if (count($values) != $columnCount) {
                    $this->error('SkuCycle', 'Wrong column count', $values);
                    continue;
                }
                $sku = [];
                $sku['text'] = implode($separator,$values);
                $sku['checksum'] = md5($sku['text']);

                if (isset($checksums[$sku['checksum']])) {
                    $this->seenSkus[] = $checksums[$sku['checksum']];
                    $this->debug('SkuCycle', 'Found checksum for: ' . $checksums[$sku['checksum']], $values);
                } else {
                    /** Isolate values and find good ones */
                    $mapping = $this->config->fetch('mapping', 'skus');
                    foreach ($mapping as $key => $val) {
                        $sku[$key] = trim($values[$val]);
                    }

                    /** Insert or Update */
                    $changedSku++;
                    $sku['shopId'] = $this->getShop()->id;

                    /** RICERCA PER CODICE ESTERNO */
                    $sku['size'] = $this->sizesTable[$sku['extSizeId']];

                    /** find keys */
                    $matchProduct = [];
                    foreach ($keys as $key) {
                        $matchProduct[$key] = $sku[$key];
                    }
                    $matchProduct['shopId'] = $this->getShop()->id;

                    /** Find Product  */
                    $dirtyProduct = $this->app->dbAdapter->select('DirtyProduct', $matchProduct)->fetchAll();
                    if (is_array($dirtyProduct) && count($dirtyProduct) !== 1) {
                        //error - PRODUCT not found? too bad
                        $this->error('Reading Skus', 'Dirty Product not found while looking at sku', $values);
                        continue;
                    }
                    $dirtyProduct = $dirtyProduct[0];
                    /** Adjust prices */
                    $res = $this->app->dbAdapter->select('DirtySku', ['dirtyProductId' => $dirtyProduct['id'], 'extSizeId' => $sku['extSizeId']])->fetchAll();

                    /** Update */
                    if (is_array($res) && count($res) == 1) {
                        $sku['changed'] = 1;
                        $id = $res[0]['id'];
                        $res = $this->app->dbAdapter->update('DirtySku', array_diff($sku, $matchProduct), ["id" => $id]);
                        $this->debug('SkuCycle', 'Updated Sku: ' . $id, array_diff($sku, $matchProduct));
                        $this->seenSkus[] = $id;
                        //check ok
                        /** Insert New */
                    } else if (is_array($res) && count($res) == 0) {
                        if (0 >= (int)$sku['qty']) continue;
                        $sku['dirtyProductId'] = $dirtyProduct['id'];
                        $sku['changed'] = 1;
                        unset($sku['extId']);
                        $new = $this->app->dbAdapter->insert('DirtySku', $sku);
                        $this->debug('SkuCycle', 'Inserted Sku: ' . $new, $sku);
                        $this->seenSkus[] = $new;
                    } else {
                        $this->error('Reading Skus', 'Found more than one DirtySku', $values);
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                $this->error('Error Reading Skus', 'Skus reading error', $e);
                continue;
            }
        }
        $this->error('Read Skus done', 'read line: ' . $changedSku, null);

        return $iterator;
    }

    public function saveFiles()
    {
        $dest = $this->err ? "err" : "done";

        fclose($this->mainF);
        fclose($this->skusF);
        fclose($this->sizesF);

        $now = new \DateTime();
        $phar = new \PharData($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/' . $dest . '/' . $now->format('YmdHis') . '.tar');

        $phar->addFile($this->main);
        $phar->addFile($this->skus);
        $phar->addFile($this->sizes);
        if ($phar->count() > 0) {
            $phar->compress(\Phar::GZ);
        }

        unlink($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/' . $dest . '/' . $now->format('YmdHis') . '.tar');
        unlink($this->main);
        unlink($this->skus);
        unlink($this->sizes);
    }
}