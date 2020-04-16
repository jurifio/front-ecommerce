<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCrmInterest
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 15/04/2020
 * @since 1.0
 */
class CCrmInterest extends AEntity
{

    protected $entityTable = 'CrmInterest';
    protected $primaryKeys = ['id'];
}