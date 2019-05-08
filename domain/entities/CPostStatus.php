<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPostStatus
 * @package bamboo\app\domain\entities
 */
class CPostStatus extends AEntity
{
    protected $entityTable = 'PostStatus';
    protected $primaryKeys = array('id');
}