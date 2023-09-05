<?php

namespace bamboo\ecommerce\business;

use bamboo\core\application\AApplication;
use bamboo\core\ecommerce\APaymentGateway;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\domain\entities\COrder;
use bamboo\domain\entities\COrderStatus;
use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\db\pandaorm\entities\CEntityManager;
use bamboo\core\ecommerce\AOrderManager;
use bamboo\domain\repositories\COrderRepo;
use bamboo\core\exceptions\RedPandaException;

/**
 * Class COrderManager
 * @package bamboo\app
 */
class COrderManager extends AOrderManager
{
    /**
     * @var CEntityManager $orderEm
     */
    protected $orderEm;

    /**
     * @var CEntityManager $orderLineEm
     */
    protected $orderLineEm;

    /**
     * Manage the Order, used to modify  and manage orders
     *
     * @param AApplication $app
     * @throws \bamboo\core\exceptions\RedPandaConfigException|\Throwable
     */
    public function __construct($app)
    {
        parent::__construct($app);

        $this->orderEm = $this->app->entityManagerFactory->create('Order');
        $this->orderLineEm = $this->app->entityManagerFactory->create('OrderLine');
    }

    /**
     * @param COrder $order
     * @param $status
     * @return $order
     */
    public function changeStatus(COrder $order, $status) {
        $status = $this->findStatus($status);
        if ("ORD_CANCEL" === $status->code) {
            $iR = \Monkey::app()->repoFactory->create('Invoice');
            if ($iR->findBy(['orderId' => $order->id])->count())
                throw new BambooException('Non può essere cancellato un ordine che ha fatture');
        }

        if ($order->status !== $status->code) {
            $order->status = $status->code;
            $order->update();
            $this->registerEvent($order->id, 'Change Status', 'Changed to status ' . $status->code, $order->status);

            if ($status->code == "ORD_CANCEL") {
                $this->abortOrder($order, $status->code);
            }

            \Monkey::app()->eventManager->triggerEvent('changeOrderStatus',
                [
                    'order' => $order,
                    'status' => $order->status
                ]);
        }

        return $order;
    }

    public function findStatus($status) {
        $osR = \Monkey::app()->repoFactory->create('OrderStatus');
        if (is_numeric($status)) {
            $status = $osR->findOneBy(['id' => $status]);
        } elseif (is_string($status)) {
            $status = $osR->findOneBy(['code' => $status]);
        } elseif (!$status instanceof COrderStatus) {
            throw new BambooException('Lo stato che si sta cercando di impostare non esiste');
        }
        if (!$status) throw new BambooException('Lo stato fornito non esiste');
        return $status;
    }

    /**
     * Register the event to OrderHistory
     *
     * @param int $orderId
     * @param string $event
     * @param string $description
     * @param string $status
     * @return bool
     */
    public function registerEvent($orderId, $event, $description, $status)
    {
        try {
            $i = $this->app->dbAdapter->insert('OrderHistory', ['orderId' => $orderId, 'event' => $event, 'description' => $description, 'status' => $status]);
        } catch (\Throwable $e) {
            return false;
        }

        return (bool)$i > 0;
    }

    /**
     * @param $order
     * @param $newStatus
     */
    protected function abortOrder($order, $newStatus)
    {
        foreach ($order->orderLine as $orderLine) {
            $sku = $orderLine->productSku;
            if ($sku->padding < 0) {
                $sku->padding++;
                $sku->stockQty++;
                $sku->update();
            }
        }
    }

    /**
     *
     */
    public function activeOrdersId()
    {
        //TODO find all active orders
    }

    /**
     *
     */
    public function activeOrders()
    {
        //TODO find all active orders
    }

