<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CMarketplaceAccountHasProductSku
 * @package bamboo\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 02/08/2016
 * @since 1.0
 */
class CMarketplaceAccountHasProductSku extends AEntity
{
    protected $entityTable = 'MarketplaceAccountHasProductSku';
	protected $primaryKeys = ['productId','productVariantId','marketplaceId','marketplaceAccountId','productSizeId'];
}