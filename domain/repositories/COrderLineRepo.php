<?php


namespace bamboo\domain\repositories;

use bamboo\core\ecommerce\IBillingLogic;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOrderLineException;
use bamboo\core\exceptions\RedPandaException;
use bamboo\domain\entities\COrder;
use bamboo\domain\entities\COrderLine;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\COrderLineStatus;
use bamboo\domain\entities\CProductSku;
use bamboo\utils\time\STimeToolbox;
use PDO;
use PDOException;

/**
 * Class COrderStatusRepo
 * @package bamboo\app\domain\repositories
 */
class COrderLineRepo extends ARepo
{

    /**
     * @param $stringId
     * @param $status
     * @param null $time
     * @return \bamboo\core\db\pandaorm\entities\AEntity|bool
     * @throws BambooException
     * @deprecated
     */
    public function updateFriendPaymentStatus($stringId,$status,$time = null)
    {
        if (is_object($stringId)) $ol = $stringId;
        else $ol = $this->findOneByStringId($stringId);

        $olfpsR = \Monkey::app()->repoFactory->create('OrderLineFriendPaymentStatus');
        if (!is_numeric($status)) {
            $status = $olfpsR->findOneBy(['code' => $status]);
        } else {
            $status = $olfpsR->findOneBy(['id' => $status]);
        }
        if (!isset($status)) throw new BambooException("Hai tentato di inserire uno stato ordine inesistente");

        if ($ol->orderLineFriendPaymentStatusId != $status->id) {
            $ol->orderLineFriendPaymentStatusId = $status->id;
            if ($time) $ol->orderLineFriendPaymentDate = $time;
            $ol->update();
            $this->logFriendPaymentUpdate(
                $ol->orderLineFriendPaymentStatus->code,
                'OrderLine',
                $ol->printId(),
                \Monkey::app()->getUser()->id
            );
            return $ol;
        }
        return false;
    }

    /**
     * @param COrderLine $orderLine
     * @param $verdict
     * @throws BambooException
     * @throws BambooOrderLineException
     */
    public function setFriendVerdict(COrderLine $orderLine,$verdict)
    {
        /** @var CLogRepo $lR */
        $lR = \Monkey::app()->repoFactory->create('Log');
        /** @var COrderLineRepo $olR */
        $olR = \Monkey::app()->repoFactory->create('OrderLine');
        if ('ok' === $verdict || 1 === $verdict) $verdict = 'ORD_FRND_OK';
        if ('ko' === $verdict || 0 === $verdict) $verdict = 'ORD_FRND_CANC';
        if ('ORD_FRND_OK' !== $verdict && 'ORD_FRND_CANC' !== $verdict) throw new BambooException('Status non accettato per questa operazione');
        $stringId = $orderLine->printId();
        $statusId = $orderLine->orderLineStatus->id;
        if (4 > $statusId || 8 < $statusId) {
            throw new BambooOrderLineException('Lo stato della linea ordine ' . $stringId . ' non può essere aggiornato');
        }
        if ('ORD_FRND_CANC' === $verdict) {
            $allShops = \Monkey::app()->getUser()->hasPermission('allShops');
            if (!$allShops) {
                $last = $lR->getLastEntry(
                    [
                        'stringId' => $stringId,
                        'eventValue' => 'ORD_FRND_OK'
                    ]
                );
                if ($last) {
                    throw new BambooOrderLineException('<p>La riga d\'ordine <strong>' . $stringId . '</strong> è stata precedentemente accettata e non può essere cancellata.</p>');
                }
            }
        }
        $olR->updateStatus($orderLine,$verdict);


        if ('ORD_FRND_CANC' === $verdict) {
            iwesMail(
                'friends@iwes.it','Rifiuto Friend',"L'utente " .
                $this->app->getUser()->getFullName() . " ha rifiutato l'ordine: " .
                $orderLine->printId() . " per il friend " . $orderLine->shop->title
            );
        }

        $accepted = ('ORD_FRND_OK' === $verdict) ? true : false;
        $psk = \Monkey::app()->repoFactory->create('ProductSku')->findOne(
            [
                $orderLine->productId,
                $orderLine->productVariantId,
                $orderLine->productSizeId,
                $orderLine->shopId
            ]
        );
        \Monkey::app()->repoFactory->create('StorehouseOperation')->registerEcommerceSale(
            $orderLine->shopId,[$psk],null,$accepted
        );
    }

