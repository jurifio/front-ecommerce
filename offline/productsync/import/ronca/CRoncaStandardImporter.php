<?php

namespace bamboo\offline\productsync\import\ronca;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;

/**
 * Class CRoncaStandardImporter
 * @package bamboo\offline\productsync\import\alducadaosta
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CRoncaStandardImporter extends ABluesealProductImporter
{

    public function readFile($file)
    {
        return true;
    }

    /**
     * @param \DOMElement $element
     * @param $field
     * @return null|string
     */
    protected function getUniqueElementNodeValue(\DOMElement $element, $field)
    {
        return $element->getElementsByTagName($field)->item(0) !== null ? $element->getElementsByTagName($field)->item(0)->nodeValue : null;
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

        $rows = \Monkey::app()->dbAdapter->query('SELECT checksum, id FROM DirtySku WHERE shopId = ? AND qty > 0', [$this->getShop()->id])->fetchAll();
        $skusChecksums = [];
        foreach ($rows as $row) {
            $skusChecksums[$row['checksum']] = $row['id'];
        }

        $rawData = json_decode(file_get_contents($file), true);
        $this->report('processFile', 'Elements: ' . count($rawData));

        $productSkus = 0;
        $doc = new \DOMDocument();
        $doc->load($file);
        foreach ($doc->getElementsByTagName('product') as $elem) {
            /** @var \DOMElement $elem */
            $dirtyProduct = [];

            $dirtyProduct['brand'] = $this->getUniqueElementNodeValue($elem, 'brand');
            $dirtyProduct['itemno'] = $this->getUniqueElementNodeValue($elem, 'codice_prodotto_fornitore');

            $var_composite0 = $elem->getElementsByTagName('var_composite')->item(0);
            $coloreForn = $this->getUniqueElementNodeValue($var_composite0,'colore_forn');
            $colore = $this->getUniqueElementNodeValue($var_composite0,'colore');
            $dirtyProduct['var'] = empty($coloreForn) ? $colore : $coloreForn;

            //VARIANTE
            $dirtyProduct['price'] = $this->getUniqueElementNodeValue($elem, 'price');
            $dirtyProduct['salePrice'] = $this->getUniqueElementNodeValue($elem, 'discount');
            $dirtyProduct['salePrice'] = $this->getUniqueElementNodeValue($elem, 'discount');
            $dirtyProduct['extId'] = $this->getUniqueElementNodeValue($elem, 'sku');

            $dirtyProductExtend = [];
            $dirtyProductExtend['name'] = $this->getUniqueElementNodeValue($elem, 'title');
            $dirtyProductExtend['cat1'] = $this->getUniqueElementNodeValue($elem, 'category');
            $dirtyProductExtend['description'] = $this->getUniqueElementNodeValue($elem, 'description');
            $dirtyProductExtend['audience'] = $this->getUniqueElementNodeValue($elem, 'gender');
            $dirtyProductExtend['season'] = $this->getUniqueElementNodeValue($elem, 'season');
            $dirtyProductExtend['colorDescription'] = $colore;
            try {
                $dirtyProductExtend['generalColor'] = $this->getUniqueElementNodeValue($elem->getElementsByTagName('parameter')->item(0),'value');
            } catch (\Throwable $e) {
                $this->report('Cycle','General Color not found for: '.$dirtyProduct['itemno'].' - '.$dirtyProduct['var']);
                $dirtyProductExtend['generalColor']  = $colore;
            }


            $dirtyProduct['checksum'] = md5(json_encode($dirtyProduct+$dirtyProductExtend));
            $dirtyProduct['keysChecksum'] = md5(implode('::', $this->mapKeys($dirtyProduct, $productKeys)));

            if (isset($checksums[$dirtyProduct['checksum']])) {
                $dirtyProduct['id'] = $checksums[$dirtyProduct['checksum']];
                $dirtyProduct['shopId'] = $this->getShop()->id;
                $this->debug('Cycle','product checksum exists',$dirtyProduct);
            } else {
                \Monkey::app()->repoFactory->beginTransaction();

                if (isset($keysChecksums[$dirtyProduct['keysChecksum']])) {
                    $this->debug('Cycle','product exists, update',$dirtyProduct);
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
                    $this->debug('Cycle','product don\'t exist, insert',$dirtyProduct);
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

                \Monkey::app()->repoFactory->commit();
            }

            $this->debug('Cycle', 'product checking images');
            $dirtyPhotos = \Monkey::app()->dbAdapter->select('DirtyPhoto', ['dirtyProductId' => $dirtyProduct['id']])->fetchAll();
            $position = 0;
            foreach ($elem->getElementsByTagName('image') as $image) {
                $img = $image->nodeValue;
                if (empty(trim($img))) continue;
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

            foreach ($elem->getElementsByTagName('var_composite') as $size) {
                $dirtySku = [];
                $dirtySku['dirtyProductId'] = $dirtyProduct['id'];
                $dirtySku['shopId'] = $dirtyProduct['shopId'];
                $dirtySku['size'] = explode("_",$this->getUniqueElementNodeValue($size, 'key'))[0];
                $dirtySku['qty'] = $this->getUniqueElementNodeValue($size, 'count');
                $dirtySku['extSkuId'] = $this->getUniqueElementNodeValue($size, 'sku');
                $dirtySku['value'] = $this->getUniqueElementNodeValue($size, 'costo');
                $dirtySku['checksum'] = md5(json_encode($dirtySku));
                if (isset($skusChecksums[$dirtySku['checksum']])) {
                    $dirtySku['id'] = $skusChecksums[$dirtySku['checksum']];
                    $this->debug('processFile', 'Sku checksum Exist, save it', $dirtySku);
                } else {
                    $dirtySku['changed'] = 1;

                    $existingSku = \Monkey::app()->dbAdapter->select('DirtySku', [
                        'shopId' => $this->getShop()->id,
                        'dirtyProductId' => $dirtyProduct['id'],
                        'extSkuId' => $dirtySku['extSkuId']
                    ])->fetchAll();

                    if (count($existingSku) == 0) {
                        $dirtySku['id'] = \Monkey::app()->dbAdapter->insert('DirtySku', $dirtySku);
                        $this->debug('processFile', 'Sku don\'t Exist, insert', $dirtySku);

                    } elseif (count($existingSku) == 1) {
                        \Monkey::app()->dbAdapter->update('DirtySku', $dirtySku, ['id' => $existingSku[0]['id']]);
                        $dirtySku['id'] = $existingSku[0]['id'];
                        $this->debug('processFile', 'Sku Exist, update', $dirtySku);

                    } else throw new BambooException('More than 1 sku found to update');
                }

                $seenSkus[] = $dirtySku['id'];
            }
        }

        $this->findZeroSkus($seenSkus);
    }


}