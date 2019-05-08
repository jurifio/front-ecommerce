<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class COrder
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
 * @property CUser $user
 * @property CUserSession $userSession
 * @property CUserAddress $billingAddress
 * @property CUserAddress $shipmentAddress
 * @property CCoupon $coupon
 * @property CCart $cart
 * @property CObjectCollection $orderLine
 * @property COrderPaymentMethod $orderPaymentMethod
 * @property CObjectCollection $shipment
 */
class COrder extends AEntity
{
    protected $entityTable = 'Order';
    protected $primaryKeys = array('id');
	protected $isCacheable = false;

    public function getStatusLog() {
        return \Monkey::app()->repoFactory->create('Order')->getStatusHistory($this);
    }

    /**
     * @return array
     */
    public function getPublicOrderStatuses() {
        return [
            'Attesa di Pagamento',
            'Accettazione',
            'Lavorazione',
            'Controllo Qualità',
            'Spedizione',
            'Consegna'
        ];
    }

    /**
     * @param $key
     * @return bool
     */
    public function isStatusPassed($key) {
        switch ($key) {
            case 0:
                return $this->isCreated();
            case 1:
                return $this->isStatusPassed($key -1) && $this->isPaid();
            case 2:
                return $this->isStatusPassed($key -1) && $this->isWorked();
            case 3:
                return $this->isStatusPassed($key -1) && $this->isQualityChecked();
            case 4:
                return $this->isStatusPassed($key -1) && $this->isShipped();
            case 5:
                return $this->isStatusPassed($key -1) && $this->isDelivered();
        }
        return false;
    }

    /**
     * @param $key
     * @return bool
     */
    public function isStatusActual($key) {
        return $this->isStatusPassed($key) && !$this->isStatusPassed($key+1);
    }

    /**
     * @return string
     */
    public function getPublicOrderStatusKey()
    {
        foreach (array_reverse($this->getPublicOrderStatuses(),true) as $key=>$val){
            if($this->isStatusPassed($key)) return $key;
        }
        return 0;
    }

    /**
     * @return mixed|string
     */
    public function getPublicOrderStatus()
    {
        if($this->isCanceled()) return "Cancellato";
        return $this->getPublicOrderStatuses()[$this->getPublicOrderStatusKey()];
    }

    /**
     * @return bool
     */
    public function isCanceled() {
        return $this->orderStatus->code == 'ORD_CANCEL' || $this->orderStatus->code == 'ORD_FR_CANCEL';
    }

    /**
     * @return bool
     */
    public function isCreated() {
        return true;
    }

    /**
     * @return bool
     */
    public function isPaid() {
        return ($this->paidAmount == $this->netTotal || $this->orderPaymentMethod->name == 'contrassegno');
    }

    /**
     * @return bool
     */
    protected function isWorked() {
        foreach ($this->orderLine as $orderLine) {
            /** @var COrderLine $orderLine */
            if($orderLine->orderLineStatus->phase >= 5) return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    protected function isQualityChecked() {
        foreach ($this->orderLine as $orderLine) {
            /** @var COrderLine $orderLine */
            if($orderLine->orderLineStatus->phase < 8 && $orderLine->orderLineStatus->code != 'ORD_FRND_OK') return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    protected function isShipped() {
        return $this->getLastShipmentToClient() != null;
    }

    /**
     * @return bool
     */
    protected function isDelivered() {
        $shipment = $this->getLastShipmentToClient();
        return $shipment && $shipment->deliveryDate && !empty($shipment->deliveryDate);
    }

    /**
     * @param $status
     * @param null $note
     */
    public function updateStatus($status, $note = null) {
        \Monkey::app()->repoFactory->create('Order')->updateStatus($this,$status,$note);
    }

    /**
     * @return bool
     */
    public function getOrderPaymentUrl() {
        return \Monkey::app()->orderManager->getPaymentGateway($this)->getUrl($this);
    }

    /**
     * @return CShipment|null
     */
    public function getLastShipmentToClient() {
        $last = null;
        foreach ($this->shipment as $shipment) {
            /** @var CShipment $shipment */
            if($shipment->scope == $shipment::SCOPE_US_TO_USER && ($last === null || $shipment->creationDate > $last->creationDate)) $last = $shipment;
        }
        return $last;
    }
}