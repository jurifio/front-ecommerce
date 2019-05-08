<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

class CProductStatistics extends AEntity
{
	protected $entityTable = 'ProductStatistics';
	protected $primaryKeys = ['id', 'productVariantId', 'shopId'];

}