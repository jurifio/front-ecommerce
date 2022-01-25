<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CShopConfigProd
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 20/01/2022
 * @since 1.0
 */
class CShopConfigProd extends AEntity
{
    protected $entityTable = 'ShopConfigProd';
    protected $primaryKeys = ['id'];
}