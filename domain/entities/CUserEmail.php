<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CUserEmail
 * @package bamboo\app\domain\entities
 */
class CUserEmail extends AEntity
{
    protected $entityTable = 'UserEmail';
    protected $primaryKeys = ['id','userId'];
}