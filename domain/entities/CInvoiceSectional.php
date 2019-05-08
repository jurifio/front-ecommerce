<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoiceSectional
 * @package bamboo\domain\entities
 */
class CInvoiceSectional extends AEntity
{
	protected $entityTable = 'InvoiceSectional';
	protected $primaryKeys = ['id'];
}