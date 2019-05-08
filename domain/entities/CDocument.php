<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\utils\price\SPriceToolbox;

/**
 * Class CDocument
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 * @property CPaymentBill $paymentBill
 * @property CInvoiceType $invoiceType
 * @property CUser $user
 * @property CInvoiceBin $invoiceBin
 *
 */
class CDocument extends AEntity
{
	protected $entityTable = 'Document';
	protected $primaryKeys = ['id'];


    /**
     * @param bool $round
     * @return float
     */
    public function getSignedValueWithVat($round = true) {
        if($this->invoiceType->isActive) $tot = -1* $this->totalWithVat;
        else $tot = $this->totalWithVat;
        if($round) return SPriceToolbox::roundVat($tot);
        else return $tot;
    }

    /**
     * @return null
     */
    public function getShop() {
        if (null == $this->shopRecipientId) return null;
        return $this->addressBook->shop;
    }

    /**
     * @return mixed
     */
    public function calculateOurTotal($round = true) {
        return \Monkey::app()->repoFactory->create('Document')->sumFriendRevenueFromOrders($this->orderLine,$this->getVatPercent(),$round);
    }

    /**
     * @return mixed
     */
    public function getVatPercent() {
        return \Monkey::app()->repoFactory->create('Document')->
            getInvoiceVat($this->invoiceType,
                            $this->shopAddressBook ?? $this->userAddressBook);
    }
}