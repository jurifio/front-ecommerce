<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPlanningWork
 * @package bamboo\app\domain\entities
 */
class CPlanningWorkEvent extends AEntity
{
    protected $entityTable = 'PlanningWorkEvent';
    protected $primaryKeys = ['id','planningWorkId'];
}