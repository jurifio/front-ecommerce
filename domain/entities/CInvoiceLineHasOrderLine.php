<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoiceLineHasOrderLine
 * @package bamboo\domain\entities
 */
class CInvoiceLineHasOrderLine extends AEntity
{
	protected $entityTable = 'InvoiceLineHasOrderLine';
	protected $primaryKeys = ['invoiceLineId', 'invoiceLineInvoiceId', 'orderLineId', 'orderLineOrderId'];
}