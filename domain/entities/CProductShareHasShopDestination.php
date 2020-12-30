<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductHasShopDestination
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *A
 * @date 24/05/2019
 * @since 1.0
 */
class CProductShareHasShopDestination extends AEntity
{
    protected $entityTable = 'ProductShareHasShopDestination';
    protected $primaryKeys = ['productId','productVariantId','shopId'];
}