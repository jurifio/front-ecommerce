<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CCampaignVisit;
use bamboo\domain\entities\CMarketplace;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CMarketplaceHasShop;
use bamboo\domain\entities\CMarketplaceHasProductAssociate;
use bamboo\domain\entities\CProduct;
use bamboo\domain\repositories\BambooException;

/**
 * Class CMarketplaceAccountHasProductRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 28/09/2018
 * @since 1.0
 */
class CMarketplaceHasProductAssociateRepo extends ARepo
{

    public function addProductToMarketPlacePrestaShop(CProduct $product,$shopId, $marketPlaceId,$prestashopId,$typeRetouchPrice,$amount,$marketplaceHasShopId)
    {
        $findActualPrice=\Monkey::app()->repoFactory->create('ProductPublicSku')->findOneBy(['productId'=>$product->id, 'productVariantId'=>$product->productVariantId]);
        $actualPrice=$findActualPrice->price;
        $actualpriceSale=$findActualPrice->salePrice;
$price=0;
        switch ($typeRetouchPrice){
            case 1:
                $price=$actualPrice+($actualPrice/100*$amount);
                $priceSale=$actualpriceSale+($actualpriceSale/100*$amount);
                break;
            case 2:
                $price=$actualPrice-($actualPrice/100*$amount);
                $priceSale=$actualPrice-($actualpriceSale/100*$amount);

                break;
            case 3:
                $price=$actualPrice+$amount;
                $priceSale=$actualpriceSale+$amount;

                break;
            case 4:
                $price=$actualPrice-$amount;
                $priceSale=$actualpriceSale -$amount;

                break;
            case 5:
                $price=$actualPrice;
                $priceSale=$actualpriceSale;

                break;
        }
        round($price,1,PHP_ROUND_HALF_DOWN);


       $marketplaceHasProductAssociate2 = \Monkey::app()->repoFactory->create('MarketplaceHasProductAssociate')->findOneBy(['productId'=>$product->id,'productVariantId'=>$product->productVariantId,'shopId'=>$shopId,'prestashopId'=>$prestashopId,'marketplaceId'=>$marketPlaceId,'marketPlaceHasShopId'=>$marketplaceHasShopId]);
        if ($marketplaceHasProductAssociate2 == null) {
            /* @var $marketplaceHasProductAssociate CRepo */
            $marketplaceHasProductAssociate = \Monkey::app()->repoFactory->create('MarketplaceHasProductAssociate')->getEmptyEntity();
            $marketplaceHasProductAssociate->productId = $product->id;
            $marketplaceHasProductAssociate->productVariantId = $product->productVariantId;
            $marketplaceHasProductAssociate->shopId = $shopId;
            $marketplaceHasProductAssociate->prestashopId = $prestashopId;
            $marketplaceHasProductAssociate->statusPublished = 0;
            $marketplaceHasProductAssociate->marketplaceId = $marketPlaceId;
            $marketplaceHasProductAssociate->typeRetouchPrice = $typeRetouchPrice;
            $marketplaceHasProductAssociate->amount = $amount;
            $marketplaceHasProductAssociate->price = $actualPrice;
            $marketplaceHasProductAssociate->priceMarketplace=$price;
            $marketplaceHasProductAssociate->marketPlaceHasShopId = $marketplaceHasShopId;
            $marketplaceHasProductAssociate->isOnSale=0;
            $marketplaceHasProductAssociate->typeSale=0;
            $marketplaceHasProductAssociate->priceSale=$priceSale;
            $marketplaceHasProductAssociate->percentSale=null;
            $marketplaceHasProductAssociate->titleSale=null;
            $marketplaceHasProductAssociate->titleTextSale='';

            $marketplaceHasProductAssociate->insert();
            $productEanParent=\Monkey::app()->repoFactory->create('ProductEan')->findOneBy(['productId'=>$product->id,'productVariantId'=>$product->productVariantId,'productSizeId'=>0,'usedForParent'=>1,'used'=>1]);
            if ($productEanParent!=null){


            }else{
                /* @var  $productEanAssign CRepo*/
                $productEanAssign=\Monkey::app()->repoFactory->create('ProductEan')->findOneBy(['used'=>0]);
                $productEanAssign->productId=$product->id;
                $productEanAssign->productVariantId=$product->productVariantId;
                $productEanAssign->productSizeId='0';
                $productEanAssign->usedForParent='1';
                $productEanAssign->used='1';
                $findBrand=\Monkey::app()->repoFactory->create('Product')->findOneBy(['id'=>$product->id,'productVariantId'=>$product->productVariantId]);
                $brandAssociate=$findBrand->productBrandId;
                $productEanAssign->brandAssociate=$brandAssociate;
                $productEanAssign->shopId=$shopId;
                $productEanAssign->update();

            }


        }else {
            $marketplaceHasProductAssociate2->productId = $product->id;
            $marketplaceHasProductAssociate2->productVariantId = $product->productVariantId;
            $marketplaceHasProductAssociate2->shopId = $shopId;
            $marketplaceHasProductAssociate2->prestashopId = $prestashopId;
            $marketplaceHasProductAssociate2->statusPublished = 2;
            $marketplaceHasProductAssociate2->marketplaceId = $marketPlaceId;
            $marketplaceHasProductAssociate2->typeRetouchPrice = $typeRetouchPrice;
            $marketplaceHasProductAssociate2->amount = $amount;
            $marketplaceHasProductAssociate2->price = $actualPrice;
            $marketplaceHasProductAssociate2->priceMarketplace = $price;
            $marketplaceHasProductAssociate2->marketPlaceHasShopId = $marketplaceHasShopId;
            $marketplaceHasProductAssociate2->isOnSale=0;
            $marketplaceHasProductAssociate2->typeSale=0;
            $marketplaceHasProductAssociate2->priceSale=$priceSale;
            $marketplaceHasProductAssociate2->percentSale=null;
            $marketplaceHasProductAssociate2->titleSale=null;
            $marketplaceHasProductAssociate2->titleTextSale='';
            $marketplaceHasProductAssociate2->update();
        }



    }

