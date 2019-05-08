<?php
namespace bamboo\domain\repositories;

use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\CProduct;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;

/**
 * Class CProductRepo
 * @package bamboo\domain\repositories
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * @since 1.0
 */
class CStorehouseOperationLineRepo extends ARepo
{
    /**
     * Crea la linea di un movimento a partire dai singoli dati, se lo sku non esiste lo crea
     * @param $productId
     * @param $productVariantId
     * @param $productSizeId
     * @param $shopId
     * @param $qtyToMove
     * @param $storehouseOperationId
     * @param $storehouseId
     * @return \bamboo\core\db\pandaorm\entities\AEntity|\bamboo\core\db\pandaorm\entities\IEntity|null
     * @throws \Exception
     */
    public function createMovementLine($productId, $productVariantId, $productSizeId, $shopId, $qtyToMove, $storehouseOperationId, $storehouseId)
    {
        if ($qtyToMove != 0) {
            $solRepo = \Monkey::app()->repoFactory->create('StorehouseOperationLine');

            \Monkey::app()->eventManager->triggerEvent(
                'before_createMovementLine',
                [
                    'productId' => $productId,
                    'productVariantId' => $productVariantId,
                    'productSizeId' => $productSizeId,
                    'shopId' => $shopId,
                    'movedQty' => $qtyToMove,
                    'storehouseOperationId' => $storehouseOperationId,
                    'storehouseId' => $storehouseId
                ]);

            $skR = \Monkey::app()->repoFactory->create('ProductSku');
            $sku = $skR->findOne([$productId, $productVariantId, $productSizeId, $shopId]);

            if (!$sku) {
                $pR = \Monkey::app()->repoFactory->create('ShopHasProduct');
                $shp = $pR->findOne([$productId, $productVariantId, $shopId]);
                if (!$shp) throw new BambooException('Non posso creare un movimento su un prodotto inesistente o di cui non sono stati inseriti i prezzi per il friend corrente');

                $salePrice = (!$shp->salePrice) ? 0 : $shp->salePrice;

                $newSku = $skR->getEmptyEntity();
                $newSku->productId = $productId;
                $newSku->productVariantId = $productVariantId;
                $newSku->productsizeId = $productSizeId;
                $newSku->shopId = $shopId;
                $newSku->currencyId = 1;
                $newSku->stockQty = 0;
                $newSku->padding = 0;
                $newSku->value = $shp->value;
                $newSku->price = $shp->price;
                $newSku->salePrice = $salePrice;
                $newSku->insert();
            }

            $sol = $solRepo->getEmptyEntity();
            $sol->storehouseOperationId = $storehouseOperationId;
            $sol->shopId = $shopId;
            $sol->storehouseId = $storehouseId;
            $sol->productId = $productId;
            $sol->productVariantId = $productVariantId;
            $sol->productSizeId = $productSizeId;
            $sol->qty = $qtyToMove;
            $sol->insert();
        }
    }
}
