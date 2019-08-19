<?php

namespace bamboo\domain\repositories;

use bamboo\blueseal\marketplace\prestashop\CPrestashopProduct;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\ecommerce\IOrder;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\core\exceptions\RedPandaLogicException;
use bamboo\domain\entities\CCart;
use bamboo\domain\entities\CCartLine;
use bamboo\domain\entities\COrder;
use bamboo\domain\entities\COrderLine;
use bamboo\domain\entities\COrderStatus;
use bamboo\domain\entities\CPrestashopHasProduct;
use bamboo\domain\entities\CPrestashopHasProductHasMarketplaceHasShop;
use bamboo\domain\entities\CProductPublicSku;
use bamboo\domain\entities\CProductSku;
use bamboo\offline\productsync\import\alducadaosta\CAlducadaostaOrderAPI;
use bamboo\utils\price\SPriceToolbox;
use bamboo\utils\time\STimeToolbox;

/**
 * Class COrderRepo
 * @package bamboo\app\domain\repositories
 */
class COrderRepo extends ARepo
{
    CONST ORDER_PREPARATION_STATUS = 'TRN';
    CONST ORDER_INIT_STATUS = 'ORD_PENDING';

    /**
     * @param $params
     * @return null|object
     */
    public function fetchEntityByJustBought($params)
    {
        $sql = "SELECT id FROM `Order` WHERE id = ? AND userId = ?";
        $res = $this->em()->findBySql($sql, [$params['order'], $this->app->getUser()->getId()]);
        if ($res->count() != 1) {
            return null;
        }
        return $res->getFirst();
    }

