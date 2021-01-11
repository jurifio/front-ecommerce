<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CMarketplace
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 20/09/2018
 * @since 1.0
 *
 * @property CShop $shop
 * @property CMarketplace $marketplace
 *
 */
class CAggregatorHasShop extends AEntity
{
    protected $entityTable = 'AggregatorHasShop';
    protected $primaryKeys = ['id'];
}