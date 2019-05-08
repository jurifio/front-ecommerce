<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CBlog
 * @package bamboo\app\domain\entities
 */
class CBlog extends AEntity
{
    protected $entityTable = 'Blog';
    protected $primaryKeys = ['id'];
}