    /**
     * @param $params
     * @return null|object
     */
    public function fetchEntityByOrderParam($params)
    {
        $sql = "SELECT id FROM `Order` WHERE id = :id AND userId = :userId";
        $res = $this->em()->findBySql($sql, array("id" => $params['order'], "userId" => $this->app->getUser()->getId()));
        if ($res->count() != 1) {
            return null;
        }
        return $res->getFirst();
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @param array $args
     * @return CObjectCollection
     */
    public function listByAllOrders(array $limit, array $orderBy, array $params, array $args)
    {
        $sql = "SELECT id FROM `Order` WHERE userId = :userId AND `status` LIKE 'ORD%' ORDER BY orderDate DESC " . $this->limit($limit);
        return $this->em()->findBySql($sql, array("userId" => $this->app->getUser()->getId()));
    }

    /**
     * @param string $shopId l'id dello shop. 0 per raccogliere i dati di tutti i negozi
     * @param string $periodType ['year', 'month', 'week', 'day', 'hour']
     * @param string $divisionTime ['month', 'week', 'day', 'hour']
     * @param string $start date, parsed by strtoTime. I dati in uscita hanno ordine cronologico decrescente, perciò start è la data più alta
     * @param array ['fieldname', 'alias'] i campi del select riportati
     * @return array multidimensionale. [righe][campi]
     */
    public function statisticsByDate($shopId, $periodType, $divisionTime, $start = 0, $fields = [])
    {

        // controllo i dati

        $periodTypes = ['year', 'month', 'week', 'day', 'hour'];
        if (($ptk = array_search($periodType, $periodTypes)) === false)
            throw new \Exception('$periodTipe deve avere uno dei seguenti valori: [\'year\', \'month\', \'week\', \'day\', \'hour\']');
        if (!$dtk = in_array($divisionTime, $periodTypes)) {
            throw new \Exception('$divisionTime deve avere uno dei seguenti valori: [\'year\', \'month\', \'week\', \'day\', \'hour\']');
        } else {
            // se l'unità di tempo utilizzata come intervallo è superiore o uguale al periodo preso in esame
            // le query risultanti non hanno senso, quindi va in errore
            if ($dtk < $ptk)
                throw new \Exception("l'unità di tempo presa in esame deve essere più grande dell'unità di tempo degli intervalli");
        }

        if ($start > 0) {
            throw new \Exception('strtotime accetta solo interi negativi');
        }

        $shopWhere = (count($shopId)) ? " AND ol.shopId in (" . implode(',', $shopId) . ") " : '';


        //intervallo di tempo
        $diff = $start; // perché parte dall'ultimo giorno
        $start = date('Y-m-d H:i:s', strtotime("tomorrow -1 second"));

        $startFixed = ($diff < 0) ? strtotime($start . " " . $diff . " " . $periodType) : strtotime($start);

        switch ($periodType) {
            case "year":
                $start = date('Y-12-31 23:59:59', $startFixed);
                $end = date('Y-01-01 00:00:00', $startFixed);
                break;
            case "month":
                $start = date('Y-m-d H:i:s', strtotime("first day of next month midnight -1 second", $startFixed));
                $end = date('Y-m-01 00:00:00', $startFixed);
                break;
            case "week":
                $start = date("Y-m-d H:i:s", strtotime('next monday midnight -1 second', $startFixed));
                $end = date("Y-m-d H:i:s", strtotime('last monday midnight', $startFixed));
                break;
            case "day":
                $start = date('Y-m-d 23:59:59', $startFixed);
                $end = date("Y-m-d 00:00:00", $startFixed);
                break;
            case "hour":
                $start = date('Y-m-d H:i:s', $startFixed);
                $end = date("Y-m-d H:i:s", strtotime($start . " -1 hour"));
                break;
        }
        //\Monkey::dump($start);
        //\Monkey::dump($end);
        //throw new \Exception;
        $when = " AND o.orderDate >= '$end' AND o.orderDate <= '$start' ";


        // group by


        $groupBy = " GROUP BY " . $divisionTime . "  ";

        //prendo i dati dal db

        $sql = "SELECT ol.orderId, ol.id, 
						ifnull(sum(friendRevenue),0) AS friend, 
						ifnull(sum(netPrice),0) AS customer, 
						ifnull(sum(netPrice) - sum(friendRevenue) - sum(ol.vat),0) AS iwes,
						o.orderDate AS orderdate,
						YEAR(orderDate) AS year,
						MONTH(orderDate) AS month,
						WEEKOFYEAR(orderDate) AS week,
						DAYOFYEAR(orderDate) AS day,
						hour(orderDate) AS hour
						FROM 
						#cut
						`Order` o,
						OrderStatus os,
						OrderLine ol, 
						OrderLineStatus ols
						WHERE 	o.id = ol.orderId AND 
								o.status =  os.code AND
								ol.status = ols.code AND
								#cut
								os.id > 2 AND os.id < 11 AND
                                ((ols.id > 2 AND ols.id < 11) OR ols.id = 17 ) AND
							  	o.orderDate IS NOT NULL " . $when . $shopWhere . $groupBy . " ORDER BY orderDate DESC";
        //\Monkey::dump(explode("#cut", $sql));
        //throw new \Exception;
        $data = $this->app->dbAdapter->query($sql, [])->fetchAll();
        //\Monkey::dump($data);

        //inserisco eventuali righe mancanti per mancanza di vendite
        if (count($data)) {

            //definisco i limiti numerici delle varie entità temporali.
            //saranno usati nel loop dove non può essere fatta una comparazione numerica diretta, per l'azzeramento
            switch ($divisionTime) {
                case 'year':
                    $upperLimit = null;
                    break;
                case 'month':
                    $upperLimit = 12;
                    break;
                case 'week':
                    $upperLimit = 54;
                    break;
                case "day":
                    $upperLimit = (date('Y', $startFixed) % 4) ? 366 : 365;
                    break;
                case "hour":
                    $upperlimit = 24;
            }

            //$period = $data[0][$divisionTime];
            foreach ($data as $k => $v) {
                if ($k === 0) continue;

                if (($v[$divisionTime] + 1 != $data[$k - 1][$divisionTime]) && ($v[$divisionTime] != $upperLimit) && ($upperLimit)) {
                    $dataBefore = array_slice($data, 0, $k);
                    $dataAfter = array_slice($data, $k);
                    $newElem = $data[$k - 1];
                    $newElem[$divisionTime] = (string)$data[$k - 1][$divisionTime] - 1;
                    array_push($dataBefore, $newElem);
                    $data = array_merge($dataBefore, $dataAfter);
                    //array_splice($data, $k-1, 0, $data[$k -1]);
                    foreach ($data[$k] as $field => $val) {
                        if (array_search($field, ['year', 'month', 'week', 'day', 'hour', 'orderdate']) === false) {
                            $data[$k][$field] = "0";
                        }
                    }
                }
            }
        }
        //if ($periodType == "year") throw new \Exception;
        return $data;
    }

    /**
     * @param array $shops
     * @param \DateTime $dateFrom
     * @return array
     */
    public function statisticsPoints(array $shops, $dateFrom)
    {
        $questionMarks = [];
        for ($i = 0; $i < count($shops); $i++) $questionMarks[] = '?';
        $shopCondition = implode(',', $questionMarks);
        $sql = "SELECT date(o.orderDate) AS dataOrdine, sum(ifnull(ol.friendRevenue,0)) AS value
                FROM `Order` o, 
                      OrderStatus os, 
                      OrderLine ol, 
                      OrderLineStatus ols
                WHERE o.id = ol.orderId AND 
                    o.status =  os.code AND
                    ol.status = ols.code AND
                    os.id > 2 AND os.id < 11 AND
                    ((ols.id IN(3,4,5,6,7,8,9,10)) OR ols.id = 17 ) AND
                    o.orderDate IS NOT NULL AND ol.shopId IN (" . $shopCondition . ") GROUP BY dataOrdine";
        $cond = [$dateFrom];
        $cond = array_merge($cond, $shops);
        $x = $this->app->dbAdapter->query($sql, $shops)->fetchAll();
        return $x;
    }

    /**
     * Main method to change order status. This method MUST be always used to change it.
     * @param $codeOrEntity
     * @param $status
     * @param null $note
     * @return AEntity|COrder|bool|null
     * @throws BambooException
     * @throws \Exception
     * @throws \PrestaShopWebserviceException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    public function updateStatus($codeOrEntity, $status, $note = null)
    {
        $order = $this->findOrder($codeOrEntity);
        /** @var COrderStatusRepo $osR */
        $osR = \Monkey::app()->repoFactory->create('OrderStatus');
        $os = $osR->findOrderStatus($status);
        $oldOs = $order->orderStatus;
        $code = $os->code;

        switch ($code) {
            case 'ORD_CANCEL':
                $order = $this->updateToCancel($order, $os, $oldOs);

                $orderLines = $order->orderLine;

                /** @var COrderLine $orderLine */
                foreach ($orderLines as $orderLine){
                    $this->updatePrestashopQty($orderLine->productId, $orderLine->productVariantId, $orderLine->productSizeId, null, 1);
                }

                $haveAlduca = false;

                foreach ($orderLines as $ordLineV) {
                    if($ordLineV->shopId == 46) {
                        $haveAlduca = true;
                    }
                }

                if($haveAlduca){
                    $alducaApi = new CAlducadaostaOrderAPI($order->id);
                    $alducaApi->deleteOrder();
                }

                break;
            case 'ORD_PACK':
                $order = $this->updateToPacking($order,$os,$oldOs);
                break;
            case 'ORD_SHIPPED':
                $order = $this->updateToShipped($order, $os, $oldOs);
                break;
            default:
                $order->status = $code;
                $order->update();
        }

        if ($note) {
            $order->note = $note;
            $order->update();
        }

        $this->registerEvent($order->id, 'Change Status', 'Changed to status ' . $os->code, $order->status);


        \Monkey::app()->eventManager->triggerEvent('updateOrderStatus',
            [
                'order' => $order,
                'status' => $order->status
            ]);

        return $order;
    }

