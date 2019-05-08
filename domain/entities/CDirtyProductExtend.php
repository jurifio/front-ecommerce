<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoice
 * @package bamboo\app\domain\entities
 */
class CDirtyProductExtend extends AEntity
{
    protected $entityTable = 'DirtyProductExtend';
    protected $primaryKeys = ['dirtyProductId'];
	protected $isCacheable = false;
}