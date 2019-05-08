<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class COrderPaymentMethod
 * @package bamboo\app\domain\entities
 */
class COrderPaymentMethod extends AEntity
{
    protected $entityTable = 'OrderPaymentMethod';
    protected $primaryKeys = array('id');
	protected $isCacheable = false;
}