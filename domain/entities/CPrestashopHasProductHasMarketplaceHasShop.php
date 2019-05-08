<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPrestashopHasProductHasMarketplaceHasShop
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 25/03/2019
 * @since 1.0
 *
 * @property CMarketplaceHasShop $marketplaceHasShop
 *
 */
class CPrestashopHasProductHasMarketplaceHasShop extends AEntity
{
    protected $entityTable = 'PrestashopHasProductHasMarketplaceHasShop';
    protected $primaryKeys = ['productId','productVariantId','marketplaceHasShopId'];

}