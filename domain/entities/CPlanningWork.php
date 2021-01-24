<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPlanningWork
 * @package bamboo\app\domain\entities
 */
class CPlanningWork extends AEntity
{
    protected $entityTable = 'PlanningWork';
    protected $primaryKeys = ['id'];
}