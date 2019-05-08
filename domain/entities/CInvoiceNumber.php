<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoiceNumber
 * @package bamboo\domain\entities
 */
class CInvoiceNumber extends AEntity
{
	protected $entityTable = 'InvoiceNumber';
	protected $primaryKeys = ['invoiceId'];
}