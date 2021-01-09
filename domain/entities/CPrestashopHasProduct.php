<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPrestashopHasProduct
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 05/09/2018
 * @since 1.0
 *
 * @property CProduct $product
 * @property CObjectCollection $marketplaceHasShop
 * @property CObjectCollection $prestashopHasProductHasMarketplaceHasShop
 * @property CObjectCollection $productStatusMarketplace
 */
class CPrestashopHasProduct extends AEntity
{

    CONST UPDATED = 'Inserito';
    CONST TOUPDATE = 'Da aggiornare';
    CONST TOBOOKINGDELETE = 'Da Cancellare';
    CONST DELETED ="Cancellato";


    protected $entityTable = 'PrestashopHasProduct';
    protected $primaryKeys = ['productId','productVariantId'];

    public function getShopsForProduct() {
        /** @var CObjectCollection $phphmhsColl */
        $phphmhsColl = $this->prestashopHasProductHasMarketplaceHasShop;

        if($phphmhsColl->count() == 0) return false;

        $prestashopShopIds = [];
        /** @var CPrestashopHasProductHasMarketplaceHasShop $phphmhs */
        foreach ($phphmhsColl as $phphmhs){
            $prestashopShopIds[] = $phphmhs->marketplaceHasShop->prestashopId;
        }

        return $prestashopShopIds;
    }

}