<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class COrderStatusTranslation
 * @package bamboo\app\domain\entities
 */
class COrderStatusTranslation extends AEntity
{
    protected $entityTable = 'OrderStatusTranslation';
	protected $primaryKeys = ['orderStatusId'];
}