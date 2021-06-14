<?php


namespace bamboo\domain\repositories;

use bamboo\business\carrier\ACarrierHandler;
use bamboo\business\carrier\IImplementedPickUpHandler;
use bamboo\core\base\CObjectCollection;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooShipmentException;
use bamboo\domain\entities\CCarrier;
use bamboo\domain\entities\COrder;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\COrderLine;
use bamboo\domain\entities\CShipment;
use bamboo\domain\entities\CShipmentFault;
use bamboo\domain\entities\CUserAddress;
use bamboo\utils\time\SDateToolbox;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CAddressBookRepo
 * @package bamboo\domain\repositories
 */
class CShipmentRepo extends ARepo
{
    /**
     * Crea una nuova spedizione di un ordine per un cliente
     * @param $carrierId
     * @param $trackingNumber
     * @param $time
     * @param COrder $order
     * @return CShipment
     * @throws BambooException
     */
    public function newOrderShipmentToClient($carrierId, $trackingNumber, $time, COrder $order)
    {
        /** @var CShipment $shipment */
        $shipment = \Monkey::app()->repoFactory->create('Shipment')->getEmptyEntity();
        $shipment->carrierId = $carrierId;
        $shipment->scope = $shipment::SCOPE_US_TO_USER;
        $shipment->predictedShipmentDate = $time;
        $shipment->predictedDeliveryDate = SDateToolbox::GetNextWorkingDay(\DateTime::createFromFormat(DATE_MYSQL_FORMAT, $time));
        $shipment->declaredValue = $order->grossTotal;

        /** @var CAddressBookRepo $addressBookRepo */
        $addressBookRepo = \Monkey::app()->repoFactory->create('AddressBook');

        $shipment->fromAddressBookId = $addressBookRepo->getMainHubAddressBook()->id;

        $addressBookTo = $addressBookRepo->findOrInsertUserAddress(CUserAddress::defrost($order->frozenShippingAddress));
        $shipment->toAddressBookId = $addressBookTo->id;
        $shipment->note = $order->shipmentNote;
        $shipment->smartInsert();

        foreach ($order->orderLine as $orderLine) {
            $olhs = \Monkey::app()->repoFactory->create('OrderLineHasShipment')->getEmptyEntity();
            $olhs->orderId = $orderLine->orderId;
            $olhs->orderLineId = $orderLine->id;
            $olhs->shipmentId = $shipment->id;
            $olhs->insert();
        }

        if ($shipment->carrier->implementation != null) {
            $shipment->sendToCarrier();
        } else {
            if (!$trackingNumber) throw new BambooException('Per una spedizione Manuale è necessario fornire il Tracking Number');
            $shipment->trackingNumber = trim($trackingNumber);
        }


        return $shipment;
    }
    /**
     * Crea una nuova spedizione di un ordine per un cliente
     * @param $carrierId
     * @param $trackingNumber
     * @param $time
     * @param $orderLineId
     * @param COrder $order
     * @return CShipment
     * @throws BambooException
     */
    public function newOrderShipmentToClientSingleLine($carrierId, $trackingNumber, $time, COrder $order, $orderLineId )
    {
        /** @var CShipment $shipment */
        $shipment = \Monkey::app()->repoFactory->create('Shipment')->getEmptyEntity();
        $shipment->carrierId = $carrierId;
        $shipment->scope = $shipment::SCOPE_US_TO_USER;
        $shipment->predictedShipmentDate = $time;
        $shipment->predictedDeliveryDate = SDateToolbox::GetNextWorkingDay(\DateTime::createFromFormat(DATE_MYSQL_FORMAT, $time));
        $shipment->declaredValue = $order->grossTotal;

        /** @var CAddressBookRepo $addressBookRepo */
        $addressBookRepo = \Monkey::app()->repoFactory->create('AddressBook');

        $shipment->fromAddressBookId = $addressBookRepo->getMainHubAddressBook()->id;

        $addressBookTo = $addressBookRepo->findOrInsertUserAddress(CUserAddress::defrost($order->frozenShippingAddress));
        $shipment->toAddressBookId = $addressBookTo->id;
        $shipment->note = $order->shipmentNote;
        $shipment->smartInsert();

        foreach ($order->orderLine as $orderLine) {
            if($orderLine->id==$orderlineId) {
                $olhs = \Monkey::app()->repoFactory->create('OrderLineHasShipment')->getEmptyEntity();
                $olhs->orderId = $orderLine->orderId;
                $olhs->orderLineId = $orderLine->id;
                $olhs->shipmentId = $shipment->id;
                $olhs->insert();
            }else{
                continue;
            }
        }

        if ($shipment->carrier->implementation != null) {
            $shipment->sendToCarrier();
        } else {
            if (!$trackingNumber) throw new BambooException('Per una spedizione Manuale è necessario fornire il Tracking Number');
            $shipment->trackingNumber = trim($trackingNumber);
        }


        return $shipment;
    }
    /**
     * Crea una nuova spedizione di un ordine per un cliente
     * @param $carrierId
     * @param $bookingNumber
     * @param $time
     * @param COrder $order
     * @param $orderLines
     * @throws BambooException

     */
    public function newOrderShipmentFromSupplierToClient($carrierId, $fromId, $bookingNumber, $time, $orderLines)
    {
        foreach ($orderLines as $ol){
            $orderId=$ol->orderId;
        }
        $orderRepo=\Monkey::app()->repoFactory->create('Order')->findOneBy(['id'=>$orderId]);
        if($orderRepo!=null){
            $shipmentAddress=$orderRepo->frozenShippingAddress;
        }
        /** @var CShipment $shipment */
        /** @var CAddressBookRepo $addressBookRepo */
        $addressBookRepo = \Monkey::app()->repoFactory->create('AddressBook');
        $toAddressBook = $addressBookRepo->findOrInsertUserAddress(CUserAddress::defrost($shipmentAddress));

        $shipment = \Monkey::app()->repoFactory->create('Shipment')->findBySql("SELECT id,date(predictedShipmentDate)
                                                                            FROM Shipment
                                                                            WHERE date(predictedShipmentDate) = DATE(?) AND
                                                                                  shipmentDate is null AND 
                                                                                  cancellationDate is null AND
                                                                                  carrierId = ? AND
                                                                                  fromAddressBookId = ? AND
                                                                                  toAddressBookId = ?", [$time,
            $carrierId,
            $fromId,
            $toAddressBook->id]);

