<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CJob
 * @package bamboo\app\domain\entities
 */
class CJob extends AEntity
{
    protected $entityTable = 'Job';
    protected $primaryKeys = ['id'];
    protected $isCacheable= false;
}