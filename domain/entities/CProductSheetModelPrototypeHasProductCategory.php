<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductSheetActual
 * @package bamboo\app\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CProductSheetModelPrototypeHasProductCategory extends AEntity
{
    protected $entityTable = 'ProductSheetModelPrototypeHasProductCategory';
    protected $primaryKeys = ['productSheetModelPrototypeId'];
}