    /**
     * @param string|CMarketplaceAccountHasProduct $marketplaceAccountHasProduct
     * @return bool
     */
    public function deleteProductFromMarketplaceAccount($marketplaceAccountHasProduct)
    {
        try {
            if(!($marketplaceAccountHasProduct instanceof CMarketplaceAccountHasProduct)) {
                $stringId = $marketplaceAccountHasProduct;
                $marketplaceAccountHasProduct = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct')->findOneByStringId($stringId);
            }

            if (null == $marketplaceAccountHasProduct) {
                $marketplaceAccountHasProduct = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct')->getEmptyEntity();
                $marketplaceAccountHasProduct->readId($stringId);
                $marketplaceAccountHasProduct->isDeleted = 1;
                $marketplaceAccountHasProduct->isRevised = 0;
                $marketplaceAccountHasProduct->isToWork = 0;
                $marketplaceAccountHasProduct->insert();
            } else {
                $marketplaceAccountHasProduct->isRevised = 0;
                $marketplaceAccountHasProduct->isDeleted = 1;
                $marketplaceAccountHasProduct->update();
            }
            $this->app->eventManager->triggerEvent('product.marketplace.change', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct->printId()]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * read the flags of $marketplaceAccountHasProduct and returns the action, null if no action is needed,
     * also corrects the wrong flag configurations if they occurs
     * @param CMarketplaceAccountHasProduct $marketplaceAccountHasProduct
     * @return null|string
     */
    public function detectAction(CMarketplaceAccountHasProduct $marketplaceAccountHasProduct)
    {
        if (!$marketplaceAccountHasProduct->isToWork && !$marketplaceAccountHasProduct->isDeleted) {
            foreach ($marketplaceAccountHasProduct->product->productSku as $productSku) {
                if ($productSku->stockQty > 0) return 'revise';
            }
            $marketplaceAccountHasProduct->isDeleted = 1;
            $marketplaceAccountHasProduct->update();
            return 'end';
        } elseif (!$marketplaceAccountHasProduct->isToWork &&
            !$marketplaceAccountHasProduct->isRevised &&
            $marketplaceAccountHasProduct->isDeleted
        ) return 'end';
        elseif ($marketplaceAccountHasProduct->isToWork &&
            !$marketplaceAccountHasProduct->isRevised &&
            !$marketplaceAccountHasProduct->isDeleted
        ) return 'add';
        elseif (!$marketplaceAccountHasProduct->isToWork &&
            $marketplaceAccountHasProduct->isRevised &&
            $marketplaceAccountHasProduct->isDeleted &&
            !$marketplaceAccountHasProduct->hasError
        ) return null;

        $marketplaceAccountHasProduct->isToWork = 0;
        $marketplaceAccountHasProduct->isRevised = 1;
        $marketplaceAccountHasProduct->isDeleted = 1;
        $marketplaceAccountHasProduct->hasError = 0;
        $marketplaceAccountHasProduct->update();
        return null;
    }
    public function updateProductStatus(int $productId, int $productVariantId, $status = 2) : CPrestashopHasProduct{

        /** @var CPrestashopHasProduct $prestashopHasProduct */
        $prestashopHasProduct = $this->findOneBy(
            [
                'productId' => $productId,
                'productVariantId' => $productVariantId
            ]);

        $prestashopHasProduct->statusPublished = $status;
        $prestashopHasProduct->update();

        return $prestashopHasProduct;
    }
}