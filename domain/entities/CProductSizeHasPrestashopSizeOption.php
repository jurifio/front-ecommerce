<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductSizeHasPrestashopSizeOption
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 22/02/2019
 * @since 1.0
 */
class CProductSizeHasPrestashopSizeOption extends AEntity
{
    protected $entityTable = 'ProductSizeHasPrestashopSizeOption';
    protected $primaryKeys = ['productSizeId'];

}