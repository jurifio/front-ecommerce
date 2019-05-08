<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CToken
 * @package bamboo\app\domain\entities
 */
class CToken extends AEntity
{
    protected $entityTable = 'Token';
    protected $primaryKeys = ['id','userId'];
}