        if ($shipment->isEmpty()) {
            $shipment = \Monkey::app()->repoFactory->create('Shipment')->getEmptyEntity();
            $shipment->carrierId = $carrierId;
            $shipment->scope = $shipment::SCOPE_SUPPLIER_TO_USER;
            $shipment->bookingNumber = trim($bookingNumber);
            $shipment->predictedShipmentDate = $time;
            $shipment->predictedDeliveryDate = STimeToolbox::DbFormattedDate(SDateToolbox::GetNextWorkingDay(STimeToolbox::GetDateTime($time)));
            $shipment->declaredValue = 0;
            $shipment->fromAddressBookId = $fromId;
            $shipment->toAddressBookId = $toAddressBook->id;
            $shipment->id = $shipment->insert();
            sleep(2);
            $shipment = $this->findOne(['id' => $shipment->id]);
            sleep(2);
                $this->addPickUp($shipment,$orderId);

        } else {
            $shipment = $shipment->getFirst();
        }

        foreach ($orderLines as $orderLine) {
            $olhs = \Monkey::app()->repoFactory->create('OrderLineHasShipment')->getEmptyEntity();
            $olhs->orderId = $orderLine->orderId;
            $olhs->orderLineId = $orderLine->id;
            $olhs->shipmentId = $shipment->id;
            if (\Monkey::app()->repoFactory->create('OrderLineHasShipment')->findOneBy($olhs->getIds())) continue;
            $olhs->insert();
            $shipment->declaredValue += $orderLine->friendRevenue;
        }
        $shipment->update();
        if ($shipment->carrier->implementation != null) {
            $shipment->sendToCarrier($orderLine->orderId);
        }
        return $shipment;
    }
    /**
     * Crea una nuova spedizione di un ordine per un cliente
     * @param $carrierId
     * @param $bookingNumber
     * @param $time
     * @param COrder $order
     * @param $orderLineId
     * @param $orderId
     * @throws BambooException

     */
    public function newOrderShipmentFromSupplierToClientSingleLine($carrierId, $fromId, $bookingNumber, $time, $orderLineId, $orderId)
    {

        $orderRepo=\Monkey::app()->repoFactory->create('Order')->findOneBy(['id'=>$orderId]);
        if($orderRepo!=null){
            $shipmentAddress=$orderRepo->frozenShippingAddress;
        }
        /** @var CShipment $shipment */
        /** @var CAddressBookRepo $addressBookRepo */
        $addressBookRepo = \Monkey::app()->repoFactory->create('AddressBook');
        $toAddressBook = $addressBookRepo->findOrInsertUserAddress(CUserAddress::defrost($shipmentAddress));

        $shipment = \Monkey::app()->repoFactory->create('Shipment')->findBySql("SELECT id,date(predictedShipmentDate)
                                                                            FROM Shipment
                                                                            WHERE date(predictedShipmentDate) = DATE(?) AND
                                                                                  shipmentDate is null AND 
                                                                                  cancellationDate is null AND
                                                                                  carrierId = ? AND
                                                                                  fromAddressBookId = ? AND
                                                                                  toAddressBookId = ?", [$time,
            $carrierId,
            $fromId,
            $toAddressBook->id]);

        if ($shipment->isEmpty()) {
            $shipment = \Monkey::app()->repoFactory->create('Shipment')->getEmptyEntity();
            $shipment->carrierId = $carrierId;
            $shipment->scope = $shipment::SCOPE_SUPPLIER_TO_USER;
            if($bookingNumber!='') {
                $shipment->bookingNumber = trim($bookingNumber);
            }
            $shipment->predictedShipmentDate = $time;
            $shipment->predictedDeliveryDate = STimeToolbox::DbFormattedDate(SDateToolbox::GetNextWorkingDay(STimeToolbox::GetDateTime($time)));
            $shipment->declaredValue = 0;
            $shipment->fromAddressBookId = $fromId;
            $shipment->toAddressBookId = $toAddressBook->id;
            $shipment->id = $shipment->insert();
            sleep(2);
            $shipment = $this->findOne(['id' => $shipment->id]);
            sleep(2);
            $this->addPickUp($shipment,$orderId);

        } else {
            $shipment = $shipment->getFirst();
        }

        $orderLine=\Monkey::app()->repoFactory->create('OrderLine')->findOneBy(['id'=>$orderLineId,'orderId'=>$orderId]);
            $olhs = \Monkey::app()->repoFactory->create('OrderLineHasShipment')->getEmptyEntity();
            $olhs->orderId = $orderId;
            $olhs->orderLineId = $orderLineId;
            $olhs->shipmentId = $shipment->id;
            $olhs->insert();
            $shipment->declaredValue += $orderLine->friendRevenue;

        $shipment->update();
        if ($shipment->carrier->implementation != null) {
            $shipment->sendToCarrier($orderLine->orderId);
        }
        return $shipment;
    }

    /**
     * returns an array of dates (Y-m-d) to do the shipping
     * @param $carrierId
     * @param $fromAddressBookId
     * @return array
     */
    public function getAvailableDatesForShipmentToUs($carrierId, $fromAddressBookId)
    {
        /** @var CAddressBookRepo $addressBookRepo */
        $addressBookRepo = \Monkey::app()->repoFactory->create('AddressBook');
        $toAddressBook = $addressBookRepo->getMainHubAddressBook();

        /** @var CCarrier $carrier */
        $carrier = \Monkey::app()->repoFactory->create('Carrier')->findOneByStringId($carrierId);

        $isTodayExisting = $this->app->dbAdapter->query(
            "SELECT TRUE
                    FROM Shipment
                    WHERE date(predictedShipmentDate) = DATE(CURRENT_TIMESTAMP) AND
                          shipmentDate is null AND
                          carrierId = ? AND
                          fromAddressBookId = ? AND
                          toAddressBookId = ?",
            [$carrierId, $fromAddressBookId, $toAddressBook->id])->fetchAll();

        if (count($isTodayExisting) > 0) {
            $firstPossibleDate = new \DateTime();
        } elseif ($carrier->getHandler() && $carrier->getHandler() instanceof IImplementedPickUpHandler) {
            $fromAddressBook = $addressBookRepo->findOneByStringId($fromAddressBookId);
            $firstPossibleDate = $carrier->getHandler()->getFirstPickUpDate($fromAddressBook,$toAddressBook);
        } elseif ($carrier->prenotationTimeLimit > (new \DateTime())->format('H:i:s'))  {
            $firstPossibleDate = new \DateTime();
        } else {
            $firstPossibleDate = SDateToolbox::GetNextWorkingDay(new \DateTime());
        }

        $possibleDates = [];
        $possibleDates[] = $firstPossibleDate->format('Y-m-d');
        $possibleDates[] = SDateToolbox::GetNextWorkingDay($firstPossibleDate)->format('Y-m-d');
        return $possibleDates;
    }

    /**
     * Crea una nuova spedizione dal friend a noi,
     * se è già presente una spedizione aggiunge le righe d'ordine
     * @param $carrierId
     * @param $fromId
     * @param $bookingNumber
     * @param $time
     * @param $orderLines
     * @return CShipment
     */
    public function newFriendShipmentToUs($carrierId, $fromId, $bookingNumber, $time, $orderLines)
    {
        /** @var CShipment $shipment */
        /** @var CAddressBookRepo $addressBookRepo */
        $addressBookRepo = \Monkey::app()->repoFactory->create('AddressBook');
        $toAddressBook = $addressBookRepo->getMainHubAddressBook();

        $shipment = \Monkey::app()->repoFactory->create('Shipment')->findBySql("SELECT id,date(predictedShipmentDate)
                                                                            FROM Shipment
                                                                            WHERE date(predictedShipmentDate) = DATE(?) AND
                                                                                  shipmentDate is null AND 
                                                                                  cancellationDate is null AND
                                                                                  carrierId = ? AND
                                                                                  fromAddressBookId = ? AND
                                                                                  toAddressBookId = ?", [$time,
            $carrierId,
            $fromId,
            $toAddressBook->id]);
        $orderId=$orderLines->orderId;

        if ($shipment->isEmpty()) {
            $shipment = \Monkey::app()->repoFactory->create('Shipment')->getEmptyEntity();
            $shipment->carrierId = $carrierId;
            $shipment->scope = $shipment::SCOPE_SUPPLIER_TO_US;
            $shipment->bookingNumber = trim($bookingNumber);
            $shipment->predictedShipmentDate = $time;
            $shipment->predictedDeliveryDate = STimeToolbox::DbFormattedDate(SDateToolbox::GetNextWorkingDay(STimeToolbox::GetDateTime($time)));
            $shipment->declaredValue = 0;
            $shipment->fromAddressBookId = $fromId;
            $shipment->toAddressBookId = $toAddressBook->id;
            $shipment->id = $shipment->insert();
            $shipment = $this->findOne(['id' => $shipment->id]);

            $this->addPickUp($shipment,$orderId);

        } else {
            $shipment = $shipment->getFirst();
        }

        foreach ($orderLines as $orderLine) {
            $olhs = \Monkey::app()->repoFactory->create('OrderLineHasShipment')->getEmptyEntity();
            $olhs->orderId = $orderLine->orderId;
            $olhs->orderLineId = $orderLine->id;
            $olhs->shipmentId = $shipment->id;
            if (\Monkey::app()->repoFactory->create('OrderLineHasShipment')->findOneBy($olhs->getIds())) continue;
            $olhs->insert();
            $shipment->declaredValue += $orderLine->friendRevenue;
        }
        $shipment->update();
        return $shipment;
    }

    /**
     * @param CShipment $shipment
     * $orderId int
     * @param $orderId
     * @return bool
     * @throws BambooException
     */
    public function addPickUp(CShipment $shipment, $orderId )
    {
        if ($shipment->carrier->getHandler() && $shipment->carrier->getHandler() instanceof IImplementedPickUpHandler) {
            return $shipment->carrier->getHandler()->addPickUp($shipment,$orderId);
        }
        return true;
    }

    /**
     * @param CShipment $shipment
     * @param CShipmentFault $fault
     * @return CShipment
     * @throws BambooException
     * @throws BambooShipmentException
     */
    public function cancel(CShipment $shipment, CShipmentFault $fault)
    {
        if ($shipment->shipmentDate) throw new BambooShipmentException('Non si può annullare un ordine spedito');
        $shipment->shipmentFaultId = $fault->id;
        $shipment->cancellationDate = STimeToolbox::DbFormattedDate();
        $shipment->update();
        if ($shipment->carrier->getHandler()) {
            $shipment->carrier->getHandler()->cancelDelivery($shipment);
            if ($shipment->carrier->getHandler() instanceof IImplementedPickUpHandler && !empty($shipment->bookingNumber)) {
                $shipment->carrier->getHandler()->cancelPickUp($shipment);
            }
        }

        return $shipment;
    }

    /**
     * @param CShipment $shipment
     * @return bool|null
     */
    public function checkIn(CShipment $shipment)
    {
        switch ($shipment->scope) {
            case CShipment::SCOPE_SUPPLIER_TO_US: {
                $ok = $this->checkInFriendShipment($shipment);
                break;
            }
            default:
                $ok = null;
                break;
        }
        return $ok;
    }

    /**
     * @param CShipment $shipment
     * @return bool
     */
    protected function checkInFriendShipment(CShipment $shipment)
    {
        /** @var COrderLineRepo $orderLineRepo */
        $orderLineRepo = \Monkey::app()->repoFactory->create('OrderLine');
        /** @var CWarehouseShelfPositionRepo $warehouseShelfPositionRepo */
        $warehouseShelfPositionRepo = \Monkey::app()->repoFactory->create('WarehouseShelfPosition');
        foreach ($shipment->orderLine as $orderLine) {
            /** @var COrderLine $orderLine */
            $orderLine->orderLineStatus->nextOrderLineStatus;
            $orderLineRepo->updateStatus($orderLine, 'ORD_CHK_IN');
            $all = true;
            foreach ($orderLine->order->orderLine as $orderLine2) {
                if ($orderLine2->orderLineStatus->phase < 10) {
                    $all = false;
                    break;
                };
            }

            if ($all) {
                //$orderLine->order->updateStatus('ORD_PACK');
            }
        }
        $warehouseShelfPositionRepo->placeShipmentInPositions($shipment);
        return true;
    }

    /**
     * @param CShipment $shipment
     * $orderId int
     * @param $orderId
     * @return CShipment
     * @throws BambooException
     */
    public function sendShipmentToCarrier(CShipment $shipment,$orderId)
    {
        $class = $shipment->carrier->implementation;
        if (!class_exists($class)) throw new BambooException("Could not send handle $shipment->carrier->name shipment");

        /** @var ACarrierHandler $handler */
        $handler = new $class([]);
        return $handler->addDelivery($shipment,$orderId);
    }

    /**
     * @param CShipment $shipment
     * @return string
     * @throws BambooException
     */
    public function printShipmentLabel(CShipment $shipment)
    {
        $class = $shipment->carrier->implementation;
        if (!class_exists($class)) throw new BambooException("Could not send handle $shipment->carrier->name shipment");

        /** @var ACarrierHandler $handler */
        $handler = new $class([]);
        return $handler->printParcelLabel($shipment);
    }

    /**
     * @param CObjectCollection $shipments
     * @param CCarrier $carrier
     * @return CObjectCollection
     * @throws BambooException
     */
    public function closeShipmentsForCarrier(CObjectCollection $shipments, CCarrier $carrier)
    {
        $class = $carrier->implementation;
        if (is_null($class)) {
            foreach ($shipments as $shipment) {
                $shipment->shipmentDate = STimeToolbox::DbFormattedDate();
                $shipment->update();
            }
            return $shipments;
        }
        if (!class_exists($class)) throw new BambooException("Could not send handle $carrier->name shipment");

        /** @var ACarrierHandler $handler */
        $handler = new $class([]);
        if ($handler->closePendentShipping($shipments)) {
            foreach ($shipments as $shipment) {
                $shipment->shipmentDate = STimeToolbox::DbFormattedDate();
                $shipment->update();
            }
            return $shipments;
        }
        return new CObjectCollection();
    }
}