<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductSizeGroup
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 * @property CObjectCollection $productSizeGroupHasProductSize
 * @property CObjectCollection $productSize
 * @property CObjectCollection $product
 * @property CProductSizeMacroGroup $productSizeMacroGroup
 */
class CProductSizeGroup extends AEntity
{
    protected $entityTable = 'ProductSizeGroup';
    protected $primaryKeys = ['id'];
}