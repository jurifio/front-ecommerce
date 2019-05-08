<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CJobLog
 * @package redpanda\app\domain\entities
 */
class CJobLog extends AEntity
{
    protected $entityTable = 'JobLog';
    protected $primaryKeys = ['id','jobId','jobExecutionId'];
    protected $isCacheable = false;
}