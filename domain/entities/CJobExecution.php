<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CJobExecution
 * @package bamboo\app\domain\entities
 */
class CJobExecution extends AEntity
{
    protected $entityTable = 'JobExecution';
    protected $primaryKeys = ['id','jobId'];
	protected $isCacheable = false;
}