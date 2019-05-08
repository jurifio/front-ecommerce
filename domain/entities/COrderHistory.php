<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class COrderHistory
 * @package bamboo\app\domain\entities
 */
class COrderHistory extends AEntity
{
    protected $entityTable = 'OrderHistory';
    protected $primaryKeys = array('id');
	protected $isCacheable = false;
}