    public function findOrder($codeOrEntity)
    {
        $o = false;
        if (is_numeric($codeOrEntity)) {
            $o = $this->findOne([$codeOrEntity]);
        } elseif (is_object($codeOrEntity) && 'Order' === $codeOrEntity->getEntityName()) {
            $o = $codeOrEntity;
        }
        if (!$o) throw new BambooException('the passed order doesn\'t exists');
        return $o;
    }

    /**
     * This method is called by $this->updateStatus when the new status is set to ORD_CANCEL
     * @param COrder $order
     * @param COrderStatus $orderStatus
     * @param COrderStatus $oldOs
     * @return COrder
     * @throws BambooException
     */
    private function updateToCancel(COrder $order, COrderStatus $orderStatus, COrderStatus $oldOs)
    {
        if ('ORD_SHIPPED' === $order->status) throw new BambooException('Non posso cancellare un ordine già spedito');

        $orderLineR = \Monkey::app()->repoFactory->create('OrderLine');

        $iR = \Monkey::app()->repoFactory->create('Invoice');
        //if ($iR->findBy(['orderId' => $order->id])->count())
        //    throw new BambooException('Non può essere cancellato un ordine che ha fatture');
        $order->status = $orderStatus->code;

        foreach ($order->orderLine as $orderLine) {
            $orderLineR->updateStatus($orderLine, 'ORD_CANCEL');
        }
        $order->update();
        return $order;
    }

    /**
     * @param COrder $order
     * @param COrderStatus $orderStatus
     * @param COrderStatus $oldOs
     * @return COrder
     */
    private function updateToShipped(COrder $order, COrderStatus $orderStatus, COrderStatus $oldOs)
    {
        foreach ($order->orderLine as $orderLine) {
            /** @var COrderLine $orderLine */
            \Monkey::app()->repoFactory->create('OrderLine')->updateStatus($orderLine,'ORD_SENT');
            $orderLine->warehouseShelfPositionId = null;
            $orderLine->update();
        }
        $order->status = $orderStatus->code;
        $order->update();
        return $order;
    }

