<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CSiteApi
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 27/07/2018
 * @since 1.0
 *
 * @property CShop $shop
 *
 */
class CSiteApi extends AEntity
{
    protected $entityTable = 'SiteApi';
    protected $primaryKeys = ['id'];

}