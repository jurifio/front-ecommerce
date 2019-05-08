<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCountry
 * @package bamboo\app\domain\entities
 */
class CCountry extends AEntity
{
    protected $entityTable = 'Country';
    protected $primaryKeys = ['id'];
}