    /**
     * @param COrder $order
     * @param COrderStatus $orderStatus
     * @param COrderStatus $oldOs
     * @return COrder
     */
    private function updateToPacking(COrder $order, COrderStatus $orderStatus, COrderStatus $oldOs)
    {
        foreach ($order->orderLine as $orderLine) {
            /** @var COrderLine $orderLine */
            \Monkey::app()->repoFactory->create('OrderLine')->updateStatus($orderLine,'ORD_PCK_CLI');
            $orderLine->update();
        }
        $order->status = $orderStatus->code;
        $order->update();
        return $order;
    }

    /**
     * This method is ported from COrderManager because this class is deprecated
     * @param $orderId
     * @param $event
     * @param $description
     * @param $status
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
     * @param COrder $order
     * @param string $returnField
     * @return mixed
     */
    public function getOrderStatus(COrder $order, $returnField = 'entity')
    {
        $ols = $order->orderStatus;
        if ('entity') return $ols;
        return $ols->{$returnField};
    }

    /**
     * @param $orderOrOrderLine
     * @param null $statusCode
     * @return CObjectCollection
     */
    public function getStatusHistory($orderOrOrderLine, $statusCode = null)
    {
        $uR = \Monkey::app()->repoFactory->create('User');
        /** @var AEntity $o */
        $o =& $orderOrOrderLine;
        $lOC = $o->getLogs('OrderStatusLog', $statusCode);
        $entityName = $o->getEntityName();
        $repo = \Monkey::app()->repoFactory->create($entityName . 'Status');
        /** @var CObjectCollection $statuses */
        $statuses = $repo->findAll();
        foreach ($lOC as $v) {
            $v->{lcfirst($entityName) . 'Status'} = $statuses->findOneByKey('code', $v->eventValue);
            $v->user = $uR->findOne([$v->userId]);
        }
        return $lOC;
    }

    /**
     * @param COrder $order
     * @param CProductSku $productSku
     * @return AEntity
     */
    public function addOrderLineToOrder(COrder $order, CProductSku $productSku)
    {
        /** @var CStorehouseOperationRepo $soR */
        $soR = \Monkey::app()->repoFactory->create('StorehouseOperation');
        $olR = \Monkey::app()->repoFactory->create('OrderLine');
        $soR->registerEcommerceSale($productSku->shopId, [$productSku], null, true);
        //fillCartValues
        //fillRowsValues
        $ol = $olR->getEmptyEntity();
        $ol->orderId = $order->id;
        $ol->productId = $productSku->productId;
        $ol->productVariantId = $productSku->productVariantId;
        $ol->productSizeId = $productSku->productSizeId;
        $ol->shopId = $productSku->shopId;
        $ol->frozenProduct = $productSku->froze();
        $ol->fullPrice = $productSku->price;
        $ol->activePrice = ($productSku->product->isOnSale) ? $productSku->salePrice : $productSku->price;
        $ol->cost = $productSku->value;
        $ol->insert();

        $ol = $this->getMaxIdOrderLine($order);
        $this->registerEvent($order->id, 'Aggiungo riga a ordine preesistente', 'Riga inserita: ' . $ol->id . '-' . $ol->orderId, $order->status);

        $this->fillOrderValuesByCart($order);
        $this->fillOrderRowsValues($order);


        \Monkey::app()->eventManager->triggerEvent('userCreateOrderLine',
            [
                'orderLine' => $ol,
                'user' => $this->app->getUser(),
            ]);

        return $ol;
    }

    /**
     * @param COrder $order
     */
    public function getMaxIdOrderLine(COrder $order)
    {
        $maxId = 0;
        foreach ($order->orderLine as $oc) {
            if ($oc->id > $maxId) $maxId = $oc->id;
        }
        return $order->orderLine->findOneByKey('id', $maxId);
    }

    /**
     * @param COrder $order
     * @return COrder
     */
    protected function fillOrderValuesByCart(COrder $order)
    {
        $cart = $order->cart;
        /** calcolo i vari importi */
        $order->grossTotal = $cart->getGrossTotal();
        $this->registerEvent($order->id, 'Calcolo grossTotal', 'grossTotal = ' . $order->grossTotal, $order->status);

        $order->shippingPrice = $cart->getShippingModifier($order->grossTotal);
        $this->registerEvent($order->id, 'Calcolo shippingPrice', 'shippingPrice = ' . $order->shippingPrice, $order->status);

        $order->paymentModifier = $cart->getPaymentModifier($order->grossTotal);
        $this->registerEvent($order->id, 'Calcolo paymentModifier', 'paymentModifier = ' . $order->paymentModifier, $order->status);

        $order->couponDiscount = $cart->getCouponModifier();
        $this->registerEvent($order->id, 'Calcolo couponDiscount', 'couponDiscount = ' . $order->couponDiscount, $order->status);

        $this->registerEvent($order->id, 'Calcolo sellingFee', 'sellingFee = ' . $order->sellingFee, $order->status);
        $this->registerEvent($order->id, 'Calcolo customModifier', 'customModifier = ' . $order->customModifier, $order->status);

        /** Totale da pagare * NetTotal */
        $order->netTotal = $order->grossTotal + $order->shippingPrice + $order->paymentModifier + $order->couponDiscount + $order->customModifier;

        $this->registerEvent($order->id, 'Calcolo netTotal', 'netTotal = ' . $order->netTotal, $order->status);

        $order->vat = $this->calculateVat($order);
        $this->registerEvent($order->id, 'Calcolo vat', 'vat = ' . $order->vat, $order->status);

        $order->update();
        return $order;
    }

