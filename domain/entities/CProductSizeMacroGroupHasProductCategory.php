<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductSizeMacroGroupHasProductCategoru
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
 *
 * @property CObjectCollection $productSizeMacroGroup
 */
class CProductSizeMacroGroupHasProductCategory extends AEntity
{
    protected $entityTable = 'ProductSizeMacroGroupHasProductCategory';
    protected $primaryKeys = ['productSizeMacroGroupId','productCategoryId'];
}