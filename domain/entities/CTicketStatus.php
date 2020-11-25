<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CTicketStatus
 * @package bamboo\app\domain\entities
 */
class CTicketStatus extends AEntity
{
    protected $entityTable = 'TicketStatus';
    protected $primaryKeys = ['id'];
}