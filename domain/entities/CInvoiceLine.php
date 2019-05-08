<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoiceLine
 * @package bamboo\domain\entities
 */
class CInvoiceLine extends AEntity
{
	protected $entityTable = 'InvoiceLine';
	protected $primaryKeys = ['id', 'invoiceId'];
}