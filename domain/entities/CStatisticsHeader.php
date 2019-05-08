<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
/**
 * Class CStatisticsHeaders
 * @package bamboo\domain\entities
 */
class CStatisticsHeader extends AEntity
{
	protected $entityTable = 'StatisticsHeader';
	protected $primaryKeys = ['id'];
}