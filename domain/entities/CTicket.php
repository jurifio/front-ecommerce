<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CTicket
 * @package bamboo\app\domain\entities
 */
class CTicket extends AEntity
{
    protected $entityTable = 'Ticket';
    protected $primaryKeys = ['id'];
}