<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoiceType
 * @package bamboo\domain\entities
 */
class CInvoiceType extends AEntity
{
    const DDT_SHOOTING = 11;
    const DDT_RETURN_SHOOTING = 12;
    const CREDIT_REQUEST = 19;

	protected $entityTable = 'InvoiceType';
	protected $primaryKeys = ['id'];
}