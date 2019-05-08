<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CDates
 * @package bamboo\domain\entities
 */
class CDates extends AEntity
{
    protected $entityTable = '_Dates';
    protected $primaryKeys = ['id'];
}