<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

class CPaymentBillHasInvoiceNew extends AEntity
{
    protected $entityTable = 'PaymentBillHasInvoiceNew';
    protected $primaryKeys = ['paymentBillId', 'invoiceNewId'];
}