<?php

namespace bamboo\offline\productsync\import\dlrboutique;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;

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
class CDlrboutiqueImportStandard extends ABluesealProductImporter
{

    public function readFile($file)
    {
        return true;
    }

    public function processFile($file)
    {
        $fileMapping = $this->config->fetchAll('mapping');

        $f = fopen($file, 'r');
        fgets($f);

        $this->report('set', 'All Configuration Ready');

        $rows = \Monkey::app()->dbAdapter->query('SELECT id, keysChecksum, checksum FROM DirtyProduct WHERE shopId = ? AND keysChecksum IS NOT NULL', [$this->getShop()->id])->fetchAll();
        $checksums = [];
        $keysChecksums = [];
        foreach ($rows as $one) {
            $checksums[$one['checksum']] = $one['id'];
            $keysChecksums[$one['keysChecksum']] = $one['id'];
        }

        $rows = \Monkey::app()->dbAdapter->query('SELECT checksum, id FROM DirtySku WHERE shopId = ? AND qty > 0', [$this->getShop()->id])->fetchAll();
        $skusChecksums = [];
        foreach ($rows as $row) {
            $skusChecksums[$row['checksum']] = $row['id'];
        }

        $productCount = 0;
        $skuCount = 0;
        $seenSkus = [];
        while (($values = fgetcsv($f, 0, ";")) !== false) {
            $assoc = $this->mapValues($values, $fileMapping);

            try {
                $copy = $assoc;
                unset($copy['size']);
                unset($copy['price']);
                unset($copy['value']);
                unset($copy['qty']);

                $dirtyProduct = [];
                $checksum = md5(json_encode($copy));
                unset($copy);
                // IN THIS CASE KEY AND PRODUCT ARE THE SAME THING... so useless
                if (isset($checksums[$checksum])) {
                    //DONE
                    $dirtyProduct['id'] = $checksums[$checksum];
                } else {
                    $dirtyProduct['brand'] = $assoc['brand'];
                    $dirtyProduct['itemno'] = $assoc['itemno'];
                    $dirtyProduct['var'] = '';
                    $dirtyProduct['extId'] = $assoc['extId'];

                    $dirtyProduct['keysChecksum'] = json_encode($dirtyProduct);
                    $dirtyProduct['checksum'] = $checksum;

                    $dirtyProductExtend['name'] = $assoc['name'];
                    $dirtyProductExtend['description'] = $assoc['description'];
                    $dirtyProductExtend['audience'] = $assoc['audience'];
                    $dirtyProductExtend['generalColor'] = $assoc['generalColor'];
                    $dirtyProductExtend['cat1'] = $assoc['cat1'];
                    $dirtyProductExtend['cat2'] = $assoc['cat2'];
                    $dirtyProductExtend['checksum'] = md5(json_encode($dirtyProductExtend));

                    if (isset($keysChecksums[$dirtyProduct['keysChecksum']])) {
                        \Monkey::app()->dbAdapter->update('DirtyProduct', $dirtyProduct, [
                            'id' => $keysChecksums[$dirtyProduct['keysChecksum']],
                            'shopId' => $this->getShop()->id
                        ]);

                        \Monkey::app()->dbAdapter->update('DirtyProductExtend', $dirtyProductExtend, [
                            'id' => $keysChecksums[$dirtyProduct['keysChecksum']],
                            'shopId' => $this->getShop()->id
                        ]);

                        $dirtyProduct['id'] = $keysChecksums[$dirtyProduct['keysChecksum']];
                    } else {
                        $dirtyProduct['shopId'] = $this->getShop()->id;
                        $dirtyProduct['dirtyStatus'] = 'F';
                        $dirtyProduct['id'] = \Monkey::app()->dbAdapter->insert('DirtyProduct', $dirtyProduct);

                        $dirtyProductExtend['dirtyProductId'] = $dirtyProduct['id'];
                        $dirtyProductExtend['shopId'] = $dirtyProduct['shopId'];

                        \Monkey::app()->dbAdapter->insert('DirtyProductExtend', $dirtyProductExtend);
                        //insert
                    }

                    $checksums[$dirtyProduct['checksum']] = $dirtyProduct['id'];

                    $imgs = [
                        $assoc['img1'],
                        $assoc['img2'],
                        $assoc['img3'],
                        $assoc['img4']
                    ];

                    $details = [
                        'materiale' => $assoc['det1']
                    ];

                    $this->debug('Cycle', 'product checking details', $details);
                    $dirtyDetails = \Monkey::app()->dbAdapter->select('DirtyDetail', ['dirtyProductId' => $dirtyProduct['id']])->fetchAll();
                    foreach ($details as $key => $detail) {
                        if(empty(trim($detail))) continue;
                        foreach ($dirtyDetails as $dirtyDetail) {
                            if ($detail == $dirtyDetail['content']) continue 2;
                        }
                        \Monkey::app()->dbAdapter->insert('DirtyDetail', [
                            'dirtyProductId' => $dirtyProduct['id'],
                            'label' => $key,
                            'content' => $detail
                        ]);
                    }

                    $this->debug('Cycle', 'product checking imgs', $imgs);
                    $dirtyPhotos = \Monkey::app()->dbAdapter->select('DirtyPhoto', ['dirtyProductId' => $dirtyProduct['id']])->fetchAll();
                    $position = 0;
                    foreach ($imgs as $img) {
                        if(empty(trim($img))) continue;
                        foreach ($dirtyPhotos as $exImg) {
                            if ($exImg['url'] == $img) continue 2;
                        }
                        $position++;
                        \Monkey::app()->dbAdapter->insert('DirtyPhoto', [
                            'dirtyProductId' => $dirtyProduct['id'],
                            'shopId' => $this->getShop()->id,
                            'url' => $img,
                            'location' => 'url',
                            'position' => $position,
                            'worked' => 0
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $this->error('ProductCycle','Error while working product',$e);
                continue;
            }
            try {
                $dirtySku = [];
                $dirtySku['dirtyProductId'] = $dirtyProduct['id'];
                $dirtySku['shopId'] = $this->getShop()->id;
                $dirtySku['size'] = $assoc['size'];
                $dirtySku['qty'] = $assoc['qty'];
                $dirtySku['value'] = $this->calculateValue($assoc['price'],$assoc['brand']);
                $dirtySku['price'] = $assoc['price'];
                $dirtySku['checksum'] = md5(json_encode($dirtySku));

                if(isset($skusChecksums[$dirtySku['checksum']])) {
                    $seenSkus[] = $skusChecksums[$dirtySku['checksum']];
                } else {
                    $dirtySku['changed'] = 1;

                    $existingSku = \Monkey::app()->dbAdapter->select('DirtySku',[
                        'shopId'=>$this->getShop()->id,
                        'dirtyProductId' => $dirtyProduct['id'],
                        'size'=>$dirtySku['size']
                    ])->fetchAll();

                    if(count($existingSku) == 0) {
                        $dirtySku['id'] = \Monkey::app()->dbAdapter->insert('DirtySku',$dirtySku);
                        $this->debug('processFile','Sku don\'t Exist, insert',$dirtySku);

                    } elseif(count($existingSku) == 1) {
                        \Monkey::app()->dbAdapter->update('DirtySku',$dirtySku,['id'=>$existingSku[0]['id']]);
                        $dirtySku['id'] = $existingSku[0]['id'];
                        $this->debug('processFile','Sku Exist, update',$dirtySku);

                    } else throw new BambooException('More than 1 sku found to update');

                    $seenSkus[] = $dirtySku['id'];
                }

            } catch (\Throwable $e) {
                $this->error('ProductCycke','Error while working sku',$e);
                continue;
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
    public function mapValues(array $values, array $map)
    {
        $newValues = [];
        foreach ($map as $key => $val) {
            $newValues[$key] = utf8_encode($values[$val]);
        }

        return $newValues;
    }

    /**
     * @param $price
     * @param $brand
     * @return float
     */
    protected function calculateValue($price, $brand) {
        switch (trim($brand)) {
            case 'Saint Laurent Paris': { $percentile = 2.09; break; }
            case 'Tous': { $percentile = 2.2; break; }
            case 'KARL LAGERFELD': { $percentile = 2.45; break; }
            case 'Versace': { $percentile = 2.5; break; }
            case 'ALEXANDER WANG': { $percentile = 2.5; break; }
            case 'Sonia Rykiel': { $percentile = 2.5; break; }
            case 'Borbonese': { $percentile = 2.5; break; }
            case 'Les petits joueurs -': { $percentile = 2.5; break; }
            case 'KENDALL+KYLIE': { $percentile = 2.5; break; }
            case 'Roberta di Camerino': { $percentile = 2.5; break; }
            case 'Santoni': { $percentile = 2.5; break; }
            case 'JIMMY CHOO': { $percentile = 2.59; break; }
            case 'Casadei': { $percentile = 2.6; break; }
            case 'Valentino': { $percentile = 2.65; break; }
            case 'Coach': { $percentile = 2.7; break; }
            case 'Charlotte Olympia': { $percentile = 2.9; break; }
            default: $percentile = 2.6;
        }

        return round($price / ( $percentile ));
    }
}