    /**
     * @param COrder $order
     * @return bool|float|int
     */
    public function calculateVat(COrder $order)
    {
        try {
            if (is_null($order->frozenBillingAddress)) return 0;
            $billing = \bamboo\domain\entities\CUserAddress::defrost($order->frozenBillingAddress);
            $vatPercent = $billing->country->vat;
            $vat = SPriceToolbox::vatFromGross($order->netTotal, $vatPercent, true);
            return $vat;
        } catch (\Throwable $e) {
            \Monkey::app()->applicationWarning('OrderRepo', 'Error Calculating Vat', 'Cart: ' . $order->id . ' netTotal:' . $order->netTotal, $e);
        }
        return false;
    }

    /**
     * Fills the order values
     *
     * @param COrder $order
     * @param null $orderLineStatus
     * @return COrder|bool
     * @throws RedPandaLogicException
     */
    public function fillOrderRowsValues(COrder $order, $orderLineStatus = null)
    {
        if (!$order->grossTotal) $this->fillOrderValues($order);
        foreach ($order->orderLine as $orderLine) {
            /** @var COrderLine $orderLine */
            if ($orderLine->orderLineStatus->isActive) {
                $activePrice = $orderLine->activePrice ? $orderLine->activePrice : $orderLine->productSku->getActivePrice();
                $weight = $order->grossTotal / $activePrice;
                $shippingCharge = $order->shippingPrice / $weight;
                $couponCharge = $order->couponDiscount / $weight;
                $userCharge = $order->userDiscount / $weight;
                $paymentCharge = $order->paymentModifier / $weight;
                $sellingFeeCharge = $order->sellingFee / $weight;
                $customModifierCharge = $order->customModifier / $weight;
                $vat = $order->vat / $weight;
                $netPrice = $activePrice + $shippingCharge + $couponCharge + $userCharge + $paymentCharge;
                try {
                    $orderLine->status = ($orderLineStatus) ? $orderLineStatus : 'ORD_WAIT';
                    $orderLine->activePrice = $activePrice;
                    $orderLine->vat = $vat;

                    $orderLine->shippingCharge = $shippingCharge;
                    $orderLine->couponCharge = $couponCharge;
                    $orderLine->userCharge = $userCharge;
                    $orderLine->paymentCharge = $paymentCharge;
                    $orderLine->sellingFeeCharge = $sellingFeeCharge;
                    $orderLine->customModifierCharge = $customModifierCharge;
                    $orderLine->netPrice = $netPrice;
                    $orderLine->update();

                } catch (\Throwable $e) {
                    $this->registerEvent($order->id, 'Eccezione Registro Riga Ordine', $e->getTraceAsString(), $order->status);
                    throw new RedPandaLogicException('Non ho potuto calcolare i valori di riga per l\'ordine: %s', [$order->id], 0, $e);
                }

            } else {
                $this->registerEvent($order->id, 'Errore Registro Riga Ordine', 'Sku non trovato mentre registravo la riga ordine' . $orderLine->id, $order->status);
                return false;
            }
        }
        return $order;
    }

