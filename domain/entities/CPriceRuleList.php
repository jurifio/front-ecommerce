<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CPriceList
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 08/09/2021
 * @since 1.0
 */
class CPriceRuleList extends AEntity
{
    protected $entityTable = 'PriceRuleList';
	protected $primaryKeys = ['id','shopId'];
} 