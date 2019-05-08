<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductSheetPrototype
 * @package bamboo\app\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 01/02/2016
 * @since 1.0
 *
 * @property CObjectCollection $productDetailLabel
 * @property CObjectCollection $productSheetPrototypeHasProductDetailLabel
 *
 */
class CProductSheetPrototype extends AEntity
{
    protected $entityTable = 'ProductSheetPrototype';
    protected $primaryKeys = ['id'];
}