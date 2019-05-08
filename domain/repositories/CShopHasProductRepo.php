<?php
namespace bamboo\domain\repositories;

use bamboo\core\exceptions\BambooException;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CProductSize;
use bamboo\domain\entities\CProductSizeGroup;
use bamboo\domain\entities\CProductSku;
use bamboo\domain\entities\CShop;
use bamboo\domain\entities\CShopHasProduct;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CShopHasProductRepo
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
class CShopHasProductRepo extends ARepo
{
    const INCOMPATIBLE_PRODUCT_SIZE_EXCEPTION_CODE = 435;

    /**
     * @param $idOrString
     * @param null $productVariantId
     * @param null $shopId
     * @return bool
     * @throws BambooException
     */
    public function isReleased($idOrString, $productVariantId = null, $shopId = null) {
        if (null === $productVariantId) {
            $shp = $this->findOneByStringId($idOrString);
            $stringId = $idOrString;
        } else {
            $shp = $this->findOne([$idOrString, $productVariantId, $shopId]);
            $stringId = $idOrString . '-' . $productVariantId . '-' . $shopId;
        }
        if (!$shp) throw new BambooException('Can\'t find the product');
        $logR = \Monkey::app()->repoFactory->create('Log');

        /** @var CStorehouseOperationRepo */

        /*
         CStorehouseOperationRepo::PUBLISH_RELEASE = 'released';
         CStorehouseOperationRepo::PUBLISH_OUT_OF_STOCK = 'out-of-stock';
         CStorehouseOperationRepo::PUBLISH_RESTOCK = 'restocked';
         */


        $lastRelease = $logR->getLastEntry([
            'entityName' => 'ShopHasProduct',
            'actionName' => 'ReleaseProduct',
            'eventValue' => CStorehouseOperationRepo::PUBLISH_RELEASE,
            'stringId' => $stringId
        ]);

        $lastSeasonChange = $logR->getLastEntry([
            'entityName' => 'ShopHasProduct',
            'actionName' => 'ChangeSeason',
            'stringId' => $stringId
        ]);
        if (($lastRelease && !$lastSeasonChange) ||
            ($lastSeasonChange && $lastSeasonChange->time < $lastRelease->time))
        {
            return true;
        } else false;
    }

    /**
     * @param $idOrString
     * @param null $productVariantId
     * @param null $shopId
     * @return bool
     * @throws BambooException
     */
    public function isOutOfStock($idOrString, $productVariantId = null, $shopId = null) {
        if (null === $productVariantId) {
            $p = $this->findOneByStringId($idOrString);
            $stringId = $idOrString;
        } else {
            $p = $this->findOne([$idOrString, $productVariantId, $shopId]);
            $stringId = $idOrString . '-' . $productVariantId . '-' . $shopId;
        }
        if (!$p) throw new BambooException('Can\'t find the product');
        $logR = \Monkey::app()->repoFactory->create('Log');
        $lastRelEvent = $logR->getLastEntry([
            'entityName' => 'ShopHasProduct',
            'actionName' => 'ReleaseProduct',
            'stringId' => $stringId
        ]);

        if (CStorehouseOperationRepo::PUBLISH_OUT_OF_STOCK === $lastRelEvent->eventValue) return true;
        return false;
    }

    /**
     * @param CShopHasProduct $shopHasProduct
     * @param CProductSizeGroup $productSizeGroup
     * @param bool $ignoreError
     * @return CShopHasProduct
     * @throws BambooException
     */
    public function changeShopHasProductProductSizeGroup(CShopHasProduct $shopHasProduct, CProductSizeGroup $productSizeGroup, $ignoreError = false) {
        if($shopHasProduct->productSizeGroupId == $productSizeGroup->id) return $shopHasProduct;
        if(!$ignoreError) {
            foreach ($shopHasProduct->product->shopHasProduct as $otherShopHasProduct) {
                if($otherShopHasProduct->productSizeGroupId != $productSizeGroup->id) throw new BambooException('Gruppo Taglia incompatibili con i gruppi taglia degli altri Shop');
            }
        }

        if(!empty($this->searchIncompatibleSizeInProductSizeGroup($shopHasProduct, $productSizeGroup))) {
            throw new BambooException('Gruppo Taglia incompatibile con gli sku esistenti',[],self::INCOMPATIBLE_PRODUCT_SIZE_EXCEPTION_CODE);
        }

        $shopHasProduct->productSizeGroupId = $productSizeGroup->id;
        $shopHasProduct->update();

        if($shopHasProduct->productSizeGroup->productSizeMacroGroup->name != $shopHasProduct->product->productSizeGroup->productSizeMacroGroup->name) {
            $shopHasProduct->product->productSizeGroupId = $shopHasProduct->productSizeGroupId;
            $shopHasProduct->product->update();
        }
        return $shopHasProduct;
    }

    /**
     * @param CShopHasProduct $shopHasProduct
     * @param CProductSizeGroup $productSizeGroup
     * @return CProductSku[]
     */
    public function searchIncompatibleSizeInProductSizeGroup(CShopHasProduct $shopHasProduct, CProductSizeGroup $productSizeGroup) {
        $res = [];
        foreach ($shopHasProduct->productSku as $productSku) {
            if(!$productSizeGroup->productSizeGroupHasProductSize->findOneByKey('productSizeId',$productSku->productSizeId)) {
                $res[] = $productSku;
            }
        }
        return $res;
    }

}