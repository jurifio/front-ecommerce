<?php

namespace bamboo\ecommerce\business;

use bamboo\core\application\AApplication;
use bamboo\core\ecommerce\IBillingLogic;
use bamboo\domain\entities\COrderLine;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductSku;

/**
 * Class CMarkUpOnValueLogic
 * @package bamboo\app
 */
class CMarkUpOnValueLogic implements IBillingLogic
{

    protected $app;

    public function __construct(AApplication $app)
    {
        $this->app = $app;
    }

    /**
     * Calcola la friend revenue a partire da una order line
     * @param COrderLine $orderLine
     * @return bool|float
     */
    public function calculateFriendReturn(COrderLine $orderLine)
    {
        try {
            $sku = CProductSku::defrost($orderLine->frozenSku);
            $sku->setEntityManager($this->app->entityManagerFactory->create('ProductSku'));
        } catch (\Throwable $e) {
            $repo = \Monkey::app()->repoFactory->create('ProductSku');
            $sku = $repo->findOne(['productId' => $orderLine->productId, 'productVariantId' => $orderLine->productVariantId, 'productSizeId' => $orderLine->productSizeId, 'shopId' => $orderLine->shopId]);
        }

        if (!isset($sku) || is_null($sku)) {
            return false;
        }

        return $this->calculateFriendReturnSku($sku);
    }

    /**
     * Calcola la friend revenue a partire dal ProductSku
     * @param CProductSku $sku
     * @return float
     */
    public function calculateFriendReturnSku(CProductSku $sku)
    {
        $multiplier = $this->getMultiplier($sku->product, $sku);

        $res = $this->friendPriceCalculation($sku->value, $multiplier);
        return $res;
    }

    /**
     * Calcola il moltiplicatore per un prodotto in questo momento
     * @param CProduct $prod
     * @param CProductSku $sku
     * @return mixed
     * @throws \Exception
     */
    protected function getMultiplier(CProduct $prod, CProductSku $sku)
    {
        if ($prod->productVariantId !== $sku->productVariantId) throw new \Exception('Product and sku must to have the same productVariantId');
        $repo = \Monkey::app()->repoFactory->create('Shop');
        $shop = $repo->findOne(['id' => $sku->shopId]);
        if (!$prod->productSeason->isActive) {
            $multiplier = $shop->pastSeasonMultiplier;
        } elseif ($sku->isOnSale) {
            $multiplier = $shop->saleMultiplier;
        } else {
            $multiplier = $shop->currentSeasonMultiplier;
        }
        return $multiplier;
    }

    /**
     * Calcola la friend revenue
     * @param $value
     * @param $multiplier
     * @return float
     */
    private function friendPriceCalculation($value, $multiplier)
    {
        return round($value + ($value * ($multiplier / 100)), 2);
    }

} 