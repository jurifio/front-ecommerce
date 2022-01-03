<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 28/05/2015
 * Time: 16:36
 */

namespace bamboo\ecommerce\offline\productsync\import;

use bamboo\core\base\CConfig;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\jobs\ACronJob;
use bamboo\core\utils\slugify\CSlugify;
use bamboo\domain\entities\CDirtySku;
use bamboo\utils\price\SPriceToolbox;

class CSyncStocks extends ACronJob
{

    protected $config;
    protected $sizesDictionary = [];
    protected $log;
    protected $shopsConfig = [];

    /**
     * @param null $mode
     * @return bool
     */
    public function run($mode = null)
    {
        try {
            $this->report("Start", "started import", []);
            proc_nice(10);
            $this->report("LoadConfig", "Load shop specific configurations");
            $this->loadShopsConfig();

            $this->report("linkSkus", "linkSkus", []);
            $this->linkSkus();

            $this->report("updateSkus", "updateSkus", []);
            $this->updateSkus();

            $this->report("End", "finito", []);
        } catch (\Throwable $e) {
            var_dump($e);
            $this->error("General", "General error", $e);
        }
        echo 'fatto';

        return true;
    }

    /**
     *
     */
    function loadShopsConfig()
    {
        $shop = \Monkey::app()->repoFactory->create('Shop')->findAll();
        foreach ($shop as $k => $v) {
            $this->shopsConfig[$v->id] = $v->config;
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function linkSkus()
    {
        $dictionary = new CConfig(__DIR__ . "/dictionary.json");
        $dictionary->load();
        $this->sizesDictionary['others'] = $dictionary->fetchAll('sizes');
        $slugify = new CSlugify();

        $this->loadDictionaries();

        $min = 0;

        // trovo tutti i prodoti da matchare
        $res = $this->app->dbAdapter->query("SELECT DISTINCT dp.* 
                                              FROM DirtyProduct dp
                                                 JOIN DirtySku ds ON ds.dirtyProductId = dp.id AND ds.shopId = dp.shopId
                                                 JOIN ShopHasProduct shp ON dp.productId = shp.productId AND dp.productVariantId = shp.productVariantId AND dp.shopId = shp.shopId  
                                                 JOIN Product p ON  shp.productId = p.id AND shp.productVariantId = p.productVariantId 
                                                 JOIN Shop s ON shp.shopId = s.id
                                              WHERE 
                                              s.isActive = 1 AND
                                              ds.productSizeId IS NULL AND
                                              shp.productSizeGroupId IS NOT NULL AND 
                                              p.productStatusId IS NOT NULL AND
                                              p.productStatusId NOT IN (7,8,13) AND 
                                              dp.dirtyStatus NOT IN ('N','C') AND
                                              dp.productVariantId IS NOT NULL GROUP BY dp.id HAVING sum(ds.qty) > 0 LIMIT 50000", [])->fetchAll();
        if ($res < 1) {
            //error
            //log
            return false;
        }
        $this->report("Linking Skus", "Product to link: " . count($res), []);
        /** dirty product that miss link*/
        $z = 0;
        foreach ($res as $dirtyProduct) {
            $this->debug('Inizio ciclo di lavoro','Lavoro per prodotto sporco: '.$dirtyProduct['id'],$dirtyProduct);
            $z++;
            $min++;
            if ($z % 100 == 0) {
                /*$this->report("Linking Skus", "Product done: " . $z . ' todo =' . (count($res) - $z), []);*/
                $this->report("Linking Skus", "Product done: " . $z ,'');
            }
            //per ogni prodotto trovo il gruppo fuck taglie e le relative taglie
           /* $goodSizes = $this->app->dbAdapter->query("SELECT shp.productSizeGroupId,ps.*
                                                          FROM ProductSizeGroupHasProductSize psg 
                                                            JOIN ShopHasProduct shp ON psg.productSizeGroupId = shp.productSizeGroupId
                                                            JOIN ProductSize ps ON psg.productSizeId = ps.id 
                                                          WHERE shp.productId = ? AND
                                                                shp.productVariantId = ? AND
                                                                shp.shopId = ?",
                                      [$dirtyProduct['productId'], $dirtyProduct['productVariantId'], $dirtyProduct['shopId']])->fetchAll();*/
            $goodSizes = $this->app->dbAdapter->query("SELECT ps.*
                                                         ProductSize ps ",[])->fetchAll();

            $skus = $this->app->dbAdapter->query("SELECT * FROM DirtySku WHERE dirtyProductId = ?", [$dirtyProduct['id']])->fetchAll();

            $ids_slugs = [];
            $ids_names = [];
            $groupSize = $goodSizes[0]['productSizeGroupId'];
            // inserisco in due array le i rispettivi nomi taglia e slug
            foreach ($goodSizes as $size) {
                $ids_names[$size['id']] = $size['name'];
                $ids_slugs[$size['id']] = $size['slug'];
            }

            $error = null;
            \Monkey::app()->repoFactory->beginTransaction();
            try {
                // inizio lavorazione match
                foreach ($skus as $sku) {
                    $sizeMatch = null;
                    $sizeId = null;

                    if($sku['status'] == CDirtySku::EXCLUDED_STATUS) continue;

                    if (isset($this->sizesDictionary[$sku['shopId']])) {
                        $res = $this->sizeArraySearch(strtolower(trim($sku['size'])), $this->sizesDictionary[$sku['shopId']]);
                        if (!is_int($res)) {
                            $error = $sku;
                            throw new BambooOutOfBoundException('Size not found in Dictionary Tables: ' . $sku['size']);
                       } elseif (!array_key_exists($res, $ids_names)) {
                            //throw new BambooOutOfBoundException('Size out of Size Group: size:' . $sku['size'] . ', id:' . $res . ' Group ' . $groupSize . ':' . implode(',', $ids_names));
                            throw new BambooOutOfBoundException('Size out of Size Group: size:' . $sku['size'] . ', id:' . $res . ' Group :' . implode(',', $ids_names));
                        } else {
                            $up = $this->app->dbAdapter->update("DirtySku", ["productSizeId" => $res, "status" => 'ok'], ["id" => $sku['id']]);
                        }
                    } else {
                        if (isset($this->sizesDictionary['others'][$sku['size']])) {
                            $sizeMatch = preg_grep('/^' . $this->sizesDictionary['others'][$sku['size']] . '$/', $ids_slugs);
                        }
                        $fixed = str_replace('+', '\+', $sku['size']);
                        $fixed = str_replace("\\", "\\\\", $fixed);
                        $fixed = str_replace("/", "\\/", $fixed);
                        if (empty($sizeMatch)) {
                            $sizeMatch = preg_grep('/^' . $fixed . '$/', $ids_names);
                        }
                        if (empty($sizeMatch)) {
                            $sizeMatch = preg_grep('/^' . $slugify->slugify($sku['size']) . '$/', $ids_slugs);
                        }
                        if (!empty($sizeMatch) && count($sizeMatch) == 1) {
                            $sizeId = array_keys($sizeMatch)[0];
                            $up = $this->app->dbAdapter->update("DirtySku", ["productSizeId" => $sizeId, "status" => 'ok'], ["id" => $sku['id']]);
                        } else {
                            $error = $sku;
                            //log
                            //error
                            throw new BambooOutOfBoundException('one or more sizes didnt match ' . $sku['size']);
                            continue;
                        }
                    }
                }
                $this->app->dbAdapter->update("DirtyProduct", ["fullMatch" => 1], ["id" => $dirtyProduct['id']]);

                \Monkey::app()->repoFactory->commit();

            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                $min--;
                $this->warning('linkSkus', 'Error while linking skus for product: productId ' . $dirtyProduct['productId'] . " - productVariantId " . $dirtyProduct['productVariantId'], $e);
                foreach ($skus as $sku) {
                    $up = $this->app->dbAdapter->update("DirtySku", ['status' => 'Size Mismatch'], ["id" => $sku['id']]);
                }
                if ($error != null && isset($error['size'])) {
                    $up = $this->app->dbAdapter->update("DirtySku", ['status' => 'Size Mismatch - Guilty'], ["id" => $error['size']]);
                }
            }
        }
        $this->report("Linking Skus", "Product liked: " . $min, []);

        return true;
    }

    /**
     *
     */
    public function loadDictionaries()
    {
        try {
            foreach ($this->app->dbAdapter->query("SELECT shopId, term, productSizeId FROM DictionarySize WHERE productSizeId IS NOT NULL", [])->fetchAll() as $term) {
                if ($term['term'] != 0 && empty(trim($term['term']))) continue;
                $this->sizesDictionary[$term['shopId']][$term['productSizeId']][] = strtolower(trim($term['term']));
            }
        } catch (\Throwable $e) {
            $this->error('loadDictionaries', 'failed loading dictionaries', $e);
        }
    }

    /**
     * @param $object
     * @param array $array
     * @return bool|int|string
     */
    function sizeArraySearch($object, $array)
    {
        foreach ($array as $key => $arr) {
            if (array_search($object, $arr) !== false) {
                return $key;
            }
        }

        return false;
    }

    /**
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    public function updateSkus()
    {
        $skuRepo = \Monkey::app()->repoFactory->create('ProductSku');
        $dirtySkuRepo = \Monkey::app()->repoFactory->create('DirtySku');

        $res = $this->app->dbAdapter->query("SELECT
                                                      max(ds.id)                              AS dirtySkuId,
                                                      group_concat(ds.id)                     AS dirtySkusId,
                                                      dp.productId                            AS productId,
                                                      dp.productVariantId                     AS productVariantId,
                                                      dp.shopId                               AS shopId,
                                                      ds.productSizeId                        AS productSizeId,
                                                      ds.barcode                              AS barcode,
                                                      max(ds.changed)                         AS changed,
                                                      max(ifnull(ds.value, dp.value))         AS value,
                                                      max(ifnull(ds.price, dp.price))         AS price,
                                                      max(ifnull(ds.salePrice, dp.salePrice)) AS salePrice,
                                                      sum(ifnull(ds.qty, 0))                  AS extQty,
                                                      ifnull(ps.stockQty, 0)                  AS inQty,
                                                      ifnull(ps.padding, 0)                   AS padding
                                                    FROM
                                                      DirtyProduct dp
                                                      JOIN DirtySku ds ON (dp.id, dp.shopId) = (ds.dirtyProductId, ds.shopId)
                                                      JOIN Shop s ON dp.shopId = s.id
                                                      LEFT JOIN ProductSku ps ON
                                                                                (dp.productId, dp.productVariantId, dp.shopId, ps.productSizeId) =
                                                                                (ps.productId, ps.productVariantId, ps.shopId, ds.productSizeId)
                                                    WHERE
                                                      s.isActive = 1 AND
                                                      nullif(trim(s.importer), '') IS NOT NULL AND
                                                      ds.productSizeId IS NOT NULL AND
                                                      dp.fullMatch = 1 AND
                                                      (ds.value IS NOT NULL OR dp.value IS NOT NULL)
                                                    GROUP BY dp.productId, dp.productVariantId, dp.shopId, ds.productSizeId
                                                    HAVING (changed = 1 OR (
                                                      inQty - padding != extQty AND
                                                      (extQty >= 0 AND
                                                       inQty >= 0 AND
                                                       padding >= 0))
                                                    )", [])->fetchAll();

        $this->report("Updating Skus", "Product to update: " . count($res), $res);
        /** dirty product that miss link*/
        $z = 0;
        $x = 0;
        foreach ($res as $dirtySku) {
            $this->debug('updateSkus', 'working: ' . $dirtySku['dirtySkuId'], $dirtySku);
            $z++;
            if ($z % 50 == 0) {
                $this->report("Updating Skus", "Product done updating: " . $z . ' todo =' . (count($res) - $z), []);
            }
            try {
                $sku = $skuRepo->findOneBy(['productId' => $dirtySku['productId'],
                    'productVariantId' => $dirtySku['productVariantId'],
                    'shopId' => $dirtySku['shopId'],
                    'productSizeId' => $dirtySku['productSizeId']]);

                $price = $dirtySku['price'];
                $salePrice = $dirtySku['salePrice'];
                $value = $dirtySku['value'];

                $price = round($this->calculatePriceModifier($dirtySku['shopId'], 'price', $price));
                $salePrice = round($this->calculatePriceModifier($dirtySku['shopId'], 'salePrice', $salePrice));
                $value = $this->calculatePriceModifier($dirtySku['shopId'], 'value', $value);

                if (is_null($sku)) {
                    $this->debug('updateSkus', 'Inserting Sku', $dirtySku);
                    $sku = $skuRepo->getEmptyEntity();

                    $sku->productId = $dirtySku['productId'];
                    $sku->productVariantId = $dirtySku['productVariantId'];
                    $sku->productSizeId = $dirtySku['productSizeId'];
                    $sku->shopId = $dirtySku['shopId'];
                    $sku->price = $price;
                    $sku->salePrice = is_null($salePrice) ? 0 : $salePrice;
                    $sku->value = $value;
                    $sku->stockQty = $dirtySku['extQty'];
                    $sku->ean = $dirtySku['barcode'];
                    $sku->insert();

                    $this->report('Inserted new Sku', "Inserted sku: {$dirtySku['productId']}-{$dirtySku['productVariantId']}-{$dirtySku['productSizeId']}-{$dirtySku['shopId']}", $dirtySku);

                    if ($sku->shopHasProduct->value == null) {
                        $sku->shopHasProduct->value = $sku->value;
                    }
                    if ($sku->shopHasProduct->price == null) {
                        $sku->shopHasProduct->price = $sku->price;
                    }
                    if ($sku->shopHasProduct->salePrice == null) {
                        $sku->shopHasProduct->salePrice = $sku->salePrice;
                    }
                    $sku->shopHasProduct->update();

                } else {
                    $this->debug('updateSkus', 'Update Sku', $dirtySku);

                    if (!isset($this->shopsConfig[$dirtySku['shopId']]['sync']) || $this->shopsConfig[$dirtySku['shopId']]['sync'] == null) {
                        $shopUpdFields = [];
                    } else {
                        try {
                            $shopUpdFields = $this->shopsConfig[$dirtySku['shopId']]['sync']['update']['skuFields'];
                        } catch (\Throwable $e) {
                            $shopUpdFields = [];
                        }
                    }

                    if (($sku->price == 0 && $price != 0) || (in_array('price', $shopUpdFields))) {
                        $sku->price = $price;
                    }
                    if (($sku->value == 0 && $value != 0) || (in_array('value', $shopUpdFields))) {
                        $sku->value = $value;
                    }
                    if (($sku->salePrice == 0 && $salePrice) || (in_array('salePrice', $shopUpdFields))) {
                        $sku->salePrice = $salePrice;
                    }

                    $intQty = $sku->stockQty;
                    $padding = $sku->padding;
                    $extQty = $dirtySku['extQty'];
                    $this->debug('updateSkus', 'Before: stockQty - ' . $intQty . ' padding - ' . $padding . ' extQty - ' . $extQty, $dirtySku);
                    /** NULLA é CAMBIATO */
                    if ($extQty == $intQty && $padding === 0) {

                    } /** PROBABILMENTE L'ESTERNO SI é ADATTATO */
                    elseif ($extQty == $intQty && $padding !== 0) {
                        $padding = 0;
                    } /** L'EQUAZIONE E' MANTENUTA, NON FACCIO NULLA */
                    elseif ($intQty === ($extQty + $padding)) {

                    } /** NULLA CAMBIA MA IL PADDING E' DIVERSO DA ZERO, SE ANDIAMO SOTTO ZERO CON LE QUANTITA' LE AZZERO PER SICUREZZA */
                    elseif ($intQty != ($extQty + $padding)) {
                        if (($extQty + $padding) >= 0) {
                            $intQty = $extQty + $padding;
                        } else if (($extQty + $padding) < 0) {
                            $intQty = 0;
                            $padding = $extQty + $padding;
                        }
                    }

                    $sku->stockQty = $intQty;
                    $sku->padding = $padding;

                    $this->debug('updateSkus', 'After: stockQty - ' . $intQty . ' padding - ' . $padding . ' extQty - ' . $extQty, $dirtySku);

                    $a = $sku->update();
                    if ($a != 1) {
                        throw new BambooException('no update occurred for: ' . $sku->printId());
                    }
                }

                foreach (explode(',', $dirtySku['dirtySkusId']) as $dsId) {
                    $dirtySkuEntity = $dirtySkuRepo->findOneBy(['id' => $dsId]);
                    $dirtySkuEntity->changed = 0;
                    $dirtySkuEntity->update();
                }

                $x++;

            } catch (\Throwable $e) {
                $this->error('Error while updating sku: ' . $dirtySku['dirtySkuId'], 'Some kind of error occurred, look at context', $e);
            }
        }
        $this->report("Updating Skus", "Skus updated: " . $x . ' over: ' . ($z), []);
    }

    /**
     * @param $shopId int
     * @param $priceType string
     * @param $price float
     * @return float
     */
    public function calculatePriceModifier($shopId, $priceType, $price)
    {
        try {
            $priceModifierConfig = $this->shopsConfig[$shopId]['sync']['priceModifier'][$priceType];
        } catch (\Throwable $e) {
            return $price;
        }

        if (!isset($priceModifierConfig['value'])) {
            $priceModifierConfig['value'] = 0;
        }

        if (isset($priceModifierConfig['mode'])) $priceModifierConfig['mode'] = 'percent';
        if (isset($priceModifierConfig['sign']) && $priceModifierConfig['sign'] == '-') $multiply = -1;
        else $multiply = 1;

        switch ($priceModifierConfig['mode']) {
            case 'flat':
                return $price + ($priceModifierConfig['value'] * $multiply);
                break;
            case 'hive-off':
                return SPriceToolbox::netPriceFromGross($price, $priceModifierConfig['value']);
                break;
            case 'percent':
            default:
                return $price + (($price * $priceModifierConfig['value'] / 100) * $multiply);
                break;
        }
    }

    public function recursiveIn_array($arr, $value, $type = "code")
    {
        return count(array_filter($arr, function ($var) use ($type, $value) {
                return $var[$type] === $value;
            })) !== 0;
    }
}