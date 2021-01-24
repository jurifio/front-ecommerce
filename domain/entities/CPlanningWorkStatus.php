<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CTicketStatus
 * @package bamboo\app\domain\entities
 */
class CPlanningWorkStatus extends AEntity
{
    protected $entityTable = 'PlanningWorkStatus';
    protected $primaryKeys = ['id'];
}