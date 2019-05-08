<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPrestashopHasProductAttribute
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 05/09/2018
 * @since 1.0
 */
class CPrestashopHasProductAttribute extends AEntity
{
    protected $entityTable = 'PrestashopHasProductAttribute';
    protected $primaryKeys = ['id_product_attribute'];

}