    /**
     * @param COrder $order
     * @return mixed
     * @deprecated
     */
    public function fillOrderValues(COrder $order)
    {
        /** calcolo i vari importi */
        $order->grossTotal = $this->calculateGrossTotal($order);
        $this->registerEvent($order->id, 'Calcolo grossTotal', 'grossTotal = ' . $order->grossTotal, $order->status);

        $order->shippingPrice = $this->calculateShippingTotal($order);
        $this->registerEvent($order->id, 'Calcolo shippingPrice', 'shippingPrice = ' . $order->shippingPrice, $order->status);

        $order->paymentModifier = $this->calculatePaymentModifier($order);
        $this->registerEvent($order->id, 'Calcolo paymentModifier', 'paymentModifier = ' . $order->paymentModifier, $order->status);

        $order->couponDiscount = $this->calculateCouponModifier($order);
        $this->registerEvent($order->id, 'Calcolo couponDiscount', 'couponDiscount = ' . $order->couponDiscount, $order->status);

        $this->registerEvent($order->id, 'Calcolo sellingFee', 'sellingFee = ' . $order->sellingFee, $order->status);
        $this->registerEvent($order->id, 'Calcolo customModifier', 'customModifier = ' . $order->customModifier, $order->status);

        /** Totale da pagare * NetTotal */
        $order->netTotal = $order->grossTotal + $order->shippingPrice + $order->paymentModifier + $order->couponDiscount + $order->customModifier;

        $this->registerEvent($order->id, 'Calcolo netTotal', 'netTotal = ' . $order->netTotal, $order->status);

        $order->vat = $this->calculateVat($order);
        $this->registerEvent($order->id, 'Calcolo vat', 'vat = ' . $order->vat, $order->status);

        $order->update();
        return $order;
    }

