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
use bamboo\domain\entities\CUserAddress;
use bamboo\utils\time\STimeToolbox;
use DateTime;
use PDO;
use PDOException;
use bamboo\domain\repositories\CInvoiceRepo;

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
        if(ENV=="prod") {
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
                $orderLine = $this->updateFriendOrderSending($orderLine,$newStatusE,$time);
                break;
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
            $now = new DateTime();
            $sentTime = new DateTime($sentLog->time);
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
     * @throws \Throwable
     */

    private function updateFriendOrderSending($orderLine,$newStatus,$time = null)
    {

        try {
            $orderLine->status = $newStatus->code;
            if ($orderLine->shopId != $orderLine->remoteShopSellerId) {

                $findShopId = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $orderLine->shopId]);
                if ($findShopId->hasEcommerce == '1' && $findShopId->id != '44') {
                    /* find  orderId*/
                    $orderForRemote = \Monkey::app()->repoFactory->create('Order')->findOneBy(['id' => $orderLine->orderId]);
                    $cartForRemote = \Monkey::app()->repoFactory->create('Cart')->findOneBy(['id' => $orderForRemote->cartId]);
                    $db_host = $findShopId->dbHost;
                    $db_name = $findShopId->dbName;
                    $db_user = $findShopId->dbUsername;
                    $db_pass = $findShopId->dbPassword;
                    try {

                        $db_con = new PDO("mysql:host={$db_host};dbname={$db_name}",$db_user,$db_pass);
                        $db_con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                        $res = " connessione ok <br>";
                    } catch (PDOException $e) {
                        throw new BambooException('fail to connect');

                    }
                    switch ($orderLine->shopId) {
                        case '1':
                            $userRemoteId = 6699;
                            $billingAddressId = 2;
                            $shipmentAddressId = 2;

                            break;
                        case '51':
                            $userRemoteId = 255;
                            $billingAddressId = 105;
                            $shipmentAddressId = 105;
                            break;
                        case '58':
                            $userRemoteId = 301;
                            $billingAddressId = 111;
                            $shipmentAddressId = 111;
                            break;
                    }
                    $billingAddressFind = \Monkey::app()->repoFactory->create('UserAddress')->findOneBy(['id' => '5843']);
                    $billingAddress = $billingAddressFind->froze();

                    if ($cartForRemote->hasInvoice == null) {
                        $hasInvoice = 'null';
                    } else {
                        $hasInvoice = $cartForRemote->hasInvoice;
                    }
                    $findIfExistOrder = $db_con->prepare('SELECT count(*) as countOrder, cartId as cartId, id as remoteOrderId from `Order` where remoteIwesOrderId=' . $orderLine->orderId);
                    $findIfExistOrder->execute();
                    $rowFindIfExistOrder = $findIfExistOrder->fetch(PDO::FETCH_ASSOC);
                    if ($rowFindIfExistOrder['countOrder'] == 0) {

                        try {
                            $insertRemoteCart = $db_con->prepare("INSERT INTO Cart (orderPaymentMethodId,
                                                                          couponId,
                                                                          userId,
                                                                          cartTypeId,  
                                                                          billingAddressId,
                                                                          shipmentAddressId,
                                                                          lastUpdate,
                                                                          creationDate,
                                                                          hasInvoice,
                                                                          isParallel,
                                                                          isImport)
                                                                           VALUES (
                                                                          " . $orderForRemote->orderPaymentMethodId . ",
                                                                          null,
                                                                          " . $userRemoteId . ",
                                                                          " . $cartForRemote->cartTypeId . ",
                                                                          " . $billingAddressId . ",
                                                                          " . $shipmentAddressId . ",
                                                                          '" . $cartForRemote->lastUpdate . "',
                                                                          '" . $cartForRemote->creationDate . "',
                                                                          " . $hasInvoice . "
                                                                          ,1
                                                                          ,1
                                                                          )");
                            $insertRemoteCart->execute();
                        } catch (\Throwable $e) {
                            \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Cart to Shop ' . $findShopId->id,$e);
                        }

                        $findLastRemoteCart = $db_con->prepare("select MAX(id) as cartId from Cart ");
                        $findLastRemoteCart->execute();
                        $rowFindLastRemoteCart = $findLastRemoteCart->fetch(PDO::FETCH_ASSOC);
                        $cartId = $rowFindLastRemoteCart['cartId'];
                        /* caricare i prodotti sul carrello
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
                        $friendRevenue = $orderLine->friendRevenue;
                        $vat = ($friendRevenue / 100) * 22;
                        $revenueTotal = number_format($friendRevenue + $vat,2);
                        if ($orderForRemote->remoteShopSellerId == '44') {
                            $isOrdermarketplace = '1';
                        } else {
                            $isOrderMarketplace = '0';
                        }

                        try {
                            if (is_null($orderForRemote->isShippingToIwes)) {
                                $isShippingto = 'null';
                            } else {
                                $isShippingto = '1';
                            }
                            $insertRemoteOrder = $db_con->prepare(sprintf("INSERT INTO `Order` (
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
           marketplaceOrderId,
           isShippingToIwes ,
           isImport,
           orderIdFather          
           ) VALUES (
            6,
            null,
            null,
            %d,
            %s,
            '%s',
            '%s',
            '%s',
            %d,
            %d,
            0,
            0,
            0,
            0,
            %d,
            %d,
            %d,
            0,
            0,
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            %d,
             '%s',
            '%s',
            '%s',
            1,
            %s,
            1,
             '%s',
             '%s',
             null,           
             %s,
             %s,
             1,        
             null )",$userRemoteId,$cartId,$orderForRemote->status,addslashes($billingAddress),addslashes($orderForRemote->frozenShippingAddress),$billingAddressId,$shipmentAddressId,$revenueTotal,$revenueTotal,$vat,$orderForRemote->orderDate,$orderForRemote->note,$orderForRemote->shipmentNote,$orderForRemote->transactionNumber,$orderForRemote->transactionMac,$revenueTotal,date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$orderLine->orderId,$orderForRemote->remoteShopSellerId,$isOrderMarketplace,$orderLine->orderId,$isShippingto));
                            $logsql = sprintf("INSERT INTO `Order` (
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
           marketplaceOrderId ,
           isShippingToIwes,
           isImport ,
           orderIdFather
           ) VALUES (
            6,
            null,
            null,
            %d,
            %s,
            '%s',
            '%s',
            '%s',
            %d,
            %d,
            0,
            0,
            0,
            0,
            %d,
            %d,
            %d,
            0,
            0,
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            %d,
             '%s',
            '%s',
            '%s',
            1,
            %s,
            1,
             '%s',
             '%s',
             null,           
             %s,
             %s,
             1,
            null)",$userRemoteId,$cartId,$orderForRemote->status,$billingAddress,$orderForRemote->frozenShippingAddress,$billingAddressId,$shipmentAddressId,$revenueTotal,$revenueTotal,$vat,$orderForRemote->orderDate,$orderForRemote->note,$orderForRemote->shipmentNote,$orderForRemote->transactionNumber,$orderForRemote->transactionMac,$revenueTotal,date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$orderLine->orderId,$orderForRemote->remoteShopSellerId,$isOrderMarketplace,$orderLine->orderId,$isShippingto);

                            $insertRemoteOrder->execute();

                        } catch (\Throwable $e) {
                            \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Cart to Shop ',$logsql,'');
                        }
                        $findLastRemoteOrder = $db_con->prepare("select MAX(id) as orderId from `Order` ");
                        $findLastRemoteOrder->execute();
                        $rowFindLastRemoteOrder = $findLastRemoteOrder->fetch(PDO::FETCH_ASSOC);
                        $orderId = $rowFindLastRemoteOrder['orderId'];
                        try {
                            if ($findShopId->id != '1') {
                                $insertRemoteOrderLine = $db_con->prepare(sprintf("INSERT INTO OrderLine (
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
                       `remoteId`,
                       `isParallel`,
                       `isImport`
                      ) VALUES (
                          %s,
                          %s,
                          %s,
                          %s,
                          %s,
                          '%s',
                          null,
                          null,
                          null,
                          '%s',
                          %d,
                          %d,
                          %d,
                          %s,
                          0,
                          0,
                          0,
                          0,
                          0,
                          0,
                          %s,
                          %s,
                          '%s',
                          '%s',
                          '%s',
                          null,
                          1,
                          1)",$orderId,$orderLine->productId,$orderLine->productVariantId,$orderLine->productSizeId,$orderLine->shopId,$newStatus->code,$orderLine->frozenProduct,$revenueTotal,$revenueTotal,$vat,$orderLine->cost,$friendRevenue,$friendRevenue,$orderLine->creationDate,$orderLine->lastUpdate,$orderLine->note));

                            } else {

                                $insertRemoteOrderLine = $db_con->prepare(sprintf("INSERT INTO OrderLine (
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
                       `remoteId`,
                       `isParallel`,
                       `isImport`
                      ) VALUES (
                          %s,
                          %s,
                          %s,
                          %s,
                          %s,
                          '%s',
                          null,
                          null,
                          '%s',
                          %d,
                          %d,
                          %d,
                          %s,
                          0,
                          0,
                          0,
                          0,
                          0,
                          0,
                          %s,
                          %s,
                          '%s',
                          '%s',
                          '%s',
                          null,
                          null,
                          1,
                          1     )",$orderId,$orderLine->productId,$orderLine->productVariantId,$orderLine->productSizeId,$orderLine->shopId,$newStatus->code,$orderLine->frozenProduct,$revenueTotal,$revenueTotal,$vat,$orderLine->cost,$friendRevenue,$friendRevenue,$orderLine->creationDate,$orderLine->lastUpdate,$orderLine->note));

                            }
                            $insertRemoteOrderLine->execute();
                        } catch (\Throwable $e) {
                            \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Cart to Shop ','','');
                        }
                    } else {
                        $orderId = $rowFindIfExistOrder['remoteOrderId'];
                        $productSku = \Monkey::app()->repoFactory->
                        create('ProductSku')->
                        findOneBy(
                            ['productId' => $orderLine->productId,
                                'productVariantId' => $orderLine->productVariantId,
                                'productSizeId' => $orderLine->productSizeId]);
                        $friendRevenue = $orderLine->friendRevenue;
                        $vat = ($friendRevenue / 100) * 22;
                        $revenueTotal = number_format($friendRevenue + $vat,2);
                        if ($orderForRemote->remoteShopSellerId == '44') {
                            $isOrdermarketplace = '1';
                        } else {
                            $isOrderMarketplace = '0';
                        }
                        try {
                            if ($findShopId->id != '1') {
                                $insertRemoteOrderLine = $db_con->prepare(sprintf("INSERT INTO OrderLine (
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
                       `remoteId`,
                       `isParallel`,
                       `isImport`
                      ) VALUES (
                          %s,
                          %s,
                          %s,
                          %s,
                          %s,
                          '%s',
                          null,
                          null,
                          null,
                          '%s',
                          %d,
                          %d,
                          %d,
                          %s,
                          0,
                          0,
                          0,
                          0,
                          0,
                          0,
                          %s,
                          %s,
                          '%s',
                          '%s',
                          '%s',
                          null,
                          1,
                          1)",$orderId,$orderLine->productId,$orderLine->productVariantId,$orderLine->productSizeId,$orderLine->shopId,$newStatus->code,$orderLine->frozenProduct,$revenueTotal,$revenueTotal,$vat,$orderLine->cost,$friendRevenue,$friendRevenue,$orderLine->creationDate,$orderLine->lastUpdate,$orderLine->note));

                            } else {

                                $insertRemoteOrderLine = $db_con->prepare(sprintf("INSERT INTO OrderLine (
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
                       `remoteId`,
                       `isParallel`,
                       `isImport`
                      ) VALUES (
                          %s,
                          %s,
                          %s,
                          %s,
                          %s,
                          '%s',
                          null,
                          null,
                          '%s',
                          %d,
                          %d,
                          %d,
                          %s,
                          0,
                          0,
                          0,
                          0,
                          0,
                          0,
                          %s,
                          %s,
                          '%s',
                          '%s',
                          '%s',
                          null,
                          null,
                          1,
                          1     )",$orderId,$orderLine->productId,$orderLine->productVariantId,$orderLine->productSizeId,$orderLine->shopId,$newStatus->code,$orderLine->frozenProduct,$revenueTotal,$revenueTotal,$vat,$orderLine->cost,$friendRevenue,$friendRevenue,$orderLine->creationDate,$orderLine->lastUpdate,$orderLine->note));

                            }
                            $insertRemoteOrderLine->execute();
                        } catch (\Throwable $e) {
                            \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Cart to Shop ','','');
                        }

                    }
                    // vecchio inserimento seller
                    try {

                        $stmtWalletMovements = $db_con->prepare('INSERT INTO ShopMovements (orderId,returnId,shopRefundRequestId,amount,date,valueDate,typeId,shopWalletId,note,isVisible)
                VALUES (
                ' . $orderId . ',
                null,
                null,
                ' . $revenueTotal . ',
                \'' . date('Y-m-d H:i:s') . '\',
                \'' . date('Y-m-d H:i:s') . '\',
                1,
                1,
                \'Ordine Parallelo\',
                1
                )');
                        $stmtWalletMovements->execute();

                    } catch (\Throwable $e) {
                        \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Wallet Supplier Shop ','','');
                    }
                    /*select  shop seller to udpate Wallet */
                    $remoteOrderSupplierId = $orderId;

                    $shopFindSeller = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $orderLine->shopId]);
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
                    $feeSeller = $shopFindSeller->paralellFee;
                    $activePrice = $orderLine->activePrice;
                    $fee = ($activePrice / 100) * $feeSeller;
                    $amount = $activePrice - $fee;
                    $amountForInvoice = -abs($amount);/*
                    $stmtWalletMovementsSeller = $db_conSeller->prepare('INSERT INTO ShopMovements (orderId,returnId,shopRefundRequestId,amount,date,valueDate,typeId,shopWalletId,note,isVisible)
                VALUES (
                ' . $orderLine->remoteOrderSellerId . ',
                null,
                null,
                ' . $amount . ',
                \'' . date("Y-m-d H:i:s") . '\',
                \'' . date("Y-m-d H:i:s") . '\',
                1,
                1,
                \'Ordine Parallelo Acquisto Prodotto \',
                1
                )');
                    $stmtWalletMovementsSeller->execute();


                } catch (\Throwable $e) {
                    \Monkey::app()->applicationLog('COrderLineRepo','Error','Insert remote Wallet to Shop ' , $findShopId->id,$e);
                }
 */
                    $remoteShopSellerId = $orderLine->remoteShopSellerId;
                    $orderLine->remoteOrderSupplierId = $orderId;
                    /** @var  CInvoiceRepo $invoiceRepo */
                    /** @var  $udpateExternalShop */
                    /** @throws BambooException */
                    $this->createNewInvoiceToOrderParallel($orderLine,$orderLine->orderId,$remoteOrderSupplierId,$remoteShopSellerId,$amountForInvoice);
                }

            }
        }
        catch
        (\Throwable $e) {
            \Monkey::app()->applicationLog('COrderLineRepo','Error','cannot work remote insert ',$e,'');
        }

        $orderLine->update();
        return $orderLine;
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
     * cambia la friendRevenue
     * @param $price
     * @return bool
     */
    public
    function changeCost(COrderLine $orderLine,$price)
    {
        if (is_string($price)) {
            $price = floatval($price);
        }
        if (is_float($price)) {
            try {
                $orderLine->cost = round($price,2);
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

    public function createNewInvoiceToOrderParallel(COrderLine $orderLine,int $orderId,int $remoteOrderSupplierId,int $remoteShopSellerId,float $amountForInvoice): bool
    {

        $orderRepo = \Monkey::app()->repoFactory->create('Order');
        $shopRepo = \Monkey::app()->repoFactory->create('Shop');

        $order = $orderRepo->findOneBy(['id' => $orderLine->orderId]);


        // prendo l'intestazione
        $shopInvoices = $shopRepo->findOneBy(['id' => 44]);
        $logo = $shopInvoices->logo;
        $intestation = $shopInvoices->intestation;
        $intestation2 = $shopInvoices->intestation2;
        $address = $shopInvoices->address;
        $address2 = $shopInvoices->address2;
        $iva = $shopInvoices->iva;
        $tel = $shopInvoices->tel;
        $email = $shopInvoices->email;
        /***sezionali******/
        $invoiceParalUe = $shopInvoices->invoiceParalUe;
        $invoiceParalExtraUe = $shopInvoices->invoiceParalExtraUe;
        $siteInvoiceChar = $shopInvoices->siteInvoiceChar;

        /* Dati destinatario Fattura shop Seller */
        $customerDataSeller = $shopRepo->findOneBy(['id' => $remoteShopSellerId]);
        switch ($customerDataSeller->id) {
            case 1:
                $filterUserAddress = 2769;
                break;
            case 51:
                $filterUserAddress = 17668;
                break;
            case 58:
                $filterUserAddress = 25251;
                break;
        }

        //definzione dello shop seller al fine di reperire l'id utente che lo gestisce
        $userHasAddress = \Monkey::app()->repoFactory->create('AddressBook')->findOneBy(['id' => $customerDataSeller->billingAddressBookId]);
        $userAddress = $userHasAddress;
        $userShipping = $userHasAddress;
        $extraUe = $userAddress->countryId;
        $countryRepo = \Monkey::app()->repoFactory->create('Country');
        $findIsExtraUe = $countryRepo->findOneBy(['id' => $extraUe]);
        $isExtraUe = $findIsExtraUe->extraue;


        if ($extraUe != '110') {
            $changelanguage = "1";

        } else {
            $changelanguage = "0";
        }

//inserimento fattura e assegnazione sezionale
        $hasInvoice = 1;
        $invoiceRepo = \Monkey::app()->repoFactory->create('Invoice');
        $invoiceNew = $invoiceRepo->getEmptyEntity();
        $siteChar = $siteInvoiceChar;
        if ($order->invoice->isEmpty()) {
            try {
                $invoiceNew->orderId = $orderId;
                $today = new DateTime();
                $invoiceNew->invoiceYear = $today->format('Y-m-d H:i:s');
                $year = (new DateTime())->format('Y');
                $em = $this->app->entityManagerFactory->create('Invoice');
                // se è fattura
                $productRepo = \Monkey::app()->repoFactory->create('ProductNameTranslation');
                //se è extracee
                if ($isExtraUe == '1') {
                    $invoiceType = $invoiceParalExtraUe;
                    $invoiceTypeVat = 'newX';
                    $documentType = '20';
                    //se è non è inglese
                    if ($changelanguage != "1") {
                        // è inglese
                        $invoiceTypeText = "Fattura N. :";
                        $invoiceHeaderText = "FATTURA";
                        $invoiceTotalDocumentText = "Totale Fattura";
                        $documentType = '20';
                    } else {
                        //è italiano
                        $invoiceTypeText = "Invoice N. :";
                        $invoiceHeaderText = "INVOICE";
                        $invoiceTotalDocumentText = "Invoice Total";


                    }
                } else {
                    // è fattura intracomunitario
                    // se è pickyshop
                    // è pickyshop
                    // è fattura Ecommerce Parallelo
                    $invoiceType = $invoiceParalUe;
                    $documentType = '21';
                    $invoiceTypeVat = 'newP';
                    // se non è inglese
                    if ($changelanguage != "1") {
                        // è italiano
                        $invoiceTypeText = "Fattura N. :";
                        $invoiceHeaderText = "FATTURA";
                        $invoiceTotalDocumentText = "Totale Fattura";
                    } else {
                        // non è italiano
                        $invoiceTypeText = "Invoice N. :";
                        $invoiceHeaderText = "INVOICE";
                        $invoiceTotalDocumentText = "Invoice Total";
                    }
                }
                // fine distinzione  tra intracee e extracee


                $number = $em->query("SELECT ifnull(MAX(invoiceNumber),0)+1 AS new
                                      FROM Invoice
                                      WHERE
                                      Invoice.invoiceYear = ? AND
                                      Invoice.invoiceType='" . $invoiceType . "' AND
                                      Invoice.invoiceShopId='" . $shopInvoices->id . "' AND
                                      Invoice.invoiceSiteChar= ?",[$year,$siteChar])->fetchAll()[0]['new'];


                $invoiceNew->invoiceShopId = $shopInvoices->id;
                $invoiceNew->invoiceNumber = $number;
                $invoiceNew->invoiceSiteChar = $siteChar;
                $invoiceNew->invoiceType = $invoiceType;
                $invoiceNew->invoiceDate = $today->format('Y-m-d H:i:s');
                $todayInvoice = $today->format('d/m/Y');
                $invoiceDate = new DateTime($invoiceNew->invoiceDate);

                $invoiceText = '';
                $invoiceText .= '
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/html">
<head>';
                $invoiceText .= '<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
<link rel="icon" type="image/x-icon" sizes="32x32" href="/assets/img/favicon32.png"/>
<link rel="icon" type="image/x-icon" sizes="256x256" href="/assets/img/favicon256.png"/>
<link rel="icon" type="image/x-icon" sizes="16x16" href="/assets/img/favicon16.png"/>
<link rel="apple-touch-icon" type="image/x-icon" sizes="256x256" href="/assets/img/favicon256.png"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta content="" name="description"/>
<meta content="" name="author"/>
<script>
    paceOptions = {
        ajax: {ignoreURLs: [\'/blueseal/xhr/TemplateFetchController\', \'/blueseal/xhr/CheckPermission\']}
    }
</script>
    <link type="text/css" href="https://www.iwes.pro/assets/css/pace.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://code.jquery.com/ui/1.11.4/themes/flick/jquery-ui.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://s3-eu-west-1.amazonaws.com/bamboo-css/jquery.scrollbar.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://s3-eu-west-1.amazonaws.com/bamboo-css/bootstrap-colorpicker.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://github.com/mar10/fancytree/blob/master/dist/skin-common.less" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.24.0/skin-bootstrap/ui.fancytree.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.4/css/selectize.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.0.1/min/basic.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.0.1/min/dropzone.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/ui.dynatree.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.6.16/summernote.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://fonts.googleapis.com/css?family=Raleway" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,700,300" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://raw.githubusercontent.com/kleinejan/titatoggle/master/dist/titatoggle-dist-min.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/pages-icons.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/pages.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/style.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/fullcalendar.css" rel="stylesheet" media="screen,print"/>
<script  type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js"></script>
<script  type="application/javascript" src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script  type="application/javascript" src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
<script  type="application/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/pages.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/blueseal.prototype.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/blueseal.ui.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.3/jquery.easing.min.js"></script>
<script defer type="application/javascript" src="https://cdn.jsdelivr.net/jquery.bez/1.0.11/jquery.bez.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/unveil/1.3.0/jquery.unveil.min.js"></script>
<script defer type="application/javascript" src="https://s3-eu-west-1.amazonaws.com/bamboo-js/jquery.scrollbar.min.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/Sortable.min.js"></script>
<script defer type="application/javascript" src="https://s3-eu-west-1.amazonaws.com/bamboo-js/bootstrap-colorpicker.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.24.0/jquery.fancytree-all.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.4/js/standalone/selectize.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.0.1/min/dropzone.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.0.1/min/dropzone-amd-module.min.js"></script>
<script defer type="application/javascript" src="https://cdn.jsdelivr.net/jquery.dynatree/1.2.4/jquery.dynatree.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.6.16/summernote.min.js"></script>
<script defer type="application/javascript" src="https://s3-eu-west-1.amazonaws.com/bamboo-js/summernote-it-IT.js"></script>
<script defer type="application/javascript" src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.14.0/jquery.validate.min.js"></script>
<script defer type="application/javascript" src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.14.0/additional-methods.min.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/blueseal.kickstart.js"></script>
<script  type="application/javascript" src="https://www.iwes.pro/assets/js/monkeyUtil.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/invoice_print.js"></script>
<script defer async type="application/javascript" src="https://www.iwes.pro/assets/js/blueseal.common.js"></script>
    <title>BlueSeal - Stampa fattura</title>
    <style type="text/css">';


                $invoiceText .= '
        @page {
            size: A4;
            margin: 5mm 0mm 0mm 0mm;
        }

        @media print {
            body {
                zoom: 100%;
                width: 800px;
                height: 1100px;
                overflow: hidden;
            }

            .container {
                width: 100%;
            }

            .newpage {
                page-break-before: always;
                page-break-after: always;
                page-break-inside: avoid;
            }

            @page {
                size: A4;
                margin: 5mm 0mm 0mm 0mm;
            }

            .cover {
                display: none;
            }

            .page-container {
                display: block;
            }

            /*remove chrome links*/
            a[href]:after {
                content: none !important;
            }

            .col-md-1,
            .col-md-2,
            .col-md-3,
            .col-md-4,
            .col-md-5,
            .col-md-6,
            .col-md-7,
            .col-md-8,
            .col-md-9,
            .col-md-10,
            .col-md-11,
            .col-md-12 {
                float: left;
            }

            .col-md-12 {
                width: 100%;
            }

            .col-md-11 {
                width: 91.66666666666666%;
            }

            .col-md-10 {
                width: 83.33333333333334%;
            }

            .col-md-9 {
                width: 75%;
            }

            .col-md-8 {
                width: 66.66666666666666%;
            }

            .col-md-7 {
                width: 58.333333333333336%;
            }

            .col-md-6 {
                width: 50%;
            }

            .col-md-5 {
                width: 41.66666666666667%;
            }

            .col-md-4 {
                width: 33.33333333333333%;
            }

            .col-md-3 {
                width: 25%;
            }

            .col-md-2 {
                width: 16.666666666666664%;
            }

            .col-md-1 {
                width: 8.333333333333332%;
            }

            .col-md-pull-12 {
                right: 100%;
            }

            .col-md-pull-11 {
                right: 91.66666666666666%;
            }

            .col-md-pull-10 {
                right: 83.33333333333334%;
            }

            .col-md-pull-9 {
                right: 75%;
            }

            .col-md-pull-8 {
                right: 66.66666666666666%;
            }

            .col-md-pull-7 {
                right: 58.333333333333336%;
            }

            .col-md-pull-6 {
                right: 50%;
            }

            .col-md-pull-5 {
                right: 41.66666666666667%;
            }

            .col-md-pull-4 {
                right: 33.33333333333333%;
            }

            .col-md-pull-3 {
                right: 25%;
            }

            .col-md-pull-2 {
                right: 16.666666666666664%;
            }

            .col-md-pull-1 {
                right: 8.333333333333332%;
            }

            .col-md-pull-0 {
                right: 0;
            }

            .col-md-push-12 {
                left: 100%;
            }

            .col-md-push-11 {
                left: 91.66666666666666%;
            }

            .col-md-push-10 {
                left: 83.33333333333334%;
            }

            .col-md-push-9 {
                left: 75%;
            }

            .col-md-push-8 {
                left: 66.66666666666666%;
            }

            .col-md-push-7 {
                left: 58.333333333333336%;
            }

            .col-md-push-6 {
                left: 50%;
            }

            .col-md-push-5 {
                left: 41.66666666666667%;
            }

            .col-md-push-4 {
                left: 33.33333333333333%;
            }

            .col-md-push-3 {
                left: 25%;
            }

            .col-md-push-2 {
                left: 16.666666666666664%;
            }

            .col-md-push-1 {
                left: 8.333333333333332%;
            }

            .col-md-push-0 {
                left: 0;
            }

            .col-md-offset-12 {
                margin-left: 100%;
            }

            .col-md-offset-11 {
                margin-left: 91.66666666666666%;
            }

            .col-md-offset-10 {
                margin-left: 83.33333333333334%;
            }

            .col-md-offset-9 {
                margin-left: 75%;
            }

            .col-md-offset-8 {
                margin-left: 66.66666666666666%;
            }

            .col-md-offset-7 {
                margin-left: 58.333333333333336%;
            }

            .col-md-offset-6 {
                margin-left: 50%;
            }

            .col-md-offset-5 {
                margin-left: 41.66666666666667%;
            }

            .col-md-offset-4 {
                margin-left: 33.33333333333333%;
            }

            .col-md-offset-3 {
                margin-left: 25%;
            }

            .col-md-offset-2 {
                margin-left: 16.666666666666664%;
            }

            .col-md-offset-1 {
                margin-left: 8.333333333333332%;
            }

            .col-md-offset-0 {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="fixed-header">

<!--start-->
<div class="container container-fixed-lg">

    <div class="panel panel-default">
        <div class="panel-body">
            <div class="invoice padding-50 sm-padding-10">
                <div>
                    <div class="pull-left">
                        <!--logo negozio-->
                        <img width="235" height="47" alt="" class="invoice-logo"
                             data-src-retina=' . $logo . ' data-src=' . $logo . ' src=' . $logo . '>
                        <!--indirizzo negozio-->
                        <br><br>
                        <address class="m-t-10"><b>' . $intestation . '
                                <br>' . $intestation2 . '</b>
                            <br>' . $address . '
                            <br>' . $address2 . '
                            <br>' . $iva . '
                            <br>' . $tel . '
                            <br>' . $email . '
                        </address>
                        <br>
                        <div>
                            <div class="pull-left font-montserrat all-caps small">
                                <strong>' . $invoiceTypeText . '</strong>  ' . $invoiceNew->invoiceNumber . "/" . $invoiceType . '<strong> del </strong>' . $invoiceDate->format('d-m-Y') . '
                            </div>

                        </div>
                        <br>
                        <div>
                            <div class="pull-left font-montserrat small"><strong>';

                if ($changelanguage != 1) {
                    $referOrder = 'Rif. ordine N. ';
                } else {
                    $referOrder = 'Order Reference N:';
                }
                $invoiceText .= $referOrder;
                $invoiceText .= '</strong>';
                $date = new DateTime($order->orderDate);
                if ($changelanguage != 1) {
                    $refertOrderIdandDate = '  ' . $orderLine->orderId . '-' . $orderLine->remoteOrderSellerId . ' del ' . $date->format('d-m-Y');
                } else {
                    $refertOrderIdandDate = '  ' . $orderLine->orderId . '-' . $orderLine->remoteOrderSellerId . ' date ' . $date->format('Y-d-m');
                };
                $invoiceText .= $refertOrderIdandDate . '</div>
                        </div>
                        <div><br>
                            <div class="pull-left font-montserrat small"><strong>';
                if ($changelanguage != 1) {
                    $invoiceText .= 'Metodo di pagamento';
                } else {
                    $invoiceText .= 'Payment Method';
                }
                $invoiceText .= '</strong>';
                $invoiceText .= ' ' . $order->orderPaymentMethod->name . '</div>

                        </div>
                    </div>
                    <div class="pull-right sm-m-t-0">
                        <h2 class="font-montserrat all-caps hint-text"><?php echo $invoiceHeaderText; ?></h2>

                        <div class="col-md-12 col-sm-height sm-padding-20">
                            <p class="small no-margin">';
                if ($changelanguage != 1) {
                    $invoiceText .= 'Intestata a';
                } else {
                    $invoiceText .= 'Invoice Address';
                }
                $invoiceText .= '</p>';
                $invoiceText .= '<h5 class="semi-bold m-t-0 no-margin">' . $userAddress->subject . '</h5>';
                $invoiceText .= '<address>';
                $invoiceText .= '<strong>';
                $invoiceText .= $userAddress->address;
                $invoiceText .= '<br>' . $userAddress->postcode . ' ' . $userAddress->city . ' (' . $userAddress->province . ')';
                $invoiceText .= '<br>' . $userAddress->country->name;
                if ($changelanguage != 1) {
                    $transfiscalcode = 'C.FISC. o P.IVA: ';
                } else {
                    $transfiscalcode = 'VAT';
                }
                $invoiceText .= '<br>';

                $invoiceText .= $transfiscalcode . $userAddress->vatNumber;


                $invoiceText .= '</strong>';
                $invoiceText .= '</address>';
                $invoiceText .= '<div class="clearfix"></div><br><p class="small no-margin">';
                if ($changelanguage != 1) {
                    $invoiceText .= 'Indirizzo di Spedizione';
                } else {
                    $invoiceText .= 'Shipping Address';
                }

                $invoiceText .= '</p><address>';
                $invoiceText .= '<strong>' . $userShipping->subject;
                $invoiceText .= '<br>' . $userShipping->address;
                $invoiceText .= '<br>' . $userShipping->postcode . ' ' . $userShipping->city . ' (' . $userShipping->province . ')';
                $invoiceText .= '<br>' . $userShipping->country->name . '</strong>';
                $invoiceText .= '</address>';
                $invoiceText .= '</div>';
                $invoiceText .= '</div>';
                $invoiceText .= '</div>';
                $invoiceText .= '<table class="table invoice-table m-t-0">';
                $invoiceText .= '<thead>
                    <!--tabella prodotti-->
                    <tr>';
                $invoiceText .= '<th class="small">';
                if ($changelanguage != 1) {
                    $invoiceText .= 'Descrizione Prodotto';
                } else {
                    $invoiceText .= 'Description';
                }
                $invoiceText .= '</th>';
                $invoiceText .= '<th class="text-center small">';
                if ($changelanguage != 1) {
                    $invoiceText .= 'Taglia';

                } else {
                    $invoiceText .= 'Size';
                }
                $invoiceText .= '</th>';
                $invoiceText .= '<th></th>';
                $invoiceText .= '<th class="text-center small">';
                if ($changelanguage != 1) {
                    $invoiceText .= 'Importo';
                } else {
                    $invoiceText .= 'Amount';
                }
                $invoiceText .= '</th>';

                $invoiceText .= '</tr></thead><tbody>';
                $tot = 0;
                foreach ($order->orderLine as $orderLine) {
                    $invoiceText .= '<tr>';
                    $invoiceText .= '<td class="">';

                    $productSku = \bamboo\domain\entities\CProductSku::defrost($orderLine->frozenProduct);

                    $productNameTranslation = $productRepo->findOneBy(['productId' => $productSku->productId,'productVariantId' => $productSku->productVariantId,'langId' => '1']);
                    $invoiceText .= (($productNameTranslation) ? $productNameTranslation->name : '') . ($orderLine->warehouseShelfPosition ? ' / ' . $orderLine->warehouseShelfPosition->printPosition() : '') . '<br />' . $productSku->product->productBrand->name . ' - ' . $productSku->productId . '-' . $productSku->productVariantId;
                    $invoiceText .= '</td>';
                    $productSize = \Monkey::app()->repoFactory->create('ProductSize')->findOneBy(['id' => $productSku->productSizeId]);
                    $invoiceText .= '<td class="text-center">' . $productSize->name;
                    $invoiceText .= '<td></td>';
                    $invoiceText .= '</td>';
                    $invoiceText .= '<td class="text-center">';
                    $tot += $orderLine->activePrice;
                    $invoiceText .= money_format('%.2n',$orderLine->activePrice) . ' &euro;' . '</td></tr>';

                }
                $invoiceText .= '</tbody><br><tr class="text-left font-montserrat small">
                        <td style="border: 0px"></td>
                        <td style="border: 0px"></td>
                        <td style="border: 0px">
                            <strong>';
                if ($changelanguage != 1) {
                    $invoiceText .= 'Totale della Merce';
                } else {
                    $invoiceText .= 'Total Amount ';
                }
                $invoiceText .= '</strong></td>
                        <td style="border: 0px"
                            class="text-center">' . money_format('%.2n',$tot) . ' &euro;' . '</td>
                    </tr>';
                $discount = $order->couponDiscount + $order->userDiscount;
                ($changelanguage != 1) ? $transdiscount = 'Sconto' : $transdiscount = 'Discount';
                ($changelanguage != 1) ? $transmethodpayment = 'Modifica di pagamento' : $transmethodpayment = 'Transaction Discount';
                ($changelanguage != 1) ? $transdeliveryprice = 'Spese di Spedizione' : $transdeliveryprice = 'Shipping Cost';
                $invoiceText .= ((!is_null($discount)) && ($discount != 0)) ? '<tr class="text-left font-montserrat small">
                            <td style="border: 0px"></td>
                            <td style="border: 0px"></td>
                            <td style="border: 0px">' . $transdiscount . '<strong></strong></td>
                            <td style="border: 0px" class="text-center">' . money_format('%.2n',$discount) . ' &euro; </td></tr>' : null;
                $invoiceText .= ((!is_null($order->paymentModifier)) && ($order->paymentModifier != 0)) ? '<tr class="text-left font-montserrat small">
                            <td style="border: 0px"></td>
                            <td style="border: 0px"></td><td style="border: 0px"><strong>' . $transmethodpayment . '</strong></td>
                            <td style="border: 0px" class="text-center">' . money_format('%.2n',$order->paymentModifier) . ' &euro; </td></tr>' : null;
                $invoiceText .= '<tr class="text-left font-montserrat small">
                        <td style="border: 0px"></td>
                        <td style="border: 0px"></td>
                        <td class="separate"><strong>' . $transdeliveryprice . '</strong></td>
                        <td class="separate text-center">' . money_format('%.2n',$order->shippingPrice) . ' &euro;</td>
                    </tr>
                    <tr style="border: 0px" class="text-left font-montserrat small hint-text">
                        <td class="text-left" width="30%">';

                if ($invoiceType == 'PP') {
                    if ($changelanguage != 1) {
                        $invoiceText .= 'Imponibile<br>';
                    } else {
                        $invoiceText .= 'Net Amount<br>';
                    }
                    $imp = ($order->netTotal * 100) / 122;
                    $invoiceText .= money_format('%.2n',$imp) . ' &euro;';
                } elseif ($invoiceType == "PX") {

                    $imp = ($order->netTotal * 100) / 122;

                    $invoiceText .= '<br>';
                } else {
                    $imp = ($order->netTotal * 100) / 122;
                    $invoiceText .= '<br>';
                }

                $invoiceText .= '</td>
                        <td class="text-left" width="25%">';

                if ($invoiceTypeVat == 'NewP') {
                    if ($changelanguage != 1) {
                        $invoiceText .= 'IVA 22%<br>';
                    } else {
                        $invoiceText .= 'VAT 22%<br>';
                    }
                    $iva = $order->vat;
                    $invoiceText .= money_format('%.2n',$iva) . ' &euro;';
                } elseif ($invoiceTypeVat == "NewX") {
                    $invoiceText .= 'non imponibile ex art 8/A  D.P.R. n. 633/72';
                    $iva = "0,00";
                    $invoiceText .= '<br>';
                } else {
                    $iva = $order->vat;
                    $invoiceText .= '<br>';
                }


                $invoiceText .= '<br></td>';
                $invoiceText .= '<td class="semi-bold"><h4>' . $invoiceTotalDocumentText . '</h4></td>';
                $invoiceText .= '<td class="semi-bold text-center">
                            <h2>' . money_format('%.2n',$order->netTotal) . ' &euro; </h2></td>
                    </tr>

                </table>
            </div>
            <br>
            <br>
            <br>
            <br>
            <br>
            <div>
                <center><img alt="" class="invoice-thank" data-src-retina="/assets/img/invoicethankyou.jpg"
                             data-src="/assets/img/invoicethankyou.jpg" src="/assets/img/invoicethankyou.jpg">
                </center>
            </div>
            <br>
            <br>
        </div>
    </div>
</div><!--end-->';

                $invoiceText .= '<script type="application/javascript">
    $(document).ready(function () {

        Pace.on(\'done\', function () {

            setTimeout(function () {
                window.print();

                setTimeout(function () {
                    window.close();
                }, 1);

            }, 200);

        });
    });
</script>
</body>
</html>';


                $invoiceNew->invoiceText = $invoiceText;
                $invoiceRepo->insert($invoiceNew);
                $sectional = $number . '/' . $invoiceType;

                // fatture in cloud*/

                $api_uid = $this->app->cfg()->fetch('fattureInCloud','api_uid');
                $api_key = $this->app->cfg()->fetch('fattureInCloud','api_key');
                if ($hasInvoice == '1' && $isExtraUe == '0') {
                    $insertJson = '{
  "api_uid": "' . $api_uid . '",
  "api_key": "' . $api_key . '",
  "id_cliente": "0",
  "id_fornitore": "0",
  "nome": "' . $userAddress->subject . '",
  "indirizzo_via": "' . $userAddress->address . '",
  "indirizzo_cap": "' . $userAddress->postcode . '",
  "indirizzo_citta": "' . $userAddress->city . '",
  "indirizzo_provincia": "' . $userAddress->province . '",
  "indirizzo_extra": "",
  "paese": "Italia",
  "paese_iso": "' . $userAddress->country->ISO . '",
  "lingua": "it",
  "piva": "' . $userAddress->vatNumber . '",
  "cf": "' . $userAddress->vatNumber . '",
  "autocompila_anagrafica": false,
  "salva_anagrafica": false,
  "numero": "' . $sectional . '",
  "data": "' . $todayInvoice . '",
  "valuta": "EUR",
  "valuta_cambio": 1,
  "prezzi_ivati": true,
  "rivalsa": 0,
  "cassa": 0,
  "rit_acconto": 0,
  "imponibile_ritenuta": 0,
  "rit_altra": 0,
  "marca_bollo": 0,
  "oggetto_visibile": "",
  "oggetto_interno": "",
  "centro_ricavo": "",
  "centro_costo": "",
  "note": "",
  "nascondi_scadenza": false,
  "ddt": false,
  "ftacc": false,
  "id_template": "0",
  "ddt_id_template": "0",
  "ftacc_id_template": "0",
  "mostra_info_pagamento": false,';
                    $orderPaymentMethodId = $order->orderPaymentMethodId;
                    $orderPaymentMethodTranslation = \Monkey::app()->repoFactory->create('OrderPaymentMethodTranslation')->findOneBy(['orderPaymentMethodId' => $orderPaymentMethodId,'langId' => 1]);
                    $metodo_pagamento = $orderPaymentMethodTranslation->name;
                    switch ($orderPaymentMethodId) {
                        case 1:
                            $metodo_titoloN = 'Merchant Paypal';
                            $metodo_descN = $api_uid = $this->app->cfg()->fetch('payPal','business');
                            break;
                        case 2:
                            $metodo_titoloN = 'Merchant Nexi';
                            $metodo_descN = '';
                            break;
                        case 3:
                            $metodo_titoloN = 'IBAN';
                            $metodo_descN = 'IT54O0521613400000000002345';
                            break;
                        case 5:
                            $metodo_titoloN = '';
                            $metodo_descN = '';
                            break;

                    }


                    $insertJson .= '"metodo_pagamento": "' . $metodo_pagamento . '",
  "metodo_titoloN": "' . $metodo_titoloN . '",
  "metodo_descN": "' . $metodo_descN . '",
  "mostra_totali": "tutti",
  "mostra_bottone_paypal": false,
  "mostra_bottone_bonifico": false,
  "mostra_bottone_notifica": false,';
                    $insertJson .= '"lista_articoli": ';
                    $tot = 0;
                    $i = 0;
                    $scontotot = 0;
                    $articoli = [];
                    $ordinearticolo = 0;
                    foreach ($order->orderLine as $orderLine) {
                        $idlineaordine = $i + 1;
                        $idOrderLine = $orderLine->id;
                        $ordinearticolo + 1;
                        $productSku = CProductSku::defrost($orderLine->frozenProduct);
                        $codice = $orderLine->orderId . "-" . $orderLine->id;
                        $productNameTranslation = $productRepo->findOneBy(['productId' => $productSku->productId,'productVariantId' => $productSku->productVariantId,'langId' => '1']);
                        $nome = $productSku->productId . "-" . $productSku->productVariantId . "-" . $productSku->productSizeId;
                        $um = "";
                        $quantity = $productSku->stockQty;
                        $productSize = \Monkey::app()->repoFactory->create('ProductSize')->findOneBy(['id' => $productSku->productSizeId]);

                        $descrizione = (($productNameTranslation) ? $productNameTranslation->name : '') . ($orderLine->warehouseShelfPosition ? ' / ' . $orderLine->warehouseShelfPosition->printPosition() : '') . ' ' . $productSku->product->productBrand->name . ' - ' . $productSku->productId . '-' . $productSku->productVariantId . " " . $productSize->name;
                        $categoria = "";
                        $prezzo_netto = number_format($orderLine->activePrice + $orderLine->couponCharge,2);
                        $prezzo_lordo = number_format($orderLine->activePrice,2);
                        $scontoCharge = number_format($orderLine->couponCharge,2);
                        $sconto = abs($scontoCharge);
                        $sconto = number_format(100 * $sconto / $orderLine->activePrice,2);
                        $cod_iva = "0";
                        $applica_ra_contributi = "true";
                        $ordine = $ordinearticolo;
                        $sconto_rosso = "0";
                        $in_ddt = false;
                        $magazzino = true;
                        $scontotot += abs($scontoCharge);
                        $tot += $orderLine->activePrice;
                        /*  $insertLineJSon.='{
                                 "id": "'.$idlineaordine.'",
                                "codice": "'.$codice.'",
                                "nome": "'.$descrizione.'",
                                "um": "",
                                "quantita": '.$quantity.',
                                "descrizione": "'.$descrizione.'",
                                "categoria": "",
                                "prezzo_netto": '.$prezzo_netto.',
                                "prezzo_lordo": '.$prezzo_lordo.',
                                "cod_iva": 0,
                                "tassabile": true,
                                "sconto": '.$sconto.',
                                "applica_ra_contributi": true,
                                "ordine": '.$ordine.',
                                "sconto_rosso": 0,
                                "in_ddt": false,
                                "magazzino": true},
                          ';*/
                        $articoli[] = [
                            'id' => $idlineaordine,
                            'codice' => $codice,
                            'nome' => $nome,
                            'um' => $um,
                            'quantita' => 1,
                            'descrizione' => $descrizione,
                            'categoria' => $categoria,
                            'prezzo_netto' => $prezzo_netto,
                            'prezzo_lordo' => $prezzo_lordo,
                            'cod_iva' => $cod_iva,
                            'tassabile' => true,
                            'sconto' => $sconto,
                            'applica_ra_contributi' => $applica_ra_contributi,
                            'ordine' => $ordine,
                            'sconto_rosso' => $sconto_rosso,
                            'in_ddt' => $in_ddt,
                            'magazzino' => $magazzino
                        ];
                    }
                    $tot = number_format($tot,2) - number_format($scontotot,2);
                    $today = new DateTime();
                    $dateInvoice = $today->format('d/m/Y');
                    $insertJson .= json_encode($articoli) . ',
                  
                "lista_pagamenti": [
              {
               "data_scadenza":"' . $dateInvoice . '",
               "importo": ' . number_format($tot,2) . ',
               "metodo": "not",
               "data_saldo": "' . $dateInvoice . '" 
              }
              ],
              "ddt_numero": "",
              "ddt_data": "' . $dateInvoice . '",
              "ddt_colli": "",
              "ddt_peso": "",
              "ddt_causale": "",
              "ddt_luogo": "",
              "ddt_trasportatore": "",
              "ddt_annotazioni": "",
              "PA": false, 
              "PA_tipo_cliente": "B2B", 
              "PA_tipo": "nessuno",
              "PA_numero": "",
              "PA_data": "' . $dateInvoice . '",
              "PA_cup": "",
              "PA_cig": "",
              "PA_codice": "",
              "PA_pec": "",
              "PA_esigibilita": "N",
              "PA_modalita_pagamento": "MP01",
              "PA_istituto_credito": "",
              "PA_iban": "",
              "PA_beneficiario": "",
              "extra_anagrafica": {
                "mail": "",
                "tel": "",
                "fax": ""
              },
              "split_payment": false
            }';
                    \Monkey::app()->applicationLog('InvoiceAjaxController','Report','jsonfattureincloud','Json Fatture in Cloud fattura Numero' . $number . ' data:' . $dateInvoice,$insertJson);
                    $urlInsert = "https://api.fattureincloud.it/v1/fatture/nuovo";
                    $options = array(
                        "http" => array(
                            "header" => "Content-type: text/json\r\n",
                            "method" => "POST",
                            "content" => $insertJson
                        ),
                    );
                    $context = stream_context_create($options);
                    $result = json_decode(file_get_contents($urlInsert,false,$context),true);
                    if (array_key_exists('success',$result)) {
                        $resultApi = "Risultato=" . $result['success'] . " new_id:" . $result['new_id'] . " token:" . $result['token'];
                    } else {
                        $resultApi = "Errore=" . $result['error'] . " codice di errore:" . $result['error_code'];
                    }
                    \Monkey::app()->applicationLog('InvoiceAjaxController','Report','ResponseApi fatture in Cloud Numero' . $sectional . ' data:' . $dateInvoice,'Risposta FatturaincCloud',$resultApi);
                    if (array_key_exists('new_id',$result)) {
                        $fattureinCloudId = $result['new_id'];
                    }
                    if (array_key_exists('token',$result)) {
                        $fattureinCloudToken = $result['token'];

                        $updateInvoice = \Monkey::app()->repoFactory->create('Invoice')->findOneBy(['orderId' => $orderId]);
                        $updateInvoice->fattureInCloudId = $fattureinCloudId;
                        $updateInvoice->fattureInCloudToken = $fattureinCloudToken;
                        $updateInvoice->update();
                    }

                    /*fine fatture in cloud*/
                    $sectional = $number . '/' . $invoiceType;


                    /** ottengo il valore della Vendita per la registrazione del  Documento */
                    $documentRepo = \Monkey::app()->repoFactory->create('Document');
                    // codice per inserire all'interno della cartella document
                    $checkIfDocumentExist = $documentRepo->findOneBy(['number' => $sectional,'year' => $year]);
                    if ($checkIfDocumentExist == null) {
                        $insertDocument = $documentRepo->getEmptyEntity();
                        $insertDocument->userId = $filterUserAddress;
                        $insertDocument->userAddressRecipientId = $userAddress->id;
                        $insertDocument->shopRecipientId = $userAddress->id;
                        $insertDocument->number = $sectional;
                        $insertDocument->invoiceTypeId = $documentType;
                        $insertDocument->paydAmount = $amountForInvoice;
                        $insertDocument->paymentExpectedDate = $order->paymentDate;
                        $insertDocument->note = $order->note;
                        $insertDocument->totalWithVat = $amountForInvoice;
                        $insertDocument->year = $year;
                        $insertDocument->insert();
                    }


                    /* definizione dello shop Supplier*/
                    $remoteShopSupplier = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $orderLine->shopId]);
                    /*** dati db esterno ***/
                    $db_host = $remoteShopSupplier->dbHost;
                    $db_name = $remoteShopSupplier->dbName;
                    $db_user = $remoteShopSupplier->dbUsername;
                    $db_pass = $remoteShopSupplier->dbPassword;


                }

            } catch
            (\Throwable $e) {
                throw $e;
                $this->app->router->response()->raiseProcessingError();
                $this->app->router->response()->sendHeaders();
            }


        }


        return true;
    }


}