<?php


namespace bamboo\domain\repositories;

use bamboo\core\base\CObjectCollection;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooShipmentException;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\COrder;
use bamboo\domain\entities\COrderLine;
use bamboo\domain\entities\CShipment;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CAddressBookRepo
 * @package bamboo\domain\repositories
 */
class COrderLineStatisticsRepo extends ARepo
{
    /**
     * @param COrderLine $orderLine
     * @throws BambooShipmentException
     */
    public function calculateSupplierShippingTime(COrderLine $orderLine)
    {
        $olsR = \Monkey::app()->repoFactory->create('OrderLineStatistics');
        $ols = $olsR->findOneByStringId($orderLine->stringId());
        /** @var  COrderLine $orderLine */
        $shipmentOC = $orderLine->shipment;
        /** @var CObjectCollection $shipmentOC */
        $shipmentArr = $shipmentOC->findByKey('scope', CShipment::SCOPE_SUPPLIER_TO_US);
        $shipmentOC = new CObjectCollection();
        foreach($shipmentArr as $v) {
            $shipmentOC->add($v);
        }
        $canceledShipment = new CObjectCollection();
        $deliveredShipment = new CObjectCollection();
        foreach ($shipmentOC as $v) {
            if (1 == $v->shipmentFaultId) $canceledShipment->add($v);
        }
        foreach ($shipmentOC as $v) {
            if (null !== $v->deliveryDate) $deliveredShipment->add($v);
        }
            if (1 < count($shipmentOC) - $canceledShipment->count())
                throw new BambooShipmentException(
                    'Riga: ' . $orderLine->id . '-' . $orderLine->orderId
                    . '. Non posso procedere al calcolo se più di una spedizione (non cancellata) è associata a questa riga d\'ordine'
                );
            if (1 < $deliveredShipment->count())
                throw new BambooShipmentException(
                    'Riga: ' . $orderLine->id . '-' . $orderLine->orderId
                    . '. Più di una consegna risulta per questa riga d\'ordine.'
                );
        if (0 < count($shipmentOC) - $canceledShipment->count()) {
            $logR = \Monkey::app()->repoFactory->create('Log');
            $lSent = $logR->findOneBy(
                [
                    'stringId' => $orderLine->stringId(),
                    'actionName' => 'OrderStatusLog',
                    'entityName' => 'OrderLine',
                    'eventValue' => 'ORD_FRND_SENT'
                ]
            );
            if ($lSent) {
                $mailSentDate = new \DateTime($lSent->time);
                $mailSentDate = STimeToolbox::getNextWorkingTime($mailSentDate);

                $shipmentOC->reorderNumbersAndDates('predictedShipmentDate');
                $lastProcessedDate = clone $mailSentDate;

                $dayCount = 0;
                foreach ($shipmentOC as $v) {
                    $predictedShipment = new \DateTime($v->predictedShipmentDate);
                    //Se la data è arrivata
                    if ($lastProcessedDate == $mailSentDate) {
                        //@TODO l'ora da confrontare dovrebbe dipendere dal limite imposto dai singoli spedizionieri
                        if (
                            11 < (int)$mailSentDate->format('H')
                            && $predictedShipment->diff($mailSentDate)->d == 1
                        ) $dayCount--;
                    }
                    if (1 != $v->shipmentFaultId) {
                        $dayCount += $predictedShipment->diff($lastProcessedDate)->d;
                    }
                    $lastProcessedDate = clone $predictedShipment;
                }

                if (!$ols) $myOls = $olsR->getEmptyEntity();
                else $myOls = $ols;

                $myOls->friendTimeDays = $dayCount;

                if (!$ols) {
                    $myOls->orderLineId = $orderLine->id;
                    $myOls->orderId = $orderLine->orderId;
                    $myOls->insert();
                }
                else $myOls->update();
            }
        }
    }

    /**
     * @param COrderLine $orderLine
     * @throws BambooException
     */
    public function countShippingTime(COrderLine $orderLine){
        $shipment = $orderLine->shipment;
        if ($shipment) throw new BambooException('Non posso calcolare i tempi di spedizione se la riga d\'ordine ' .
            'non ha una spedizione associata');
        $shipmentDate = new \DateTime($orderLine->shipment->shipmentDate);
        $deliveryDate = new \DateTime($orderLine->shipment->deliveryDate);
        $diff = $deliveryDate->diff($shipmentDate);
        $this->writeStatistic($orderLine, ['CarrierFromFriend' => $diff->d]);
        return $diff->d;
    }

    /**
     * @param COrderLine $orderLine
     * @param array $values
     */
    public function writeStatistic(COrderLine $orderLine, array $values) {
        $olsR = \Monkey::app()->repoFactory->create('OrderLineStatistics');
        $ols = $olsR->findOneByStringId($orderLine->stringId());
        if (!$ols) {
            $myOls = $olsR->getEmptyEntity();
        } else {
            $myOls = $ols;
        }
        foreach($values as $v) {
            $myOls->{$k} = $v;
        }
        if (!$ols) {
            $myOls->orderLineId = $orderLine->id;
            $myOls->orderLineOrderId = $orderLine->orderId;
            $myOls->insert();
        } else {
            $myOls->update();
        }
        return $myOls;
    }
}