<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoice
 * @package bamboo\app\domain\entities
 */
class CInvoiceDocument extends AEntity
{
    protected $entityTable = 'InvoiceDocument';
    protected $primaryKeys = ["id"];
}