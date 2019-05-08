<?php

namespace bamboo\domain\repositories;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductEan;
use bamboo\domain\entities\CProductPublicSku;
use bamboo\domain\entities\CProductSku;

/**
 * Class CProductEanRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 23/04/2019
 * @since 1.0
 */
class CProductEanRepo extends ARepo
{
    /**
     * @param CProduct $product
     * @return bool
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     * @throws \bamboo\core\exceptions\RedPandaException
     *
     * see how many ean are associated and assign only not associated items
     */
    public function assignEanForProduct(CProduct $product): bool
    {
        /** @var CObjectCollection $productPublicSkus */
        $productPublicSkus = $product->productPublicSku;

        $skusCount = $productPublicSkus->count();

        if ($skusCount > 0) {

            $assignedEans = $this->productHasEan($product);

            if (is_null($assignedEans['father'])) {
                $this->assignFatherEanToProduct($product);
            }

            if (!isset($assignedEans['children-assigned']) || count($assignedEans['children-assigned']) == 0) {

                /** @var CObjectCollection $newEanCollection */
                $newEanCollection = $this->findFreeEan($skusCount);

                $this->matchEanWithSku($newEanCollection->toArray(), $productPublicSkus->toArray(), $product);
            } else {

                if(isset($assignedEans['children-not-assigned'])) {
                    $notAssignedSizesCount = count($assignedEans['children-not-assigned']);
                    /** @var CObjectCollection $newEanCollection */
                    $newEanCollection = $this->findFreeEan($notAssignedSizesCount);
                    $this->matchEanWithSku($newEanCollection->toArray(), $assignedEans['children-not-assigned'], $product);
                }
            }


            return true;
        }

        return false;
    }

    /**
     * @param array $eanArray
     * @param array $skuArray
     * @param CProduct $product
     * @return bool
     */
    public function matchEanWithSku(array $eanArray, array $skuArray, CProduct $product): bool
    {
        array_map(function ($eans, $skus) use ($product) {

            /** @var CProductEan $eans */
            /** @var CProductPublicSku $skus */
            $eans->productId = $skus->productId;
            $eans->productVariantId = $skus->productVariantId;
            $eans->productSizeId = $skus->productSizeId;
            $eans->used = 1;
            $eans->brandAssociate = $product->productBrandId;
            $eans->shopId = $product->productSku->getFirst()->shopId;
            $eans->update();

            /** @var CProductSku $productSku */
            $productSku = $skus->getActualSku();
            if (!is_null($productSku)) {
                $productSku->ean = $eans->ean;
                $productSku->update();
            }

        }, $eanArray, $skuArray);

        return true;
    }

    /**
     * @param CProduct $product
     * @return bool
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    protected function assignFatherEanToProduct(CProduct $product): bool
    {
        /** @var CProductEan $parentEan */
        $parentEan = $this->findFreeEan(1)->getFirst();
        $parentEan->productId = $product->id;
        $parentEan->productVariantId = $product->productVariantId;
        $parentEan->productSizeId = 0;
        $parentEan->usedForParent = 1;
        $parentEan->used = 1;
        $parentEan->brandAssociate = $product->productBrandId;
        $parentEan->shopId = $product->productSku->getFirst()->shopId;
        $parentEan->update();

        return true;
    }

    /**
     * @param int $number
     * @return CObjectCollection
     */
    public function findFreeEan(int $number): CObjectCollection
    {
        return $this->findBy(['used' => 0], 'limit ' . $number);
    }

    /**
     * @param CProduct $product
     * @return \bamboo\core\db\pandaorm\entities\AEntity|null
     */
    public function getParentEanForProduct(CProduct $product)
    {
        return $this->findOneBy(['productId' => $product->id, 'productVariantId' => $product->productVariantId, 'usedForParent' => 1]);
    }

    /**
     * @param CProduct $product
     * @return array
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     * @throws \bamboo\core\exceptions\RedPandaException
     *
     * this function check if product have ean and if find a mismatch between
     * ProductEan and ProductSku it fix the error
     */
    public function productHasEan(CProduct $product): array
    {
        /** @var CObjectCollection $productPublicSkus */
        $productPublicSkus = $product->productPublicSku;
        $res = [];

        //check father
        /** @var CProductEan $productEan */
        $productEan = $this->findOneBy([
            'productId' => $product->id,
            'productVariantId' => $product->productVariantId,
            'usedForParent' => 1]);

        $res['father'] = (!is_null($productEan)) ? $productEan->id : null;

        /** @var CProductPublicSku $productPublicSku */
        foreach ($productPublicSkus as $productPublicSku) {

            /** @var CProductSku $productSku */
            $productSku = $productPublicSku->getActualSku();

            //check child
            /** @var CProductEan $productEanChild */
            $productEanChild = $this->findOneBy([
                'productId' => $productPublicSku->productId,
                'productVariantId' => $productPublicSku->productVariantId,
                'productSizeId' => $productSku->productSizeId]);

            if (!is_null($productEanChild)) {

                /** @var CProductSku $productSku */
                $productSku = $productPublicSku->getActualSku();

                if(!is_null($productSku)){
                    if(is_null($productSku->ean)){
                        $productSku->ean = $productEanChild->ean;
                        $productSku->update();
                    }
                }

                $res['children-assigned'][] = $productPublicSku;
            } else {
                $res['children-not-assigned'][] = $productPublicSku;
            }

        }

        return $res;
    }

}