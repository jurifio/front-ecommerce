<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductDetailTranslation
 * @package bamboo\app\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 19/01/2016
 * @since 1.0
 */
class CProductHistory extends AEntity
{
    protected $entityTable = 'ProductHistory';
    protected $primaryKeys = ['id','productId','productVariantId','userId'];
}