    /**
     * @param $value
     * @param $entityName
     * @param $stringId
     * @param $userId
     */
    private function logFriendPaymentUpdate($value,$entityName,$stringId,$userId)
    {
        \Monkey::app()->eventManager->triggerEvent(
            'changeOrderLineFriendPaymentStatus',
            [
                'value' => $value,
                'entityName' => $entityName,
                'stringId' => $stringId,
                'userId' => $userId
            ]
        );
    }

    /**
     * @param COrderLine $orderLine
     * @return string
     */
    public function getOrderLineDescription(COrderLine $orderLine)
    {
        $product = $orderLine->product;
        $description = 'Ord: ' . $orderLine->printId() . ' - cod. p.: ' . $product->printId();
        $description .= ' - cpf: ' . $product->itemno . ' # ' . $product->productVariant->name;
        $description .= ' - brand: ' . $product->productBrand->name . ' - size: ' . $orderLine->productSize->name;
        return $description;
    }

    /**
     * @param $orderLine
     * @param $newStatus
     * @return mixed
     * @throws RedPandaException
     */
    public function updateStatus($orderLine,$newStatus,$time = null)
    {
        $olsR = \Monkey::app()->repoFactory->create('OrderLineStatus');
        $newStatusE = null;
        if ($newStatus instanceof COrderLineStatus) $newStatusE = $newStatus;

        if (!($newStatus instanceof COrderLineStatus)) {
            $newStatusE = $olsR->findOneBy(['id' => $newStatus]);
        }

        if (!($newStatusE instanceof COrderLineStatus)) {
            $newStatusE = $olsR->findOneBy(['code' => $newStatus]);
        }
        if (!($newStatusE instanceof COrderLineStatus)) {
            throw new RedPandaException("Can't find the status you are speaking about");
        }

        /** @var  $this ->app->dbAdapter CMySQLAdapter */
        $this->log($orderLine,"Change Line","Changing Status to " . $newStatusE->code);
        \Monkey::app()->eventManager->triggerEvent("orderLineStatusChange",['orderLine' => $orderLine,'newStatus' => $newStatusE]);

        $code = $newStatusE->code;
        $oldStatus = $orderLine->orderLineStatus;
        $shopRepo = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $orderLine->remoteShopSellerId]);
        $orderRepo = \Monkey::app()->repoFactory->create('Order')->findOneBy(['id' => $orderLine->orderId,'remoteShopSellerId' => $orderLine->remoteShopSellerId]);
        if ($orderLine->remoteOrderSellerId != null) {
            $db_host = $shopRepo->dbHost;
            $db_name = $shopRepo->dbName;
            $db_user = $shopRepo->dbUsername;
            $db_pass = $shopRepo->dbPassword;
            $shop = $shopRepo->id;
            try {

                $db_con = new PDO("mysql:host={$db_host};dbname={$db_name}",$db_user,$db_pass);
                $db_con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                $res = ' connessione ok <br>';
            } catch (PDOException $e) {
                $res = $e->getMessage();
            }

            $stmtOrder = $db_con->prepare("UPDATE `Order` SET `status`='" . $orderRepo->status . "' WHERE id=" . $orderRepo->remoteOrderSellerId);
            $stmtOrder->execute();
            $stmtOrderLine = $db_con->prepare("UPDATE OrderLine SET `status`='" . $code . "' WHERE id=" . $orderLine->remoteOrderLineSellerId . " and orderId=" . $orderLine->remoteOrderSellerId);
            $stmtOrderLine->execute();
        }


        switch ($code) {
            case 'ORD_CANCEL':
                $orderLine = $this->updateToCancel($orderLine,$newStatusE,$oldStatus);
                break;
            case 'ORD_FRND_OK':
                $orderLine = $this->updateToFriendOk($orderLine,$newStatusE,$oldStatus);
                break;
            case 'ORD_FRND_ORDSNT':
                $orderLine = $this->updateFriendOrderSent($orderLine,$newStatusE,$time);
                break;
            case 'ORD_FRND_SNDING':

                $orderLine = $this->updateFriendOrderSending($orderLine,$newStatusE);
            default:
                $orderLine->status = $newStatusE->code;
                $orderLine->update();
        }

        //Se cambia il flag isActive, si ricalcolano i totali del carrello.
        //if ($oldStatus->isActive != $newStatusE->isActive) {
        //    /** @var COrderRepo $oR */
        //    $oR = \Monkey::app()->repoFactory->create('Order');
        //    $oR->fillOrderValues($orderLine->order);
        //    $oR->fillRowsValues($orderLine->order);
        //}

        \Monkey::app()->eventManager->triggerEvent('changeOrderLineStatus',
            [
                'order' => $orderLine,
                'status' => $orderLine->status,
                'oldStatus' => $oldStatus,
                'time' => $time
            ]
        );
        return $orderLine;
    }

    private function updateToCancel($orderLine,$newStatus,$oldStatus)
    {

        $orderLine->status = $newStatus->code;
        $orderLine->update();

        $sku = $orderLine->productSku;
        if ($sku->padding < 0) {
            $sku->padding++;
            $sku->stockQty++;
            $sku->update();
        }
        return $orderLine;
    }

    /**
     * @param COrderLine $orderLine
     * @param $newStatus
     * @param $oldStatus
     */
    private function updateToFriendOk(COrderLine $orderLine,$newStatus,$oldStatus)
    {
        $sentLog = $orderLine->getStatusLog('ORD_FRND_SENT');
        $days = 0;
        if ($sentLog) {
            $now = new \DateTime();
            $sentTime = new \DateTime($sentLog->time);
            $sentTime = STimeToolbox::getNextWorkingTime($sentTime);
            $days = $now->diff($sentTime)->d;
        }
        $olsR = \Monkey::app()->repoFactory->create('OrderLineStatistics');
        $ols = $olsR->findOneByStringId($orderLine->stringId());
        if (!$ols) $myOls = $olsR->getEmptyEntity();
        else $myOls = $ols;
        $myOls->friendConfirmationDays = $days;
        if (!$ols) {
            $myOls->orderLineId = $orderLine->id;
            $myOls->orderId = $orderLine->orderId;
            $myOls->insert();
        } else $myOls->update();

        $orderLine->status = $newStatus->code;
        $orderLine->update();

        return $orderLine;
    }

    /**
     * @param $orderLine
     * @param $newStatus
     * @param null $time
     */

    private function updateFriendOrderSending($orderLine,$newStatus)
    {
        $orderLine->status = $newStatus->code;

        $findShopId = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $orderLine->shopId]);
        if ($findShopId->hasEcommerce == '1' && $findShopId->id != '44') {
            /* find  orderId*/
            $orderForRemote = \Monkey::app()->repoFactory->create('Order')->findOneBy(['id' => $orderLine->orderId]);
            $cartForRemote = \Monkey::app()->repoFactory->create('Cart')->findOneBy(['id' => $orderForRemote->cartId]);
            $db_host = $findShopId->dbHost;
            $db_name = $findShopId->dbName;
            $db_user = $findShopId->dbUsername;
            $db_pass = $findShopId->dbPassword;
            switch ($orderLine->shopId) {
                case '1':
                    $userRemoteId = 5;
                    $billingAddressId = 2;
                    $shipmentAddressId = 2;
                    break;
                case '51':
                    $userRemoteId = 52;
                    $billingAddressId = 105;
                    $shipmentAddressId = 105;
                    break;
            }
            try {

                $db_con = new PDO("mysql:host={$db_host};dbname={$db_name}",$db_user,$db_pass);
                $db_con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                $res = " connessione ok <br>";
            } catch (PDOException $e) {
                throw new BambooException('fail to connect');

            }
            if($cartForRemote->hasInvoice==null){
                $hasInvoice='null';
            }else{
                $hasInvoice=$cartForRemote->hasInvoice;
            }
            try {
                $insertRemoteCart = $db_con->prepare("INSERT INTO Cart (orderPaymentMethodId,
                                                                          couponId,
                                                                          userId,
                                                                          cartTypeId,  
                                                                          billingAddressId,
                                                                          shipmentAddressId,
                                                                          lastUpdate,
                                                                          creationDate,
                                                                          hasInvoice )
                                                                           VALUES (
                                                                          " . $orderForRemote->orderPaymentMethodId . ",
                                                                          null,
                                                                          " . $userRemoteId . ",
                                                                          " . $cartForRemote->cartTypeId . ",
                                                                          " . $billingAddressId . ",
                                                                          " . $shipmentAddressId . ",
                                                                          '" . $cartForRemote->lastUpdate . "',
                                                                          '" . $cartForRemote->creationDate . "',
                                                                          " . $hasInvoice . ")");
                $insertRemoteCart->execute();
            } catch (\Throwable $e) {
                \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Cart to Shop ' . $findShopId->id,$e);
            }

            $findLastRemoteCart = $db_con->prepare("select MAX(id) as cartId from Cart ");
            $findLastRemoteCart->execute();
            $rowFindLastRemoteCart = $findLastRemoteCart->fetch(PDO::FETCH_ASSOC);
            $cartId = $rowFindLastRemoteCart['cartId'];
            /*
            try {
                $insertRemoteCartLine = $db_con->prepare(sprintf('INSERT INTO CartLine (cartId, productId,productVariantId,productSizeId)
VALUES(%s,%s,%s,%s)',$cartId,$orderLine->productId,$orderLine->productVariantId,$orderLine->productSizeId));
                $insertRemoteCartLine->execute();
            } catch (\Throwable $e) {
                \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote CartLine to Shop ' . $findShopId->id,$e);
            }
            */
            $productSku = \Monkey::app()->repoFactory->
            create('ProductSku')->
            findOneBy(
                ['productId' => $orderLine->productId,
                    'productVariantId' => $orderLine->productVariantId,
                    'productSizeId' => $orderLine->productSizeId]);
            $friendRevenue=$orderLine->friendRevenue;
            $vat = ($friendRevenue / 100) * 22;
            $revenueTotal = $friendRevenue + $vat;
            if ($orderForRemote->remoteShopSellerId == '44') {
                $isOrdermarketplace = '1';
            } else {
                $isOrderMarketplace = '0';
            }
            try {
                $insertRemoteOrder = $db_con->prepare("INSERT INTO `Order` (
            orderPaymentMethodId,
            orderShippingMethodId,
            couponId,
            userId,
            cartId,
          `status`,
           frozenBillingAddress,
           frozenShippingAddress,
           billingAddressId,
           shipmentAddressId,
           shippingPrice,
           userDiscount,
           couponDiscount,
           paymentModifier,
           grossTotal,
           netTotal,
           `vat`,
           sellingFee,
           customModifier,
           orderDate,
           `note`,
           shipmentNote,  
           transactionNumber,          
           transactionMac,
           paidAmount,
           paymentDate,
           lastUpdate,
           creationDate,
           hasInvoice,
           remoteIwesOrderId,          
           isParallel,
           remoteSellerId,
           isOrderMarketplace,
           marketplaceId,
           marketplaceOrderId          
           ) VALUES (
            6,
            null,
            null,
            " . $userRemoteId . ",
            " . $cartId . ",
            '" . $orderForRemote->status . "',
            '" . $orderForRemote->frozenBillingAddress . "',
            '" . $orderForRemote->frozenShippingAddress . "',
            " . $billingAddressId . ",
            " . $shipmentAddressId . ",
            0,
            0,
            0,
            0,
            " . $revenueTotal . ",
            " . $revenueTotal . ",
            " . $vat . ",
            0,
            0,
            '" . $orderForRemote->orderDate . "',
            '" . $orderForRemote->note . "',
            '" . $orderForRemote->shipmentNote . "',
            '" . $orderForRemote->transactionNumber . "',
            '" . $orderForRemote->transactionMac . "',
            " . $revenueTotal . ",
             '" . date("Y-m-d H:i:s") . "',
            '" . date("Y-m-d H:i:s") . "',
            '" . date("Y-m-d H:i:s") . "',
            1,
            " . $orderLine->orderId . ",
            1,
             '" . $orderForRemote->remoteShopSellerId . "',
             '" . $isOrderMarketplace . "',
             0,           
             " . $orderLine->orderId . ")");
                $insertRemoteOrder->execute();
            } catch (\Throwable $e) {
                \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote order to Shop ' . $findShopId->id,$e);
            }
            $findLastRemoteOrder = $db_con->prepare("select MAX(id) as orderId from `Order` ");
            $findLastRemoteOrder->execute();
            $rowFindLastRemoteOrder = $findLastRemoteOrder->fetch(PDO::FETCH_ASSOC);
            $orderId = $rowFindLastRemoteOrder['orderId'];
            try {
                if ($findShopId->id != '1') {
                    $insertRemoteOrderLine = $db_con->prepare('INSERT INTO OrderLine (
                      `orderId`,
                      `productId`,
                      `productVariantId`,
                      `productSizeId`,
                       `shopId`,  
                       `status`,
                       `orderLineFriendPaymentStatusId`,
                       `orderLineFriendPaymentDate`, 
                       `warehouseShelfPositionId`,
                       `frozenProduct`,
                       `fullPrice`, 
                       `activePrice`, 
                       `vat`,
                       `cost`, 
                       `shippingCharge`, 
                       `couponCharge`, 
                       `userCharge`,  
                       `paymentCharge`,
                       `sellingFeeCharge`,
                       `customModifierCharge`,
                       `netPrice`, 
                       `friendRevenue`, 
                       `creationDate`, 
                       `lastUpdate`,
                       `note`,
                       `remoteId` 
                       VALUES (
                          ' . $orderId . ',
                          ' . $orderLine->productId . ',
                          ' . $orderLine->productVariantId . ',
                          ' . $orderLine->productSizeId . ',
                          ' . $orderLine->shopId . ',
                          \'' . $newStatus->code . '\',
                          null,
                          null,
                          null,
                          \'' . $orderLine->frozenProduct . '\',
                          ' . $revenueTotal . ',
                          ' . $revenueTotal . ',
                          ' . $vat . ',
                          ' . $orderLine->cost . ',
                          0,
                          0,
                          0,
                          0,
                          0,
                          0,
                          ' . $friendRevenue . ',
                          ' . $friendRevenue . ',
                          \'' . $orderLine->creationDate . '\',
                          \'' . $orderLine->lastUpdate . '\',
                          \'' . $orderLine->note . '\',
                          null)');

                } else {

                    $insertRemoteOrderLine = $db_con->prepare('INSERT INTO OrderLine (
                      `orderId`,
                      `productId`,
                      `productVariantId`,
                      `productSizeId`,
                       `shopId`,  
                       `status`,
                       `orderLineFriendPaymentStatusId`,
                       `orderLineFriendPaymentDate`,
                       `frozenProduct`,
                       `fullPrice`, 
                       `activePrice`, 
                       `vat`,
                       `cost`, 
                       `shippingCharge`, 
                       `couponCharge`, 
                       `userCharge`,  
                       `paymentCharge`,
                       `sellingFeeCharge`,
                       `customModifierCharge`,
                       `netPrice`, 
                       `friendRevenue`, 
                       `creationDate`, 
                       `lastUpdate`,
                       `note`,
                       `warehouseShelfPositionId`,
                       `remoteId` 
                      ) VALUES (
                          ' . $orderId . ',
                          ' . $orderLine->productId . ',
                          ' . $orderLine->productVariantId . ',
                          ' . $orderLine->productSizeId . ',
                          ' . $orderLine->shopId . ',
                          \'' . $newStatus->code . '\',
                          null,
                          null,
                          \'' . $orderLine->frozenProduct . '\',
                          ' . $revenueTotal . ',
                          ' . $revenueTotal . ',
                          ' . $vat . ',
                          ' . $orderLine->cost . ',
                          0,
                          0,
                          0,
                          0,
                          0,
                          0,
                          ' . $friendRevenue . ',
                          ' . $friendRevenue . ',
                          \'' . $orderLine->creationDate . '\',
                          \'' . $orderLine->lastUpdate . '\',
                          \'' . $orderLine->note . '\',
                          null,
                          null)');

                }
                $insertRemoteOrderLine->execute();
            } catch (\Throwable $e) {
                \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Cart to Shop ' . $findShopId->id,$e);
            }

            try {
                $stmtWalletMovements = $db_con->prepare('INSERT INTO ShopMovements (orderId,returnId,shopRefundRequestId,amount,date,valueDate,typeId,shopWalletId,note,isVisible) 
                VALUES (
                ' . $orderId . ',
                null,
                null,
                '.$revenueTotal.',
                \'' . date("Y-m-d H:i:s") . '\',
                \'' . date("Y-m-d H:i:s") . '\',
                1,
                1,
                \'Ordine Parallelo\',
                1
                )');
                $stmtWalletMovements->execute();

                /*select  shop seller to udpate Waller */

                $shopFindSeller=\Monkey::app()->repoFactory->create('Shop')->findOneBy(['id'=>$orderLine->shopId]);
                $db_hostSeller = $shopFindSeller->dbHost;
                $db_nameSeller = $shopFindSeller->dbName;
                $db_userSeller = $shopFindSeller->dbUsername;
                $db_passSeller = $shopFindSeller->dbPassword;

                try {

                    $db_conSeller = new PDO("mysql:host={$db_hostSeller};dbname={$db_nameSeller}",$db_userSeller,$db_passSeller);
                    $db_conSeller->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                    $res = " connessione ok <br>";
                } catch (PDOException $e) {
                    throw new BambooException('fail to connect');

                }
                $feeSeller=$shopFindSeller->paralellFee;
                $fee=($activePrice/100)*$feeSeller;
                $activePrice=$orderLine->activePrice;
                $amount=$fee-$activePrice;
                $stmtWalletMovementsSeller = $db_conSeller->prepare('INSERT INTO ShopMovements (orderId,returnId,shopRefundRequestId,amount,date,valueDate,typeId,shopWalletId,note,isVisible) 
                VALUES (
                ' . $orderLine->remoteOrderSellerId . ',
                null,
                null,
                '.$amount.',
                \'' . date("Y-m-d H:i:s") . '\',
                \'' . date("Y-m-d H:i:s") . '\',
                1,
                1,
                \'Ordine Parallelo Acquisto Prodotto \',
                1
                )');
                $stmtWalletMovementsSeller->execute();



            } catch (\Throwable $e) {
                \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Wallet to Shop ' . $findShopId->id,$e);
            }

        }
        $orderLine->remoteOrderSupplierId = $orderId;
        $orderLine->update();
    }

    /**
     * @param $orderLine
     * @param $oldStatus
     * @param null $time
     */
    private function updateFriendOrderSent($orderLine,$newStatus,$time = null)
    {
        /** @var COrderLineStatisticsRepo $olsR */
        $olsR = \Monkey::app()->repoFactory->create('OrderLineStatistics');
        $olsR->calculateSupplierShippingTime($orderLine);
        $orderLine->status = $newStatus->code;
        $orderLine->update();
        return $orderLine;
    }

    /**
     * @param $stringOrObj
     */
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
     *
     * @param $event
     * @param $description
     */
    public
    function log($orderLine,$event,$description)
    {
        $this->app->dbAdapter->insert('OrderHistory',["orderId" => $orderLine->orderId,"event" => $event,"description" => $description,"status" => $orderLine->status]);
    }

    /**
     * @param $event
     * @param $event
     */
    public
    function getFriendInvoice($orderLine)
    {
        return $this->getFriendDocument($orderLine,'fr_invoice');
    }

    /**
     * @param COrderLine $orderLine
     * @return mixed
     */
    public
    function getFriendCreditNote($orderLine)
    {
        return $this->getFriendDocument($orderLine,'fr_credit_note');
    }

    /**
     * @param $orderLine
     * @return null
     */
    public
    function getFriendTransDoc($orderLine)
    {
        return $this->getFriendDocument($orderLine,'fr_trans_doc');
    }

    /**
     * @param COrderLine $orderLine
     */
    public
    function getFriendDocument(COrderLine $orderLine,string $documentType)
    {
        foreach ($orderLine->invoiceLine as $v) {
            if (false !== strpos($v->document->invoiceType->code,$documentType)) return $v->document;
        }
        return null;
    }

    /**
     * @param COrderLine $orderLine
     * @param string $returnField
     * @return mixed
     */
    public
    function getOrderLineStatus(COrderLine $orderLine,$returnField = 'entity')
    {
        $os = $orderLine->orderLineStatus;
        if ('entity') return $os;
        return $os->{$returnField};
    }

    /**
     * cambia la friendRevenue
     * @param $price
     * @return bool
     */
    public
    function changeFriendRevenue(COrderLine $orderLine,$price)
    {
        if (is_string($price)) {
            $price = floatval($price);
        }
        if (is_float($price)) {
            try {
                $orderLine->friendRevenue = round($price,2);
                $orderLine->update();

            } catch (\Throwable $e) {
                $this->app->router->response()->raiseUnauthorized();
            }
        } else return false;
        return true;
    }

    /**
     * @param COrderLine $orderLine
     * @param $sku
     * @return bool
     */
    public
    function setNewSku(COrderLine $orderLine,$sku)
    {
        if (!$sku instanceof CProductSku) {
            $sku = \Monkey::app()->repoFactory->create('ProductSku')->findOne(
                ['productId' => $orderLine->productId,
                    'productVariantId' => $orderLine->productVariantId,
                    'productSizeId' => $orderLine->productSizeId,
                    'shopId' => $sku]);
        }
        if ($sku == null) return false;

        $this->log($orderLine,'Change Line','Switching to new Shop: ' . $sku->shopId . ' from ' . $orderLine->shopId);
        try {
            $orderLine->shopId = $sku->shopId;
            $orderLine->frozenProduct = $sku->froze();
            $orderLine->update();
        } catch (\Throwable $e) {
            $this->app->router->response()->raiseUnauthorized();
        }
        $pricer = $sku->shop->billingLogic;
        /** @var IBillingLogic $pricer */
        $pricer = new $pricer($this->app);

        try {
            $orderLine->cost = $sku->value;
            $orderLine->friendRevenue = $pricer->calculateFriendReturn($orderLine);
            $orderLine->update();
        } catch (\Throwable $e) {
            $this->app->router->response()->raiseUnauthorized();
        }
        return true;
    }
}