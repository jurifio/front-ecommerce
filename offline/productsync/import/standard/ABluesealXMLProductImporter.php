<?php

namespace bamboo\offline\productsync\import\standard;

use bamboo\core\application\AApplication;
use bamboo\core\base\CConfig;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooFileException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\exceptions\RedPandaOutOfBoundException;

/**
 * Class ABluesealXMLProductImporter
 * @package redpanda\import\productsync\standard
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
abstract class ABluesealXMLProductImporter extends ABluesealProductImporter
{

    /**
     * ABluesealProductImporter constructor.
     * @param AApplication $app
     * @param null $jobExecution
     */
    public function __construct(AApplication $app, $jobExecution = null)
    {
        parent::__construct($app, $jobExecution);
        $this->genericConfig = new CConfig(__DIR__ . "/config/bluesealXMLProductImporter.json");
        $this->genericConfig->load();
    }

    /**
     * @param $file
     * @return bool
     * @throws BambooFileException
     * @throws BambooOutOfBoundException
     */
    public function readFile($file)
    {
        $content = file_get_contents($file);
        if (mb_detect_encoding($content) == false) {
            $content = preg_replace('/[\xA3]/', utf8_encode('£'), $content);
        }

        $doc = new \DOMDocument();
        $doc->loadXML(utf8_encode($content));
        /** @var \DOMElement $rss */
        $rss = $doc->getElementsByTagName('rss')->item(0);
        $xsd = $rss->getAttribute('xmlns');
        if ($doc->schemaValidateSource(file_get_contents($xsd)) !== true) {
            $this->error('readFile', 'File Corrupted, throwing');
            throw new BambooFileException('Invalid file in Input, validation failed');
        }
        if ($doc->getElementsByTagName('shopId')->item(0)->nodeValue != $this->getShop()->id) {
            $this->error('readFile', 'Wrong shop code, throwing');
            throw new BambooOutOfBoundException('Invalid file in Input, validation failed');
        }

        $dateTime = new \DateTime($doc->getElementsByTagName('date')->item(0)->nodeValue);
        if ((new \DateTime())->diff($dateTime)->days > 3) {
            $this->warning('readFile', 'File too old to be good');
            //throw new RedPandaOutOfBoundException('File too old to be good');
        }

        return true;
    }

    /**
     *  File must be valid now, i can assume it is
     */
    public function processFile($file)
    {
        $doc = new \DOMDocument();
        $content = file_get_contents($file);
        $doc->loadXML(utf8_encode($content));
        try {
            $action = $doc->getElementsByTagName('action')->item(0)->nodeValue;
            if (!is_string($action)) throw new \Exception();
        } catch (\Throwable $e) {
            $this->error('processFile', 'Action not found', $e);
            throw new RedPandaOutOfBoundException('Action not Found', [], [], $e);
        }
        switch (trim(strtolower($action))) {
            case "add":
                $this->add($doc);
                break;
            case "review":
            case "revise":
                $this->revise($doc);
                break;
            case "set":
                $this->set($doc);
                break;
            default:
                throw new RedPandaOutOfBoundException('Action %s not supported', [$action]);
        }
    }

    /**
     * @param \DOMDocument $doc
     */
    public function add(\DOMDocument $doc)
    {
        $this->report('add', 'Starting');
        $iterator = 0;

        $keysChecksums = $this->app->dbAdapter->query('SELECT keysChecksum FROM DirtyProduct WHERE shopId = ? AND keysChecksum IS NOT NULL', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
        $keysChecksums = array_flip($keysChecksums);
        $keysSkus = $this->app->dbAdapter->query('SELECT extSkuId FROM DirtySku WHERE shopId = ? AND extSkuId IS NOT NULL', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);

        $keysSkus = array_flip($keysSkus);

        $productKeys = $this->config->fetch('keys', 'product');
        $skuKeys = $this->config->fetch('keys', 'sku');
        $shopIdArr = ['shopId' => $this->getShop()->id];
        $this->report('add', 'All Configuration Ready');

        $productCount = 0;
        $skuCount = 0;
        foreach ($doc->getElementsByTagName('item') as $elementItem) {
            /** @var $elementItem \DOMElement */
            $iterator++;
            $sharedDirtyProductExtend = [];
            $sharedDirtyProduct = [];
            try {
                $sharedDirtyProduct['brand'] = $this->getUniqueElementNodeValue($elementItem, 'brand');
                $sharedDirtyProductExtend['season'] = $this->getUniqueElementNodeValue($elementItem, 'season');
                $sharedDirtyProductExtend['sizeGroup'] = $this->getUniqueElementNodeValue($elementItem, 'sizeGroup');
                $sharedDirtyProductExtend['audience'] = $this->getUniqueElementNodeValue($elementItem, 'audience');
                $sharedDirtyProductExtend['cat1'] = $this->getUniqueElementNodeValue($elementItem, 'cat1');
                $sharedDirtyProductExtend['cat2'] = $this->getUniqueElementNodeValue($elementItem, 'cat2');
                $sharedDirtyProductExtend['cat3'] = $this->getUniqueElementNodeValue($elementItem, 'cat3');
                $sharedDirtyProductExtend['cat4'] = $this->getUniqueElementNodeValue($elementItem, 'cat4');
                $sharedDirtyProductExtend['cat5'] = $this->getUniqueElementNodeValue($elementItem, 'cat5');

                $dirtyProduct = $sharedDirtyProduct + $shopIdArr;

                foreach ($elementItem->getElementsByTagName('variant') as $elementVariant) {
                    /** @var $elementVariant \DOMElement */

                    $dirtyProductExtend = $sharedDirtyProductExtend + $shopIdArr;

                    $dirtyProduct['itemno'] = $this->getUniqueElementNodeValue($elementVariant, 'cpf');
                    $dirtyProduct['extId'] = $this->getUniqueElementNodeValue($elementVariant, 'extId');
                    $dirtyProduct['var'] = $this->getUniqueElementNodeValue($elementVariant, 'brandColor');

                    $dirtyProductExtend['name'] = $this->getUniqueElementNodeValue($elementVariant, 'name');
                    $dirtyProductExtend['description'] = $this->getUniqueElementNodeValue($elementVariant, 'description');
                    $dirtyProductExtend['generalColor'] = $this->getUniqueElementNodeValue($elementVariant, 'mainColor');
                    $dirtyProductExtend['colorDescription'] = $this->getUniqueElementNodeValue($elementVariant, 'colorDescription');

                    $dirtyProduct['keysChecksum'] = md5(implode('::', $this->mapKeys($dirtyProduct, $productKeys)));
                    $dirtyProduct['detailsChecksum'] = md5(implode('::', $sharedDirtyProductExtend));
                    $dirtyProduct['checksum'] = md5(implode('::', $dirtyProduct));

                    $dirtyProduct['dirtyStatus'] = 'F';

                    if (isset($keysChecksums[$dirtyProduct['keysChecksum']])) {
                        //prodotto già esistente... SERVIREBBE REVISE
                        continue;
                    }
                    \Monkey::app()->repoFactory->beginTransaction();
                    $dirtyProduct['id'] = $this->app->dbAdapter->insert('DirtyProduct', $dirtyProduct);

                    $dirtyProductExtend['dirtyProductId'] = $dirtyProduct['id'];
                    $dirtyProductExtend['checksum'] = md5(implode('::', $dirtyProductExtend));
                    $this->app->dbAdapter->insert('DirtyProductExtend', $dirtyProductExtend);

                    if (!isset($dirtyProduct['relationshipId'])) {
                        $sharedDirtyProduct['parentName'] = $dirtyProduct['id'];
                        $sharedDirtyProduct['relationshipId'] = $dirtyProduct['id'];
                    }

                    $dirtyProduct['sizesChecksum'] = md5($this->getUniqueElementNodeValue($elementVariant, 'sizes'));
                    $dirtyProduct['detailsChecksum'] = md5($this->getUniqueElementNodeValue($elementVariant, 'details'));
                    $dirtyProduct['photosChecksum'] = md5($this->getUniqueElementNodeValue($elementVariant, 'photos'));

                    foreach ($elementVariant->getElementsByTagName('size') as $size) {
                        /** @var \DOMElement $size */
                        if ($size->childNodes->length == 1) continue;
                        $dirtySku = [];
                        $dirtySku['extSkuId'] = $this->getUniqueElementNodeValue($size, 'sku');
                        $dirtySku['dirtyProductId'] = $dirtyProduct['id'];
                        $dirtySku['shopId'] = $this->getShop()->id;
                        $dirtySku['size'] = $this->getUniqueElementNodeValue($size, 'size');
                        $dirtySku['qty'] = $this->getUniqueElementNodeValue($size, 'quantity');
                        $dirtySku['value'] = str_replace(',', '.', $this->getUniqueElementNodeValue($size, 'value'));
                        $dirtySku['price'] = str_replace(',', '.', $this->getUniqueElementNodeValue($size, 'price'));
                        $dirtySku['salePrice'] = str_replace(',', '.', $this->getUniqueElementNodeValue($size, 'salePrice'));
                        $dirtySku['checksum'] = md5($size->nodeValue);
                        $dirtySku['changed'] = 1;

                        if (isset($keysSkus[$dirtySku['extSkuId']])) {
                            continue;
                        }

                        $dirtySku['id'] = $this->app->dbAdapter->insert('DirtySku', $dirtySku);
                        $keysSkus[$dirtySku['id']] = "";
                        $skuCount++;
                    }

                    $this->updateDetails($elementVariant, $dirtyProduct);
                    $this->updatePhotos($elementVariant, $dirtyProduct);
                    $productCount++;
                    \Monkey::app()->repoFactory->commit();
                    //\Monkey::app()->repoFactory->rollback();
                }
            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                $this->error('add', 'exception', $e);
            }
        }
        $this->report('add', 'Iterator Count: ' . $iterator);
        $this->report('add', 'Product Count: ' . $productCount);
        $this->report('add', 'Sku Count: ' . $skuCount);
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

    /**
     * @param \DOMElement $item
     * @param array $dirtyProduct
     * @return int
     */
    protected function updateDetails(\DOMElement $item, array $dirtyProduct)
    {
        $this->app->dbAdapter->delete('DirtyDetail', ["dirtyProductId" => $dirtyProduct['id']]);
        $details = 0;
        foreach ($item->getElementsByTagName('detail') as $detail) {
            $det = [];
            /** @var \DOMElement $detail */
            if ($detail->getElementsByTagName('content')->length == 1 && $detail->getElementsByTagName('content')->item(0)) $det['content'] = trim($detail->getElementsByTagName('content')->item(0)->textContent);
            if (isset($det['content']) && empty($det['content'])) continue;
            $det['label'] = $this->getUniqueElementNodeValue($detail, 'label');
            $det['dirtyProductId'] = $dirtyProduct['id'];
            $this->app->dbAdapter->insert('DirtyDetail', $det);
            $details++;
        }
        return $details;
    }

    /**
     * @param \DOMElement $item
     * @param array $dirtyProduct
     * @return int
     */
    protected function updatePhotos(\DOMElement $item, array $dirtyProduct)
    {
        $photos = 0;
        $readPhotos = $this->app->dbAdapter->query("  SELECT id, url, worked
													  FROM DirtyPhoto
													  WHERE dirtyProductId = ? AND shopId = ?", [$dirtyProduct['id'], $this->getShop()->id])->fetchAll();
        $this->debug('updatePhotos', 'Found already inserted photos: ' . count($readPhotos), $readPhotos);
        foreach ($item->getElementsByTagName('photo') as $photo) {
            $this->debug('updatePhotos', 'working photo for ' . $dirtyProduct['id'], $photo);
            $photoArray = [];
            /** @var \DOMElement $photo */
            $url = $photo->getElementsByTagName('url')->length > 0 ? $photo->getElementsByTagName('url')->item(0)->nodeValue : "";
            $path = $photo->getElementsByTagName('path')->length > 0 ? $photo->getElementsByTagName('path')->item(0)->nodeValue : "";
            if (empty($url) && empty($path)) continue;
            $photos++;
            $location = empty($url) ? 'path' : 'url';
            $url = ${$location};
            $photoArray = ['dirtyProductId' => $dirtyProduct['id'], 'location' => $location, 'url' => $url, 'worked' => 0, 'shopId' => $this->getShop()->id, 'position' => $photos];
            $this->debug('updatePhotos', 'working photo for ' . $dirtyProduct['id'], $photoArray);

            $existing = false;
            foreach ($readPhotos as $readPhoto) {
                if ($readPhoto['url'] == $url) {
                    $existing = true;
                    break;
                }
            }
            if (!$existing) {
                $this->debug('updatePhotos', 'inserting photo for ' . $dirtyProduct['id'], $photoArray);
                $this->app->dbAdapter->insert('DirtyPhoto', $photoArray);
            }
        }
        return $photos;
    }

    /**
     * @param \DOMDocument $doc
     * @throws RedPandaOutOfBoundException
     */
    public function revise(\DOMDocument $doc)
    {
        $productKeys = $this->config->fetch('keys', 'product');

        $this->report("revise", "start revise");
        $shopId = $this->getShop()->id;
        $keysChecksums = $this->app->dbAdapter->query('SELECT keysChecksum FROM DirtyProduct WHERE shopId = ? AND keysChecksum IS NOT NULL', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
        $keysChecksums = array_flip($keysChecksums);

        // conteggi per il debug
        $varCount = 0;
        $varFound = 0;
        $skuCount = 0;
        $skuProcessed = 0;
        $skuUpdated = 0;
        $skuInserted = 0;
        $skuIgnored = 0;
        foreach ($doc->getElementsByTagName('item') as $elem) {

            // items' elements used for $checksum
            $elemChecksum['brand'] = $this->getUniqueElementNodeValue($elem, 'brand');
            //$elemChecksum['shop'] = $shopId;

            foreach ($elem->getElementsByTagName('variant') as $var) {

                // variants' elements used in the $checksum
                $varChecksum['itemno'] = $this->getUniqueElementNodeValue($var, 'cpf');
                $varChecksum['extId'] = $this->getUniqueElementNodeValue($var, 'extId');
                $varChecksum['var'] = $this->getUniqueElementNodeValue($var, 'brandColor');
                //uso mapKeys per essere sicuro di avere gli elementi nell'ordine giusto
                $keysChecksum = md5(implode('::', $this->mapKeys($elemChecksum + $varChecksum + ['shop' => $this->getShop()->id], $productKeys)));

                $existsDebug = 0;
                if (!array_key_exists($keysChecksum, $keysChecksums)) {
                    $dpcheck = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy([
                            'itemno' => $varChecksum['itemno'],
                            'extId' => $varChecksum['extId'],
                            'var' => $varChecksum['var'],
                            'shopId' => $shopId]
                    );
                    if ($dpcheck) {
                        $dpcheck->keysChecksum = $keysChecksum;
                        $dpcheck->update();
                        $existsDebug = 1;
                    }

                    $varCount++;
                }
                if ((array_key_exists($keysChecksum, $keysChecksums)) || ($existsDebug)) {
                    $varFound++;
                    $idProduct = $this->app->dbAdapter->select('DirtyProduct', ['keysChecksum' => $keysChecksum, 'shopId' => $shopId])->fetch();

                    foreach ($var->getElementsByTagName('size') as $s) {
                        if (!$this->getUniqueElementNodeValue($s, 'size')) continue;
                        $skuCount++;

                        $sku = [];
                        $sku['extSkuId'] = $this->getUniqueElementNodeValue($s, 'sku');

                        $sku['barcode'] = $this->getUniqueElementNodeValue($s, 'barcode');
                        if (empty(trim($sku['barcode']))) unset($sku['barcode']);

                        $sku['barcode_int'] = $this->getUniqueElementNodeValue($s, 'barcode_int');
                        if (empty(trim($sku['barcode_int']))) unset($sku['barcode_int']);

                        $sku['dirtyProductId'] = $idProduct['id'];
                        $sku['shopId'] = $shopId;
                        $sku['size'] = $this->getUniqueElementNodeValue($s, 'size');
                        $sku['qty'] = $this->getUniqueElementNodeValue($s, 'quantity');
                        $sku['value'] = str_replace(',', '.', $this->getUniqueElementNodeValue($s, 'value'));
                        $sku['price'] = str_replace(',', '.', $this->getUniqueElementNodeValue($s, 'price'));
                        $sku['salePrice'] = str_replace(',', '.', $this->getUniqueElementNodeValue($s, 'salePrice'));

                        $sku['checksum'] = md5($s->nodeValue);

                        $sku['changed'] = 1;
                        //var_dump($idProduct);
                        //var_dump($sku['size']);
                        $res = $this->app->dbAdapter->select("DirtySku", ['dirtyProductId' => $idProduct['id'], 'size' => $sku['size']])->fetch();

                        if ($res) {
                            $skuProcessed++;
                            if ($sku['checksum'] != $res['checksum']) {
                                unset($sku['dirtyProductId']);
                                $sizeUpd = $sku['size'];
                                unset($sku['size']);
                                //$this->app->dbAdapter->delete("DirtySku", ['dirtyProductId' => $idProduct['id'], 'size' => $sizeUpd]);
                                try {
                                    $this->app->dbAdapter->update("DirtySku", $sku, ['dirtyProductId' => $idProduct['id'], 'size' => $sizeUpd]);
                                } catch (\Throwable $e) {
                                    $this->error(
                                        "REVISE - Update DirtySku",
                                        "dirtyProductId: " . $idProduct['id'] . ", size: " . $sizeUpd . ", extSkuId: " . $sku['extSkuId'],
                                        $e->getMessage()
                                    );
                                }
                                $skuUpdated++;
                            } else {
                                $skuIgnored++;
                            }
                        } else {
                            try {
                                $this->app->dbAdapter->insert("DirtySku", $sku);
                            } catch (\Throwable $e) {
                                $this->error("REVISE - Insert DirtySku",
                                    "dirtyProductId: " . $sku['dirtyProductId'] . ", size: " . $sku['size'] . ", extSkuId: " . $sku['extSkuId'],
                                    $e->getMessage()
                                );
                            }
                            $skuInserted++;
                        }

                    }
                }
            }

        }
        $this->report('revise', 'Fine. Variazioni contate nel file: ' . $varCount .
            " di cui elaborate: " . $varFound . " - Sku contanti nel file: " . $skuCount . " di cui trovati nel db: " . $skuProcessed .
            ". Di questi: " . $skuInserted . " inseriti, " . $skuUpdated . " aggiornati e " . $skuIgnored . " ignorati (perché non sono cambiati)");

    }

    /**
     * @param \DOMDocument $doc
     */
    public function set(\DOMDocument $doc)
    {
        $productKeys = $this->config->fetch('keys', 'product');

        $this->report("set", "start set");

        $rows = $this->app->dbAdapter->query('SELECT keysChecksum, id FROM DirtyProduct WHERE shopId = ? AND keysChecksum IS NOT NULL', [$this->getShop()->id])->fetchAll();
        $keysChecksums = [];
        foreach ($rows as $one) {
            $keysChecksums[$one['keysChecksum']] = $one['id'];
        }
        $shopIdArr = ['shopId' => $this->getShop()->id];

        $seenSkus = [];
        // conteggi per il debug
        $varCount = 0;
        $varFound = 0;
        $skuCount = 0;
        $skuProcessed = 0;
        $skuUpdated = 0;
        $skuInserted = 0;
        $skuIgnored = 0;
        $iterator = 0;

        /** @var \DOMElement $elem */
        foreach ($doc->getElementsByTagName('item') as $elem) {
            $iterator++;
            $sharedDirtyProductExtend = [];
            $sharedDirtyProduct = [];

            $sharedDirtyProduct['shopId'] = $this->getShop()->id;
            $sharedDirtyProduct['brand'] = $this->getUniqueElementNodeValue($elem, 'brand');

            $sharedDirtyProductExtend['shopId'] = $this->getShop()->id;
            $sharedDirtyProductExtend['season'] = $this->getUniqueElementNodeValue($elem, 'season');
            $sharedDirtyProductExtend['sizeGroup'] = $this->getUniqueElementNodeValue($elem, 'sizeGroup');
            $categories = $elem->getElementsByTagName('categories')->item(0);
            $sharedDirtyProductExtend['audience'] = $this->getUniqueElementNodeValue($categories, 'audience');
            $sharedDirtyProductExtend['cat1'] = $this->getUniqueElementNodeValue($categories, 'cat1');
            $sharedDirtyProductExtend['cat2'] = $this->getUniqueElementNodeValue($categories, 'cat2');
            $sharedDirtyProductExtend['cat3'] = $this->getUniqueElementNodeValue($categories, 'cat3');
            $sharedDirtyProductExtend['cat4'] = $this->getUniqueElementNodeValue($categories, 'cat4');
            $sharedDirtyProductExtend['cat5'] = $this->getUniqueElementNodeValue($categories, 'cat5');

            foreach ($elem->getElementsByTagName('variant') as $var) {

                try {
                    \Monkey::app()->repoFactory->beginTransaction();
                    /** @var \DOMElement $var */
                    $dirtyProduct = $sharedDirtyProduct;
                    // variants' elements used in the $checksum
                    $dirtyProduct['itemno'] = $this->getUniqueElementNodeValue($var, 'cpf');
                    $dirtyProduct['extId'] = $this->getUniqueElementNodeValue($var, 'extId');
                    $dirtyProduct['var'] = $this->getUniqueElementNodeValue($var, 'brandColor');
                    $dirtyProduct['shopId'] = $this->getShop()->id;

                    $dirtyProductExtendA = $sharedDirtyProductExtend;
                    $dirtyProductExtendA['name'] = $this->getUniqueElementNodeValue($var, 'name');
                    $dirtyProductExtendA['description'] = $this->getUniqueElementNodeValue($var, 'description');
                    $dirtyProductExtendA['generalColor'] = $this->getUniqueElementNodeValue($var, 'mainColor');
                    $dirtyProductExtendA['colorDescription'] = $this->getUniqueElementNodeValue($var, 'colorDescription');
                    $dirtyProductExtendA['shopId'] = $this->getShop()->id;

                    if ($var->getElementsByTagName('tags')->length > 0) {
                        $tags = $var->getElementsByTagName('tags')->item(0);
                        $dirtyProductExtendA['tag1'] = $this->getUniqueElementNodeValue($tags, 'tag1');
                        $dirtyProductExtendA['tag2'] = $this->getUniqueElementNodeValue($tags, 'tag2');
                        $dirtyProductExtendA['tag3'] = $this->getUniqueElementNodeValue($tags, 'tag3');
                    } else {
                        $dirtyProductExtendA['tag1'] = null;
                        $dirtyProductExtendA['tag2'] = null;
                        $dirtyProductExtendA['tag3'] = null;
                    }

                    //uso mapKeys per essere sicuro di avere gli elementi nell'ordine giusto
                    $dirtyProduct['keysChecksum'] = md5(implode('::', $this->mapKeys($dirtyProduct, $productKeys)));
                    $dirtyProduct['detailsChecksum'] = md5(implode('::', $dirtyProductExtendA));
                    $dirtyProduct['checksum'] = md5(implode('::', $dirtyProduct));
                    $this->debug('Variant Cycle', 'Working Product ', $dirtyProduct);
                    if (array_key_exists($dirtyProduct['keysChecksum'], $keysChecksums)) {
                        $dproduct = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['keysChecksum' => $dirtyProduct['keysChecksum']]);
                    } else {
                        $dproduct = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy($this->mapKeys($dirtyProduct, $productKeys));
                    }

                    if ($dproduct) {
                        if ($dirtyProduct['checksum'] != $dproduct->checksum) {
                            $dproduct->brand = $sharedDirtyProduct['brand'];
                            $dproduct->itemno = $dirtyProduct['itemno'];
                            $dproduct->extId = $dirtyProduct['extId'];
                            $dproduct->var = $dirtyProduct['var'];
                            $dproduct->keysChecksum = $dirtyProduct['keysChecksum'];
                            $dproduct->checksum = $dirtyProduct['checksum'];

                            $dproduct->update();
                            $keysChecksums[$dirtyProduct['keysChecksum']] = $dproduct->id;

                            $dirtyProductExtend = $dproduct->extend;

                            $dirtyProductExtend->name = $dirtyProductExtendA['name'];
                            $dirtyProductExtend->description = $dirtyProductExtendA['description'];
                            $dirtyProductExtend->season = $dirtyProductExtendA['season'];
                            $dirtyProductExtend->audience = $dirtyProductExtendA['audience'];
                            $dirtyProductExtend->cat1 = $dirtyProductExtendA['cat1'];
                            $dirtyProductExtend->cat2 = $dirtyProductExtendA['cat2'];
                            $dirtyProductExtend->cat3 = $dirtyProductExtendA['cat3'];
                            $dirtyProductExtend->cat4 = $dirtyProductExtendA['cat4'];
                            $dirtyProductExtend->cat5 = $dirtyProductExtendA['cat5'];
                            $dirtyProductExtend->tag1 = $dirtyProductExtendA['tag1'];
                            $dirtyProductExtend->tag2 = $dirtyProductExtendA['tag2'];
                            $dirtyProductExtend->tag3 = $dirtyProductExtendA['tag3'];
                            $dirtyProductExtend->generalColor = $dirtyProductExtendA['generalColor'];
                            $dirtyProductExtend->colorDescription = $dirtyProductExtendA['colorDescription'];
                            $dirtyProductExtend->sizeGroup = $dirtyProductExtendA['sizeGroup'];
                            $dirtyProductExtend->update();
                        }
                    } else {

                        //INSERT!!!!!
                        $dproduct = \Monkey::app()->repoFactory->create('DirtyProduct')->getEmptyEntity();
                        $dproduct->shopId = $this->getShop()->id;
                        $dproduct->brand = $sharedDirtyProduct['brand'];
                        $dproduct->itemno = $dirtyProduct['itemno'];
                        $dproduct->extId = $dirtyProduct['extId'];
                        $dproduct->var = $dirtyProduct['var'];
                        $dproduct->keysChecksum = $dirtyProduct['keysChecksum'];
                        $dproduct->dirtyStatus = 'F';
                        $dproduct->id = $dproduct->insert();
                        $dproduct = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy($dproduct->getIds());


                        $dirtyProductExtend = \Monkey::app()->repoFactory->create('DirtyProductExtend')->getEmptyEntity();
                        $dirtyProductExtend->dirtyProductId = $dproduct->id;
                        $dirtyProductExtend->shopId = $dproduct->shopId;
                        $dirtyProductExtend->name = $dirtyProductExtendA['name'];
                        $dirtyProductExtend->description = $dirtyProductExtendA['description'];
                        $dirtyProductExtend->season = $dirtyProductExtendA['season'];
                        $dirtyProductExtend->audience = $dirtyProductExtendA['audience'];
                        $dirtyProductExtend->cat1 = $dirtyProductExtendA['cat1'];
                        $dirtyProductExtend->cat2 = $dirtyProductExtendA['cat2'];
                        $dirtyProductExtend->cat3 = $dirtyProductExtendA['cat3'];
                        $dirtyProductExtend->cat4 = $dirtyProductExtendA['cat4'];
                        $dirtyProductExtend->cat5 = $dirtyProductExtendA['cat5'];
                        $dirtyProductExtend->tag1 = $dirtyProductExtendA['tag1'];
                        $dirtyProductExtend->tag2 = $dirtyProductExtendA['tag2'];
                        $dirtyProductExtend->tag3 = $dirtyProductExtendA['tag3'];
                        $dirtyProductExtend->generalColor = $dirtyProductExtendA['generalColor'];
                        $dirtyProductExtend->colorDescription = $dirtyProductExtendA['colorDescription'];
                        $dirtyProductExtend->sizeGroup = $dirtyProductExtendA['sizeGroup'];
                        $dirtyProductExtend->insert();
                    }
                    $varCount++;


                    foreach ($var->getElementsByTagName('size') as $s) {
                        if (!$this->getUniqueElementNodeValue($s, 'size')) continue;
                        $sizeUpd = $this->getUniqueElementNodeValue($s, 'size');
                        try {
                            $skuCount++;

                            $sku = [];
                            $skuChecksum = md5($s->nodeValue);
                            $sku = \Monkey::app()->repoFactory->create('DirtySku')->findOneBy(["checksum" => $skuChecksum]);

                            if (!is_null($sku)) {
                                $seenSkus[] = $sku->id;
                                //unchanged
                            } else {
                                $extSkuId = $this->getUniqueElementNodeValue($s, 'sku');
                                $size = $this->getUniqueElementNodeValue($s, 'size');
                                $sku = \Monkey::app()->repoFactory->create('DirtySku')->findOneBy(["extSkuId" => $extSkuId]);
                                if (is_null($sku)) {
                                    $sku = \Monkey::app()->repoFactory->create('DirtySku')->getEmptyEntity();
                                    $sku->size = $size;
                                    $sku->extSkuId = $extSkuId;
                                    $sku->dirtyProductId = $dproduct->id;
                                    $sku->shopId = $this->getShop()->id;
                                    $sku->id = $sku->insert();
                                    $sku = \Monkey::app()->repoFactory->create('DirtySku')->findOneBy(["id" => $sku->id]);
                                    $skuInserted++;
                                }

                                $sku->checksum = $skuChecksum;
                                $sku->barcode = $this->getUniqueElementNodeValue($s, 'barcode');
                                if (empty(trim($sku->barcode))) $sku->barcode = null;

                                $sku->barcode_int = $this->getUniqueElementNodeValue($s, 'barcode_int');
                                if (empty(trim($sku->barcode_int))) $sku->barcode_int = null;

                                $sku->qty = $this->getUniqueElementNodeValue($s, 'quantity');
                                $sku->value = str_replace(',', '.', $this->getUniqueElementNodeValue($s, 'value'));
                                $sku->price = str_replace(',', '.', $this->getUniqueElementNodeValue($s, 'price'));
                                $sku->salePrice = str_replace(',', '.', $this->getUniqueElementNodeValue($s, 'salePrice'));
                                $sku->changed = 1;
                                $seenSkus[] = $sku->id;

                                $sku->update();
                                $skuUpdated++;
                            }

                            $detCount = $this->updateDetails($var, $dproduct->toArray());
                            $this->debug('updateDetails', 'Ended: ' . $detCount);
                            $phoCount = $this->updatePhotos($var, $dproduct->toArray());
                            $this->debug('updatePhotos', 'Ended: ' . $phoCount);
                        } catch (\Throwable $e) {
                            $did = 'errore nel trovare l\'id';
                            try {
                                $did = $dproduct->id;
                            } catch (\Throwable $e) {
                            }
                            $this->error(
                                "SET - analyzing DirtySku",
                                "dirtyProductId: " . $did . ", size: " . $sizeUpd, $e);
                        }
                    }
                    \Monkey::app()->repoFactory->commit();
                } catch (\Throwable $e) {
                    \Monkey::app()->repoFactory->rollback();
                    $this->error('SET Generic Error','Errore generico nella lavorazione di una "variant"',$e);
                }
            }
        }

        $cleared = $this->findZeroSkus($seenSkus);
        $this->report('set', 'Fine. Variazioni contate nel file: ' . $varCount .
            " di cui elaborate: " . $varFound . " - Sku contanti nel file: " . $skuCount . " di cui trovati nel db: " . $skuProcessed .
            ". Di questi: " . $skuInserted . " inseriti, " . $skuUpdated . " aggiornati e " . $skuIgnored . " ignorati (perché non sono cambiati)");

    }
}