    /**
     * Returns the last order Entity
     * @return bool|\bamboo\core\db\pandaorm\entities\IEntity
     */
    public function lastOrder()
    {
        try {
            if ($id = $this->lastOrderId()) {
                return $this->orderEm->findOne([$id]);
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Returns the last order or false if fails
     * @return bool
     */
    public function lastOrderId()
    {
        try {
            $userId = $this->app->getUser()->getId();
            $id = $this->app->dbAdapter->query("SELECT id FROM `Order` WHERE `status` LIKE 'ORD%' AND userId = ? ORDER BY orderDate DESC LIMIT 0,1", [$userId])->fetch()['id'];
            if ((bool)$id) return $id;

        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * @param $order
     * @param $payment
     * @return bool
     */
    public function pay($order, $payment)
    {
        /** @var COrderRepo $oR */
        $oR = \Monkey::app()->repoFactory->create('Order');
        try {
            if ($order instanceof COrder) {

            } else if (is_numeric($order)) {
                $order = $oR->findOne(['id' => $order]);
            } else {
                throw new RedPandaException('Method Pay require id or COrder');
            }

            if ($payment === true) {
                $payment = $order->netTotal;
            }
            if ($order->status == 'ORD_PENDING' && $payment == $order->netTotal) {
                $oR->updateStatus($order, 'ORD_WAIT');
            }
            $order->paidAmount = $order->netTotal;
            $order->update();

            \Monkey::app()->eventManager->triggerEvent('payOrder',
                [
                    'order' => $order,
                    'status' => $order->status,
                    'userId' => $order->userId
                ]);

            $this->registerEvent($order->id, 'Pagamento - Pagato', "è arrivato un pagamento di " . $payment . " EUR a fronte di " . $order->netTotal . " da pagare - PAGATO", $order->status);

        } catch (\Throwable $e) {
            \Monkey::dump($e);

            return false;
        }

        return true;
    }

    /**
     * @param $order
     * @return APaymentGateway
     * @throws BambooOutOfBoundException
     * @throws RedPandaException
     */
	public function getPaymentGateway($order)
	{
		if ($order instanceof COrder) {

        } else if (is_numeric($order)) {
            $order = \Monkey::app()->repoFactory->create('Order')->findOne(['id' => $order]);
        } else {
            throw new RedPandaException('Required id or COrder');
        }
        switch ($order->orderPaymentMethod->name) {
            case 'paypal':
                $className = 'bamboo\\business\\payment\\CPayPalGateway';
                break;
            case 'carta-di-credito':
                $className = 'bamboo\\business\\payment\\CCreditCardGateway';
                break;
            case 'bonifico':
                $className = 'bamboo\\business\\payment\\CBonificoGateway';
                break;
            case 'pickandpay':
                $className = 'bamboo\\business\\payment\\CPickAndPayGateway';
                break;
            case 'contrassegno':
                $className = 'bamboo\\business\\payment\\CContrassegnoGateway';
                break;
            default:
                throw new BambooOutOfBoundException('Undefined Payment Method %s', [$order->orderPaymentMethod->name]);
        }
        //$className = 'bamboo\\business\\payment\\C' . $order->orderPaymentMethod->name . 'Gateway';
        $reflection = new \ReflectionClass($className);

        return $reflection->newInstance($this->app);
    }

    /**
     * @param $order
     * @param $paymentMethodId
     * @return bool
     */
    public function setPaymentMethodId($order, $paymentMethodId)
    {
        if (!$order instanceof IEntity) {
            $order = $this->app->entityManagerFactory->create('Order')->findOneBY(['id' => $order]);
            if (!(bool)$order) return false;
        }
        try {

            $order->orderPaymentMethodId = $paymentMethodId;
            $order->update();
            $this->registerEvent($order->id, 'Updating Payment Method', 'Payment Method set to ' . $paymentMethodId, $order->status);

        } catch (\Throwable $e) {
            $this->registerEvent($order->id, 'Fail Updating Payment Method', 'Fail Updating Payment Method to ' . $paymentMethodId, $order->status);
            $this->app->router->response()->raiseUnauthorized();

            return false;
        }

        //$this->app->dbAdapter->update('Order',array('orderPaymentMethodId'=>$paymentMethodId),array('id'=>$order->id));

        return true;
    }

    /**
     * @param $address int|IEntity
     * @return bool
     * TODO verificare se questa cose viene utilizzata e come
     */
    public function setFrozenShippingAddress($order, $address)
    {
        if (!$address instanceof IEntity) {
            $address = $this->app->entityManagerFactory->create('UserAddress')->findOneBY(['id' => $address]);
            if (!(bool)$address) return false;
            $this->setFrozenShippingPrice($order, $address->country->shippingCost);
        }
        if (!$order instanceof IEntity) {
            $order = $this->app->entityManagerFactory->create('Order')->findOneBY(['id' => $order]);
            if (!(bool)$order) return false;
        }
        try {

            $order->frozenShippingAddress = $address->froze();
            $order->update();
            $this->registerEvent($order->id, 'Updating Shipping Address', 'Shipping Price was UserAddress id : ' . $address->id, $order->status);

        } catch (\Throwable $e) {
            $this->registerEvent($order->id, 'Fail Updating Shipping Address', 'Fail Updating Shipping Address to ' . $address->id, $order->status);
            $this->app->router->response()->raiseUnauthorized();

            return false;
        }

        //$this->app->dbAdapter->update('Order',array('frozenShippingAddress'=>serialize($address)),array('id'=>$order->id));

        return true;
    }

    /**
     * @param $order
     * @param $shippingPrice
     * @return bool
     */
    public function setFrozenShippingPrice($order, $shippingPrice)
    {
        if (!$order instanceof IEntity) {
            $order = $this->app->entityManagerFactory->create('Order')->findOneBY(['id' => $order]);
            if (!(bool)$order) return false;
        }
        try {

            $order->shippingPrice = $shippingPrice;
            $order->update();
            $this->registerEvent($order->id, 'Updating Shipping Price', 'Shipping Price set to ' . $shippingPrice, $order->status);

        } catch (\Throwable $e) {
            $this->registerEvent($order->id, 'Fail Updating Shipping Price', 'Fail Updating Shipping Price to ' . $shippingPrice, $order->status);
            $this->app->router->response()->raiseUnauthorized();

            return false;
        }

        //$this->app->dbAdapter->update('Order',array('shippingPrice'=>$shippingPrice),array('id'=>$order->id));

        return true;
    }


}