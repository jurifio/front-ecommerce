<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class COrderLineStatus
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
 * @property COrderLineStatus $nextOrderLineStatus|null
 * @property COrderLineStatus $errOrderLineStatus|null
 */
class COrderLineStatus extends AEntity
{
    protected $entityTable = 'OrderLineStatus';
    protected $primaryKeys = array('id');
}