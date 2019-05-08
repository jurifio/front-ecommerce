<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CCampaignVisit;
use bamboo\domain\entities\CMarketplace;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\domain\entities\CProduct;

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
 * @date $date
 * @since 1.0
 */
class CMarketplaceAccountHasProductRepo extends ARepo
{

    public function addProductToMarketplaceAccount(CProduct $product, CMarketplaceAccount $marketplaceAccount,$cpc = null, $priceModifier = null)
    {
        $config = $marketplaceAccount->config;
        if(!is_null($cpc)) {
            $config['cpc'] = $cpc;
        }
        if(!is_null($priceModifier)) {
            $config['priceModifier'] = $priceModifier;
        }

        $marketplaceAccountHasProduct = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct')->getEmptyEntity();
        $marketplaceAccountHasProduct->productId = $product->id;
        $marketplaceAccountHasProduct->productVariantId = $product->productVariantId;
        $marketplaceAccountHasProduct->marketplaceAccountId = $marketplaceAccount->id;
        $marketplaceAccountHasProduct->marketplaceId = $marketplaceAccount->marketplaceId;

        if($marketplaceAccountHasProduct2 = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct')->findOneBy($marketplaceAccountHasProduct->getIds())) {
            $marketplaceAccountHasProduct = $marketplaceAccountHasProduct2;

            $marketplaceAccountHasProduct->priceModifier = $config['priceModifier'];
            if($marketplaceAccount->marketplace->type == 'cpc') {
                $marketplaceAccountHasProduct->fee = $config['cpc'];
            }

            if($marketplaceAccountHasProduct->isDeleted) {
                $marketplaceAccountHasProduct->isDeleted = 0;
                $marketplaceAccountHasProduct->isToWork = 1;
                $marketplaceAccountHasProduct->update();
                //reinsert
                $this->app->eventManager->triggerEvent('marketplace.product.add',['marketplaceAccountHasProductId'=>$marketplaceAccountHasProduct->printId()]);

            } else {
                $this->app->eventManager->triggerEvent('product.marketplace.change',['marketplaceAccountHasProductId'=>$marketplaceAccountHasProduct->printId()]);
            }
        } else {
            //insert
            $marketplaceAccountHasProduct->insert();
            $this->app->eventManager->triggerEvent('marketplace.product.add',['marketplaceAccountHasProductId'=>$marketplaceAccountHasProduct->printId()]);
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
}