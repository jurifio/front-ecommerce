<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CBanner
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
 * @property CCampaign $campaign;
 * @property CShop $shop;
 */
class CBanner extends AEntity
{
    protected $entityTable = 'Banner';
    protected $primaryKeys = ['id'];
}