<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoiceSectional
 * @package bamboo\domain\entities
 */
class CInvoiceBin extends AEntity
{
	protected $entityTable = 'InvoiceBin';
	protected $primaryKeys = ['invoiceId'];
}