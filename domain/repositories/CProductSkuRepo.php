<?php


namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\ecommerce\IBillingLogic;
use bamboo\core\exceptions\BambooConfigException;
use bamboo\domain\entities\CProductPublicSku;
use bamboo\domain\entities\CProductSize;
use bamboo\domain\entities\CProductSku;
use bamboo\domain\entities\CShop;

/**
 * Class CProductSkuRepo
 * @package bamboo\domain\repositories
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
class CProductSkuRepo extends ARepo
{
    /**
     * Ritorna tutti gli sku a partire da un prodotto, pescando i parametri dai parametri
     * @return \bamboo\core\base\CObjectCollection
     * @deprecated
     */
    public function listByProduct()
    {
        $sql = "SELECT DISTINCT productId, productVariantId, productSizeId 
                FROM ProductPublicSku psk 
                  JOIN ProductSize ps ON psk.productSizeId = ps.id 
              WHERE productId = ? AND productVariantId = ? ORDER BY ps.name";
        $args = $this->app->router->getMatchedRoute()->getComputedFilters();
        return $this->em()->findBySql($sql, array($args['item'], $args['variant']));
    }

    /**
     * Scarica una quantità dagli sku mettendo anche il padding
     * @param CProductSku $sku
     * @return bool
     */
    public function saveQty(CProductSku $sku)
    {
        $qty = 1;
        try {
            if ($sku->stockQty < 1) return false;
            else {
                $sku->stockQty = $sku->stockQty - $qty;
                $sku->padding = $sku->padding - $qty;
                $sku->update();
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Assegna un nuovo ean allo sku pescandolo dal secchio degli Ean vuoti
     * @param CProductSku $productSku
     * @return \bamboo\core\db\pandaorm\entities\IEntity|null
     * @throws BambooConfigException
     * @transaction
     */
    public function assignNewEan(CProductSku $productSku)
    {
        $ean = \Monkey::app()->repoFactory->create('EanBucket')->findOneBy(['isAssigned' => 0]);
        if (is_null($ean)) throw new BambooConfigException('Could not find an unassigned Ean');
        \Monkey::app()->repoFactory->beginTransaction();
        $productSku->ean = $ean->ean;
        $productSku->update();
        $ean->isAssigned = 1;
        $ean->update();
        \Monkey::app()->repoFactory->commit();
        return $ean->ean;
    }

    /**
     * Prendere la taglia standard per uno sku
     * @param CProductSku $productSku
     * @return CProductSize|null
     */
    public function getStandardSizeFor(CProductSku $productSku)
    {
        try {
            if ($productSku->product->productSizeGroup->locale == 'ST') {
                return $productSku->productSize;
            } else {
                return \Monkey::app()->repoFactory->create('ProductSize')->findStandardFor($productSku->product->productSizeGroup, $productSku->productSize);
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Aggiorna tutti i prezzi degli sku di un prodotto-shop ad un valore
     * @param $productId
     * @param $productVariantId
     * @param $shopId
     * @param $value
     * @param $price
     * @param int $salePrice
     * @return \bamboo\core\base\CObjectCollection
     */
    public function updateSkusPrices($productId, $productVariantId, $shopId, $value, $price, $salePrice = 0)
    {
        $skuRepo = \Monkey::app()->repoFactory->create('ProductSku');
        $skus = $skuRepo->findBy(['productId' => $productId, 'productVariantId' => $productVariantId, 'shopId' => $shopId]);
        foreach ($skus as $s) {
            $s->value = $value;
            $s->price = $price;
            if ($salePrice) $s->salePrice = $salePrice;
            $s->update();
        }
        $this->levelPrice($productId, $productVariantId);
        return $skus;
    }

    /**
     * Livella i prezzi di tutti li sku a quello piu basso (non 0)
     * @param $id
     * @param $productVariantId
     * @param int $productSizeId
     * @return \bamboo\core\base\CObjectCollection
     */
    public function levelPrice($id, $productVariantId, $productSizeId = 0)
    {
        $skuRepo = \Monkey::app()->repoFactory->create('ProductSku');
        $skuParams = ['productId' => $id, 'productVariantId' => $productVariantId];
        if ($productSizeId) $skuParams['productSizeId'] = $productSizeId;
        $sku = $skuRepo->findBy($skuParams);

        $skuPrice = null;
        $skuSalePrice = null;

        foreach ($sku as $s) {
            if (is_null($skuPrice) || $s->price < $skuPrice) $skuPrice = $s->price;
            if ((is_null($skuSalePrice) || $s->price < $skuPrice) && (0 < $s->price)) $skuSalePrice = $s->salePrice;
        }

        foreach ($sku as $s) {
            $s->price = $skuPrice;
            $s->salePrice = $skuSalePrice;
            $s->update();
        }
        return $sku;
    }

    /**
     * @param CProductPublicSku $productPublicSku
     * @param CShop|null $shop
     * @param bool $onlyDisposable
     * @return CProductSku|null
     */
    public function findOneSkuFromPublicSku(CProductPublicSku $productPublicSku, CShop $shop = null, $onlyDisposable = true)
    {
        return $this->findProductSkusFromPublicSku($productPublicSku, $shop, $onlyDisposable)->getFirst();
    }

    /**
     * @param CProductPublicSku $productPublicSku
     * @param CShop|null $shop
     * @param bool $onlyDisposable
     * @return \bamboo\core\base\CObjectCollection
     */
    public function findProductSkusFromPublicSku(CProductPublicSku $productPublicSku, CShop $shop = null, $onlyDisposable = true)
    {
        $sql = "SELECT DISTINCT ps.*
        FROM ProductPublicSku pps 
          JOIN Product p ON pps.productId = p.id AND pps.productVariantId = p.productVariantId
          JOIN ProductSizeGroup psg ON p.productSizeGroupId = psg.id
          JOIN ProductSizeGroupHasProductSize psghps ON psg.id = psghps.productSizeGroupId AND psghps.productSizeId = pps.productSizeId
          JOIN ShopHasProduct shp ON p.id = shp.productId AND p.productVariantId = shp.productVariantId
          JOIN ProductSizeGroup psg2 ON shp.productSizeGroupId = psg2.id AND psg2.productSizeMacroGroupId = psg.productSizeMacroGroupId
          JOIN ProductSizeGroupHasProductSize psghps2 ON psg2.id = psghps2.productSizeGroupId AND psghps.position = psghps2.position
          JOIN ProductSku ps ON ps.productId = p.id AND ps.productVariantId = p.productVariantId AND ps.productSizeId = psghps2.productSizeId
        WHERE if(:onlyDisposable = 1, ps.stockQty > 0, true) AND 
          pps.productId = :productId AND 
          pps.productVariantId = :productVariantId AND
          pps.productSizeId = :productSizeId AND 
          ps.shopId = ifnull(:shopId, ps.shopId)";
        $bind = ['onlyDisposable' => $onlyDisposable ? 1 : 0] + $productPublicSku->getIds() + ['shopId' => $shop ? $shop->id : null];

        return $this->findBySql($sql, $bind);
    }

    /**
     * Cerca uno sku disponibile partendo da prodotto-variante-taglia-shop
     * modifica resa necessaria dai gruppi taglia multipli
     * @param CProductSku $productSku
     * @return CProductSku|null
     */
    public function findOneDisposableSkuFromSku(CProductSku $productSku)
    {
        return $this->findDisposableSkusFromSku($productSku)->getFirst();
    }

    /**
     * @param CProductSku $productSku
     * @return \bamboo\core\base\CObjectCollection
     */
    public function findDisposableSkusFromSku(CProductSku $productSku)
    {
        return $this->findBySql(
            "SELECT DISTINCT pskOut.*
                    FROM
                      ProductSku pskIn 
                      JOIN ProductSku pskOut ON (pskIn.productId, pskIn.productVariantId) = (pskOut.productId, pskOut.productVariantId)
                      JOIN ShopHasProduct shp1
                        ON (pskIn.productId, pskIn.productVariantId, pskIn.shopId) = (shp1.productId, shp1.productVariantId,shp1.shopId)
                      JOIN ShopHasProduct shp2
                        ON (pskOut.productId, pskOut.productVariantId, pskOut.shopId) = (shp2.productId, shp2.productVariantId,shp2.shopId)
                      JOIN ProductSizeGroup psg ON shp1.productSizeGroupId = psg.id
                      JOIN ProductSizeGroupHasProductSize psghpsIn ON psg.id = psghpsIn.productSizeGroupId AND pskIn.productSizeId = psghpsIn.productSizeId
                      JOIN ProductSizeGroupHasProductSize psghpsOut
                        ON psghpsIn.position = psghpsOut.position AND psghpsOut.productSizeId = pskOut.productSizeId AND shp2.productSizeGroupId = psghpsOut.productSizeGroupId
                    WHERE pskIn.productId = :productId
                      AND pskIn.productVariantId = :productVariantId
                      AND pskIn.productSizeId = :productSizeId
                      AND pskIn.shopId = :shopId
                      AND pskOut.stockQty > 0 ",
            $productSku->getIds());
    }

    /**
     * Calculate friend revenue for a sku
     * @param CProductSku $productSku
     * @return mixed
     */
    public function calculateFriendRevenue(CProductSku $productSku)
    {
        $pricer = $productSku->shop->billingLogic;
        /** @var IBillingLogic $pricer */
        $pricer = new $pricer($this->app);
        return $pricer->calculateFriendReturnSku($productSku);
    }

}