    /**
     * @param COrder $order
     * @return bool|int
     */
    private function calculateGrossTotal(COrder $order)
    {
        /** Totale prodotti * GrossTotal */
        try {
            $grossTotal = 0;
            foreach ($order->orderLine as $line) if ($line->orderLineStatus->isActive) $grossTotal += $line->activePrice ?? $line->productSku->getActivePrice();
            return $grossTotal;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * NOTA deve essere allineato con quello nel cartRepo / cart
     *
     * @param COrder $order
     * @return float|mixed
     */
    public function calculateShippingTotal(COrder $order)
    {
        /** Spese di spedizione */
        if (!is_null($order->orderPaymentMethod) && $order->orderPaymentMethod->name == 'pickandpay') {
            return (double)0;
        }
        if (!is_null($order->frozenShippingAddress)) {
            $address = \bamboo\domain\entities\CUserAddress::defrost($order->frozenShippingAddress);
            $country = $address->country;
        } elseif (!is_null($order->frozenBillingAddress)) {
            $address = \bamboo\domain\entities\CUserAddress::defrost($order->frozenBillingAddress);
            $country = $address->country;
        } elseif (!is_null($order->user) && (
            $address = $order->user->userAddress->findOneByKeys(['isDefault' => 1, 'isBilling' => 0]) ||
                $address = $order->user->userAddress->findOneByKeys(['isDefault' => 1, 'isBilling' => 1]))
        ) {
            $country = $address->country;

        } elseif ($this->app->getUser()->id != 0 && (
            $address = $this->app->getUser()->userAddress->findOneByKeys(['isDefault' => 1, 'isBilling' => 0]) ||
                $address = $this->app->getUser()->userAddress->findOneByKeys(['isDefault' => 1, 'isBilling' => 1]))
        ) {
            $country = $address->country;
        } else {
            $countryId =
                \Monkey::app()->repoFactory->create('Configuration')->getConfiguration('core', 'default-country-id');
            $country = \Monkey::app()->repoFactory->create('Country')->findOne([$countryId]);
        }

        if ($order->grossTotal < $country->freeShippingLimit) {
            return $country->shippingCost;
        } else {
            return (double)0;
        }
    }

    /**
     * @param COrder $order
     * @return int|mixed
     */
    public function calculatePaymentModifier(COrder $order)
    {
        /** Metodo di pagamento */
        $paymentModifier = 0;
        if (isset($order->orderPaymentMethod->modifier) && ($modifier = $order->orderPaymentMethod->modifier) != null) {
            if (strstr($modifier, '%')) {
                $mod = substr($modifier, 0, strpos($modifier, '%'));
                $paymentModifier = $order->grossTotal * $mod * 0.01;
            } else {
                $paymentModifier += $modifier;
            }
        }
        return $paymentModifier;
    }

    /**
     * @param $order
     * @return int
     * @throws RedPandaLogicException
     */
    public function calculateCouponModifier($order)
    {
        /** Sconto dal Coupon */
        $couponDiscount = 0;
        if (!is_null($order->coupon) && isset($order->coupon->amountType)) {
            switch ($order->coupon->amountType) {
                case 'P':
                    $couponDiscount = -1 * $order->grossTotal * $order->coupon->amount * 0.01;
                    break;
                case 'F':
                    $couponDiscount = -1 * $order->coupon->amount;
                    break;
                case 'G':
                    $fullPrice = $this->calculateFullPriceTotal($order);
                    $couponDiscount = -1 * ($order->grossTotal - ($fullPrice - ($fullPrice * $order->coupon->amount * 0.01)));
                    break;
                default:
                    throw new RedPandaLogicException('Coupon type not supported %s', [$order->coupon->amountType]);
            }

        }
        return $couponDiscount;
    }

    /**
     * @param $order
     * @return bool|int
     */
    public function calculateFullPriceTotal($order)
    {
        /** Totale prodotti * Full Price */
        $total = 0;
        try {
            foreach ($order->orderLine as $line) {
                if ($line->isActive)
                    $total += $line->fullPrice;
            }
            return $total;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Returns the last order Entity
     * @return bool|\bamboo\core\db\pandaorm\entities\IEntity
     */
    public function lastOrder()
    {
        try {
            if ($id = $this->lastOrderId()) {
                return \Monkey::app()->repoFactory->create('Order')->findOne([$id]);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * Returns the last order or false if fails
     * @return bool
     */
    public function lastOrderId()
    {
        try {
            $userId = $this->app->getUser()->getId();
            $id = $this->app->dbAdapter->query('SELECT id FROM `Order` WHERE userId = ? ORDER BY orderDate DESC LIMIT 0,1', [$userId])->fetch()['id'];
            if ((bool)$id) return $id;

        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    /**
     * @param CCart $cart
     * @return AEntity|COrder
     * @throws BambooException
     * @throws \PrestaShopWebserviceException
     */
    public function prepareNewOrderFromCart(CCart $cart)
    {
        $productSkuRepo=\Monkey::app()->repoFactory->create('ProductSku');
        $productPublicSkuRepo=\Monkey::app()->repoFactory->create('ProductPublicSku');
        /** @var COrder $order */
        $order = $this->getEmptyEntity();
        $order->orderPaymentMethodId = $cart->orderPaymentMethodId;
        $order->couponId = $cart->couponId;

        if (is_null($cart->userId)) throw new BambooException('User Not Set for Cart ' . $cart->id);
        $order->userId = $cart->userId;
        $order->status = self::ORDER_PREPARATION_STATUS;
        $order->billingAddressId = $cart->billingAddressId;
        $order->shipmentAddressId = $cart->shipmentAddressId;
        $order->frozenBillingAddress = $cart->billingAddress->froze();
        $order->frozenShippingAddress = $cart->shipmentAddress->froze();
        //TODO prendere i valori dal carrello direttamente????
        $order->grossTotal = $cart->getGrossTotal();
        $order->shippingPrice = $cart->getShippingModifier($order->grossTotal);
        $order->cartId = $cart->id;
        $order->hasInvoice=$cart->hasInvoice;
        $order->smartInsert();
        $order = $this->fillOrderValuesByCart($order);

        foreach ($cart->cartLine as $cartLine) {
            /** @var CCartLine $cartLine */
            /** @var COrderLine $orderLine */
            $orderLine = \Monkey::app()->repoFactory->create('OrderLine')->getEmptyEntity();
            /** @var CProductSku $productSku */
            $productSku = $cartLine->productPublicSku->getActualDisposableSku();
            /** @var CProductSku $productSkuFind */
            $productSkuFind=$productSkuRepo->findOneBy(['productId'=>$cartLine->productId,'productVariantId'=>$cartLine->productVariantId,'productSizeId'=>$cartLine->productSizeId]);
            /** @var CProductPublicSku $productPublicSkuFind */
            $productPublicSkuFind=$productPublicSkuRepo->findOneBy(['productId'=>$cartLine->productId,'productVariantId'=>$cartLine->productVariantId,'productSizeId'=>$cartLine->productSizeId]);
            $orderLine->orderId = $order->id;
            $orderLine->productId = $cartLine->productId;
            $orderLine->productVariantId = $cartLine->productVariantId;
            $orderLine->productSizeId = $cartLine->productSizeId;
            $orderLine->shopId = $productSkuFind->shopId;
            $orderLine->cost = $productSkuFind->value;
            $orderLine->status = $orderLine::INIT_STATUS;
            $orderLine->frozenProduct = $productSkuFind->froze();
            $orderLine->smartInsert();
            \Monkey::app()->repoFactory->create('ProductSku')->saveQty($orderLine->productSku);
            $this->fillOrderLineValuesByCartLine($orderLine, $cartLine);
            $findPrestashopHasProduct=\Monkey::app()->repoFactory->create('PrestashopHasProduct')-$this->findOneBy([
                'productId'=>$orderLine->productId,
                'productVariantId'=>$orderLine->productVariantId]);
            if($findPrestashopHasProduct!=null) {
                $this->updatePrestashopQty($orderLine->productId, $orderLine->productVariantId, $orderLine->productSizeId, null, -1);
            }
        }


        //$order = $this->fillOrderRowsValues($order);

        return $order;
    }

    /**
     * @param $productId
     * @param $productVariantId
     * @param $productSizeId
     * @param null $newQty
     * @param null $differential
     * @return bool
     * @throws \PrestaShopWebserviceException
     */
    private function updatePrestashopQty($productId, $productVariantId, $productSizeId, $newQty = null, $differential = null) : bool
    {

        /** @var CPrestashopHasProductRepo $phpRepo */
        $phpRepo = \Monkey::app()->repoFactory->create('PrestashopHasProduct');

        /** @var CPrestashopHasProduct $php */
        $php = $phpRepo->findOneBy(['productId'=>$productId, 'productVariantId'=>$productVariantId]);

        if($php!=null) {
            $prestashopShopIds = $php->getShopsForProduct();
            $prestashopProduct = new CPrestashopProduct();
            $prestashopProduct->updateProductQuantity($php->prestaId, $productSizeId, $newQty, $differential, $prestashopShopIds);
            return true;
        }else{
            return false;
        }
    }

    /**
     * @param COrderLine $orderLine
     * @param CCartLine $cartLine
     * @return bool
     */
    protected function fillOrderLineValuesByCartLine(COrderLine $orderLine, CCartLine $cartLine)
    {
        $orderLine->fullPrice = $cartLine->getLineFullPrice();
        $orderLine->activePrice = (float)$cartLine->getLineGrossTotal();
        if ($orderLine->activePrice == 0) {
            \Monkey::app()->applicationWarning(
                'OrderRepo',
                'Active price set at 0 in fillOrderLineValuesByCartLine',
                'active price found at 0 while calculating weight for cartLine: '.$orderLine->printId(). 'and orderLine: '.$orderLine->printId());
        }

        $weight = $orderLine->order->grossTotal / $orderLine->activePrice;

        $orderLine->vat = $orderLine->order->vat / $weight;
        $orderLine->shippingCharge = $orderLine->order->shippingPrice / $weight;
        $orderLine->couponCharge = $cartLine->getCouponDiscount();
        $orderLine->userCharge = $orderLine->order->userDiscount / $weight;
        $orderLine->paymentCharge = $orderLine->order->paymentModifier / $weight;
        $orderLine->sellingFeeCharge = $orderLine->order->sellingFee / $weight;
        $orderLine->customModifierCharge = $orderLine->order->customModifier / $weight;

        $orderLine->netPrice = $orderLine->activePrice +
            $orderLine->shippingCharge +
            $orderLine->couponCharge +
            $orderLine->userCharge +
            $orderLine->paymentCharge +
            $orderLine->sellingFeeCharge +
            $orderLine->customModifierCharge;

        $orderLine->update();
        return true;
    }

    /**
     * Adds a line to order starting for a sku of a product and a quantity
     * does not check values!!
     * @param COrder $order
     * @param CProductSku $productSku
     * @param int $qty
     * @return int
     */
    public function addSku(COrder $order, CProductSku $productSku, $qty = 1)
    {
        $lines = $order->cartLine->findByKeys(['productId' => $productSku->productId,
            'productVariantId' => $productSku->productVariantId,
            'productSizeId' => $productSku->productSizeId]);
        if ($productSku->stockQty < (count($lines) + $qty)) return -502;

        $count = 0;
        try {
            $orderLine = \Monkey::app()->repoFactory->create('OrderLine')->getEmptyEntity();

            $orderLine->cartId = $order->id;
            $orderLine->productId = $productSku->productId;
            $orderLine->productVariantId = $productSku->productVariantId;
            $orderLine->shopId = $productSku->shopId;
            $orderLine->productSizeId = $productSku->productSizeId;

            while ($qty > 0) {
                $qty--;
                if (($orderLine->id = $orderLine->insert()) > 0) {
                    $count++;
                    $description = 'Cart: ' . $order->id . ',' . implode(',', $orderLine->toArray()) . ' for user: ' . $this->app->getUser()->getId() . ' in session: ' . $this->app->getSession()->getSid();
                    $this->registerEvent($order->id, 'Item added to Cart', $description, $order->status);
                }
            }

            return $count;
        } catch (\Throwable $e) {
            return $count > 0 ? -1 * 5000 + $count : 5000;
        }
    }

    /**
     * @param COrder $order
     * @return bool
     * @throws BambooLogicException
     */
    public function finalizeNewOrder(COrder $order)
    {
        if ($order->coupon) {
            $order->coupon->valid = 0;
            $order->coupon->update();
        }

        if ($order->status != self::ORDER_PREPARATION_STATUS) throw new BambooLogicException('Order not in preparation while finalizing ' . $order->id);
        $order->status = self::ORDER_INIT_STATUS;
        $order->orderDate = STimeToolbox::DbFormattedDateTime();
        $order->update();
        return true;
    }
}