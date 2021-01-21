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
 * @property CObjectCollection $aggregatorHasShop
 * @property CObjectCollection $marketplaceAccountHasProduct
 * @property CObjectCollection $productStatusAggregator
 * @property CObjectCollection $productSku;
 */
class CAggregatorHasProduct extends AEntity
{

    CONST UPDATED = 'Inserito';
    const MANUAL ='Inserito Manualmente';
    CONST TOUPDATE = 'Da aggiornare';
    CONST TOBOOKINGDELETE = 'Da Cancellare';
    CONST DELETED ="Cancellato";


    protected $entityTable = 'AggregatorHasProduct';
    protected $primaryKeys = ['productId','productVariantId','aggregatorHasShopId'];

    public function getShopsForProduct() {
        /** @var CObjectCollection $mahpColl */
        $mahpColl = $this->marketplaceAccountHasProduct;

        if($mahpColl->count() == 0) return false;

        $marketplaceProductIds = [];
        /** @var CMarketplaceAccountHasProduct $mahp */
        foreach ($mahpColl as $mahp){
            $marketplaceProductIds[] = $mahp->aggregatorHasShop->marketplaceProductId;
        }

        return $marketplaceProductIds;
    }

}