<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductSheetModelPrototypeMacroCategoryGroup
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 11/06/2018
 * @since 1.0
 *
 * @property CObjectCollection $productSheetModelPrototypeCategoryGroup
 *
 */
class CProductSheetModelPrototypeMacroCategoryGroup extends AEntity
{

    CONST DEFAULT = 192;

    protected $entityTable = 'ProductSheetModelPrototypeMacroCategoryGroup';
    protected $primaryKeys = ['id'];
}