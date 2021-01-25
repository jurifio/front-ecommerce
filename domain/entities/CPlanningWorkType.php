<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CTicketStatus
 * @package bamboo\app\domain\entities
 */
class CPlanningWorkType extends AEntity
{
    protected $entityTable = 'PlanningWorkType';
    protected $primaryKeys = ['id'];
}