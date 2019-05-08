<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCity
 * @package bamboo\app\domain\entities
 */
class CCity extends AEntity
{
    protected $entityTable = 'City';
    protected $primaryKeys = array('id');
}