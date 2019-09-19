<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\entities\CCart;
use bamboo\domain\entities\CCartLine;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\exceptions\RedPandaLogicException;
use bamboo\core\exceptions\RedPandaOrderLogicException;
use bamboo\domain\entities\CProductPublicSku;
use bamboo\utils\price\SPriceToolbox;


/**
 * Class CCartRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CCartRepo extends ARepo
{
    use TMySQLTimestamp;

    CONST ORD_INIT_STATUS = "ORD_PENDING";
    CONST DEFAULT_COUNTRY = "ITA";

    CONST CART_TYPE_CART = 1;
    CONST CART_TYPE_CART_MERGED = 2;
    CONST CART_TYPE_CART_ORDERED = 3;
    CONST CART_TYPE_WISH = 4;
    CONST CART_TYPE_TRN = 5;

    /** USEFUL? */
    private $cartId = null;

    /**
     * @var array $registeredEvents
     */
    private $registeredEvents = [];

    public function isEmpty()
    {
        return $this->currentCart()->cartLine->isEmpty();
    }

    /**
     * @return CCart
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function currentCart()
    {
        $cart = $this->findOne([$this->currentCartId()]);
        if (is_null($cart->userId) && $this->app->getUser()->getId() != 0) {
            $cart->userId = $this->app->getUser()->getId();
            $cart->update();
        }
        return $cart;
    }

    /**
     * Returns THE valid cart id for this session
     *
     * @param bool $new
     * @return int|null
     */
    public function currentCartId($new = false)
    {
        if (!$new && !is_null($this->cartId)) return $this->cartId;
        if ($this->app->getUser()->id == 0) {
            $sql = "SELECT DISTINCT c.id AS id
                        FROM Cart c
                          JOIN UserSessionHasCart ushc ON c.id = ushc.cartId
                          JOIN UserSession u ON ushc.userSessionId = u.id
                        WHERE c.cartTypeId = ? AND u.id = ?";

            $cartsIds = $this->app->dbAdapter->query(
                $sql,
                [self::CART_TYPE_CART, $this->app->getSession()->getId()])->fetchAll();
        } else {
            $slowSql = "SELECT DISTINCT c.id AS id
                        FROM Cart c
                          JOIN UserSessionHasCart ushc ON c.id = ushc.cartId
                          JOIN UserSession u ON ushc.userSessionId = u.id
                        WHERE c.cartTypeId = ? AND ( c.userId = ? OR u.id = ? OR u.userId = ?)";
            $sql = "SELECT DISTINCT id FROM (
                      SELECT c.id AS id
                      FROM Cart c
                        JOIN UserSessionHasCart ushc ON c.id = ushc.cartId
                        JOIN UserSession u ON ushc.userSessionId = u.id
                      WHERE c.cartTypeId = ? AND (u.id = ? OR u.userId = ?)
                      UNION
                      SELECT c.id AS id
                          FROM Cart c
                          WHERE c.cartTypeId = ? AND c.userId = ?
                    ) q1";
            $cartsIds = $this->app->dbAdapter->query(
                $sql,
                [self::CART_TYPE_CART,
                    $this->app->getSession()->getId(),
                    $this->app->getUser()->id,
                    self::CART_TYPE_CART,
                    $this->app->getUser()->id])->fetchAll();
        }

        switch (count($cartsIds)) {
            case 0:
                $this->cartId = $this->createCart();
                break;
            case 1:
                $this->cartId = $cartsIds[0]['id'];
                break;
            default:
                $this->cartId = $this->mergeCartsIds($cartsIds);
                break;
        }
        return $this->cartId;
    }

    /**
     * Create a new cart for Session
     *
     * @return int
     * @throws \bamboo\core\exceptions\RedPandaORMInvalidEntityException
     */
    protected function createCart()
    {
        $cart = $this->getEmptyEntity();
        $cart->cartTypeId = $this::CART_TYPE_CART;
        if ($this->app->getUser()->getId() != 0) $cart->userId = $this->app->getUser()->getId();
        $cart->id = $cart->insert();

        $userSessionHasCart = \Monkey::app()->repoFactory->create('UserSessionHasCart')->getEmptyEntity();
        $userSessionHasCart->userSessionId = $this->app->getSession()->getId();
        $userSessionHasCart->cartId = $cart->id;
        $userSessionHasCart->insert();
        return $cart->id;
    }

    /**
     * Merge carts into the oldest one, starts from an array of cart ids
     *
     * @param array $cartsIds
     * @return int
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    protected function mergeCartsIds(array $cartsIds)
    {
        $col = new CObjectCollection();
        foreach ($cartsIds as $id) {
            $col->add($this->findOne([$id['id']]));
        }
        return $this->mergeCarts($col);
    }

    /**
     * Merge carts into the oldest one, starts from a collection of carts object
     *
     * @param CObjectCollection $carts
     * @return int
     */
    protected function mergeCarts(CObjectCollection $carts)
    {
        $first = $carts->getFirst();
        foreach ($carts as $cart) {
            if ($cart->creationDate < $first->creationDate) $first = $cart;
        }
        $carts->del($first);

        foreach ($carts as $cart) {
            $this->registerEvent($cart->id, 'Cart being merged to', 'Merging to cart: ' . $first->id);
            $this->registerEvent($first->id, 'Cart being merged in', 'Merging from cart: ' . $cart->id);

            try {
                if (!is_null($cart->couponId) && is_null($first->couponId)) {
                    $first->couponId = $cart->couponId;
                    $first->update();
                }
                // todo passare anche date, indirizzi e simili
                $cart->cartTypeId = $this::CART_TYPE_CART_MERGED;
                $cart->update();

            } catch (\Throwable $e) {
                $this->app->router->response()->raiseUnauthorized();
            }

            foreach ($cart->cartLine as $cartLine) {
                $this->mergeLine($cartLine, $first);
            }
            foreach ($cart->userSessionHasCart as $userSessionHasCart) {
                try {
                    $userSessionHasCart->delete();
                    $userSessionHasCart->cartId = $first->id;
                    $userSessionHasCart->insert();
                } catch (\Throwable $e) {
                }
            }
            $this->registerEvent($first->id, 'Cart has been merged in', 'Merged from cart: ' . $cart->id);
        }
        return $first->id;
    }

    /**
     * Register the event to CartHistory
     *
     * @param $cartId
     * @param $event
     * @param $description
     * @return bool
     */
    public function registerEvent($cartId, $event, $description)
    {
        try {
            $this->registeredEvents[] = ['cartId' => $cartId, 'event' => $event, 'description' => $description];
        } catch (\Throwable $e) {
            return false;
        }
        return true;
    }

    /**
     * Merge a line of carts, copy it into an other cart
     *
     * @param CCartLine $line
     * @param CCart $cart
     * @return int
     */
    public function mergeLine(CCartLine $line, CCart $cart)
    {
        $oldOrder = $line->cartId;
        $line->cartId = $cart->id;
        $line->id = $line->insert();
        if ($line->id) {
            $description = 'Cart: ' . $line->id . ' line: ' . $line->id . ' for user: ' . $this->app->getUser()->getId() . ' in session: ' . $this->app->getSession()->getSid() . ' merged from Cart: ' . $oldOrder . ' line:' . serialize($line);
            $this->registerEvent($line->id, 'Item merged to Cart', $description);
            return $line->id;
        }
        return 0;
    }

    /**
     * @param $cart
     * @param null $grossTotal
     * @return float|int|null
     */
    public function calculatePaymentModifier($cart, $grossTotal = null)
    {
        if (is_null($cart)) $cart = $this->currentCart();
        if (is_null($grossTotal)) $grossTotal = $this->calculateGrossTotal($cart);

        /** Metodo di pagamento */
        $paymentModifier = 0;
        if (isset($cart->orderPaymentMethod->modifier) && ($modifier = $cart->orderPaymentMethod->modifier) != null) {
            if (strstr($modifier, '%')) {
                $mod = substr($modifier, 0, strpos($modifier, '%'));
                $paymentModifier = $grossTotal * $mod * 0.01;
            } else {
                $paymentModifier += $modifier;
            }
        }
        return $paymentModifier;
    }

    /**
     * @param $cart
     * @return double
     */
    public function calculateGrossTotal($cart) //@TODO da cancellare e implementare quella in COrderRepo
    {
        /** Totale prodotti * GrossTotal */
        try {
            $grossTotal = 0;
            foreach ($cart->cartLine as $line) {
                $grossTotal += (($line->productPublicSku->product->isOnSale()) ? $line->productPublicSku->salePrice : $line->productPublicSku->price);
            }
            return $grossTotal;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param $couponCode
     * @param CCart|null $cart
     * @return bool
     */
    public function setCouponCodeToCart($couponCode, CCart $cart = null)
    {
        $coup = trim($couponCode);
        if (!empty($coup)) {
            $repo = \Monkey::app()->repoFactory->create('Coupon');
            $coupon = $repo->findOneBy(['valid' => 1, 'code' => $coup]);
            if ($coupon == false) {
                $coupon = \Monkey::app()->repoFactory->create('CouponEvent')->getCouponFromEvent($coup);
            }
            if ($coupon instanceof AEntity) {
                if ($coupon->couponType->validForCartTotal > 0) {
                    if ($cart->getGrossTotal() > $coupon->couponType->validForCartTotal) {
                        $cart->couponId = $coupon->id;
                    }
                } else {
                    $cart->couponId = $coupon->id;
                }
                try {
                    $cart->update();
                    return true;
                } catch (\Throwable $e) {
                }
            }
        }
        return false;
    }

    /**
     * @param $cart
     * @param null $grossTotal
     * @return int
     * @throws RedPandaLogicException
     */
    public function calculateCouponModifier($cart, $grossTotal = null)
    {
        if (is_null($grossTotal)) {
            $grossTotal = $this->calculateGrossTotal($cart);
        }
        /** Sconto dal Coupon */
        $couponDiscount = 0;
        if (!is_null($cart->coupon) && isset($cart->coupon->amountType)) {
            switch ($cart->coupon->amountType) {
                case 'P':
                    $couponDiscount = -1 * $grossTotal * $cart->coupon->amount * 0.01;
                    break;
                case 'F':
                    $couponDiscount = -1 * $cart->coupon->amount;
                    break;
                case 'G':
                    $fullPrice = $this->calculateFullPriceTotal($cart);
                    $couponDiscount = -1 * ($grossTotal - ($fullPrice - ($fullPrice * $cart->coupon->amount * 0.01)));
                    break;
                default:
                    throw new RedPandaLogicException('Coupon type not supported %s', [$cart->coupon->amountType]);
            }

        }
        return $couponDiscount;
    }

    /**
     * Calcola il totale per il prezzo pieno dei prodotti
     *
     * @param $cart
     * @return double
     */
    public function calculateFullPriceTotal($cart) //@TODO calculate full price total
    {
        /** Totale prodotti * Full Price */
        $total = 0;
        try {
            foreach ($cart->cartLine as $line) {
                /** @var CCartLine $line */
                $total += $line->productPublicSku->price;
            }
            return $total;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param CCart|null $cart
     * @return int|mixed
     */
    public function calculateVat(CCart $cart = null)
    {
        try {
            if (is_null($cart)) $cart = $this->currentCart();
            if (is_null($cart->billingAddress)) return 0;
            $vatPercent = $cart->billingAddress->country->vat;
            $vat = SPriceToolbox::vatFromGross($cart->netTotal, $vatPercent, true);
            return $vat;
        } catch (\Throwable $e) {
            \Monkey::app()->applicationWarning('Cart Manager', 'Error Calculating Vat', 'Cart: ' . $cart->id . ' netTotal:' . $cart->netTotal, $e);
        }
        return 0;
    }

    /**
     * @return CCart
     */
    public function fetchEntityByCurrentCart()
    {
        return $this->currentCart();
    }


    /**
     * @param $payment
     * @return bool
     */
    public function updatePaymentMethod($payment)
    {
        try {
            if ($this->setPaymentMethodId($payment)) return true;
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param $paymentMethodId
     * @return bool
     */
    public function setPaymentMethodId($paymentMethodId, $cartExt = null)
    {
        $cart = $cartExt ?? $this->currentCart();
        try {
            $cart->orderPaymentMethodId = $paymentMethodId;
            if (isset($cart->orderPaymentMethod)) {
                unset($cart->orderPaymentMethod);
            }
            $cart->update();
            $this->registerEvent($cart->id, 'Updating Payment Method', 'Payment Method set to ' . $paymentMethodId);
        } catch (\Throwable $e) {
            $this->registerEvent($cart->id, 'Fail Updating Payment Method', 'Fail Updating Payment Method to ' . $paymentMethodId);
            $this->app->router->response()->raiseUnauthorized();
            return false;
        }

        return true;
    }

    /**
     * Adds a line to carts starting for a sku of a product and a quantity
     *
     * @param CProductPublicSku $productPublicSku
     * @param int $qty
     * @param CCart|null $cart
     * @return int
     */
    public function addSku(CProductPublicSku $productPublicSku, $qty = 1, CCart $cart = null,$remoteShopSellerId= null)
    {
        if(!isset($remoteShopSellerId)){
            $remoteShopSellerId=44;
        }
        $cart = $cart ?? $this->currentCart();
        $lines = $cart->cartLine->findByKeys(['productId' => $productPublicSku->productId,
            'productVariantId' => $productPublicSku->productVariantId,
            'productSizeId' => $productPublicSku->productSizeId]);
        if ($productPublicSku->stockQty < (count($lines) + $qty)) return -502;

        $count = 0;
        try {
            $cartLine = \Monkey::app()->repoFactory->create('CartLine')->getEmptyEntity();

            $cartLine->cartId = $cart->id;
            $cartLine->productId = $productPublicSku->productId;
            $cartLine->productVariantId = $productPublicSku->productVariantId;
            $cartLine->productSizeId = $productPublicSku->productSizeId;
            $cartLine->remoteShopSellerId=$remoteShopSellerId;

            while ($qty > 0) {
                $qty--;
                $cartLine->smartInsert();
                if ($cartLine->id > 0) {
                    $count++;
                    $description = 'Cart: ' . $cart->printId() . ',' . json_encode($cartLine) . ' for user: ' . $this->app->getUser()->getId() . ' in session: ' . $this->app->getSession()->getSid();
                    $this->registerEvent($cart->id, 'Item added to Cart', $description);
                }
            }
            return $count;
        } catch (\Throwable $e) {
            return $count > 0 ? -1 * 5000 + $count : 5000;
        }
    }

    /**
     * @param $cartLine
     * @param int $qty
     */
    public function removeSku($cartLine, $qty = 1)
    {
        if (!($cartLine instanceof CCartLine)) {
            $cartLine = \Monkey::app()->repoFactory->create('CartLine')->findOne(['id' => $cartLine, 'cartId' => $this->currentCartId()]);
        }
        if (!$cartLine) return;
        $this->registerEvent($cartLine->cartId, 'Item deleted from cart', "orderLine = " . $cartLine->id);
        $cartLine->delete();
    }

    /**
     *
     */
    public function removeCoupon()
    {
        $cart = $this->currentCart();
        $cart->couponId = null;
        $cart->update();
    }

    /**
     * @param $shippingPrice
     * @return bool
     */
    public function setFrozenShippingPrice($shippingPrice)
    {
        $cart = $this->currentCart();
        try {
            $cart->shippingPrice = $shippingPrice;
            $cart->update();
            $this->registerEvent($cart->id, 'Updating Shipping Price', 'Shipping Price set to ' . $shippingPrice);
        } catch (\Throwable $e) {
            $this->registerEvent($cart->id, 'Fail Updating Shipping Price', 'Fail Updating Shipping Price to ' . $shippingPrice);
            $this->app->router->response()->raiseUnauthorized();
            return false;
        }
        return true;
    }

    /**
     * @param string $type
     */
    public function deleteAddress($type = 'Shipping')
    {
        $cart = $this->currentCart();
        try {
            if ($type == 'Shipping') {
                $cart->shipmentAddressId = null;
            } else {
                $cart->billingAddressId = null;
            }
            $cart->update();
        } catch (\Throwable $e) {
            $this->app->router->response()->raiseUnauthorized();
        }
    }

    /**
     * @param $address
     * @param null $userId
     * @param CCart|null $cart
     * @return bool
     */
    public function setShipmentAddress($address, $userId = null, CCart $cart = null)
    {
        if (!$address instanceof IEntity) {
            $address = \Monkey::app()->repoFactory->create('UserAddress')->findOne(
                ['id' => $address,
                    'userId' => $userId ?? $this->app->getUser()->getId()]);
            if (!(bool)$address) return false;
        }
        $cart = $cart ?? $this->currentCart();
        try {
            $this->registerEvent($cart->id, 'Updating Shipping Address', 'Shipping was UserAddress id : ' . $address->id);
            $cart->shipmentAddressId = $address->id;
            $cart->update();
            return true;
        } catch (\Throwable $e) {
            $this->registerEvent($cart->id, 'Fail Updating Shipping Address', 'Fail Updating Shipping Address to : ' . $address->id);
            $this->app->router->response()->raiseUnauthorized();
            return false;
        }
    }

    /**
     * @param $address
     * @param null $userId
     * @param CCart|null $cart
     * @return bool
     */
    public function setBillingAddress($address, $userId = null, CCart $cart = null)
    {
        if (!$address instanceof IEntity) {
            $address = \Monkey::app()->repoFactory->create('UserAddress')->findOne(
                ['id' => $address,
                    'userId' => $userId ?? $this->app->getUser()->getId()]);
            if (!(bool)$address) return false;
        }

        $cart = $this->currentCart();
        try {
            $this->registerEvent($cart->id, 'Updating Billing Address', 'Billing was UserAddress id : ' . $address->id);
            $cart->billingAddressId = $address->id;
            $cart->update();

            return true;
        } catch (\Throwable $e) {
            $this->registerEvent($cart->id, 'Fail Updating Billing Address', 'Fail Updating Billing Address to : ' . $address->id);
            $this->app->router->response()->raiseUnauthorized();
            return false;
        }
    }
    /**
     * @param $hasInvoice
     * @parma $countryId
     * @param null $userId
     * @param CCart|null $cart
     * @return bool
     */
    public function setHasInvoice($hasInvoice, $countryId, $userId = null, CCart $cart = null)
    {
        $countryRepo=\Monkey::app()->repoFactory->create('Country');
        $findCountry=$countryRepo->findOneBy(['id'=>$countryId]);
        $isExtraUe=$findCountry->extraue;

        $cart = $this->currentCart();
        try {
            if($isExtraUe==='1'){
                $documenteRelease="Release  with Invoice";
            }else {
                if ($hasInvoice === "1") {
                    $documenteRelease = "Release  with Invoice";
                } else {
                    $documenteRelease = "Release  with Receipt";
                }
            }
            $this->registerEvent($cart->id, 'Updating Has Invoice ', 'Billing will be '.$documenteRelease);
            $cart->hasInvoice=$hasInvoice;
            $cart->update();

            return true;
        } catch (\Throwable $e) {
            $this->registerEvent($cart->id, 'Fail Updating Billing Has Invoice', 'Fail Updating Billing  will be '.$documenteRelease);
            $this->app->router->response()->raiseUnauthorized();
            return false;
        }
    }

    /**
     * @return \bamboo\core\db\pandaorm\entities\AEntity
     * @throws RedPandaOrderLogicException
     * @throws \Throwable
     */
    public function cartToOrder()
    {
        $cart = $this->currentCart();
        if ($this->app->getUser()->getId() == 0) throw new RedPandaOrderLogicException('Utente non loggato mentre creo il carrello', [], -10);
        return $this->customCartToOrder($cart, $this->app->getUser());
    }

    /**
     * @param CCart $cart
     * @param null $user
     * @return mixed
     * @throws \Throwable
     * @transaction
     */
    public function customCartToOrder(CCart $cart, $user = null)
    {
        /** @var COrderRepo $orderRepo */
        $orderRepo = \Monkey::app()->repoFactory->create('Order');
        try {
            $user = $user ?? $cart->user;

            $this->registerEvent($cart->id, "Porto stato ordine in Transition", "L'ordine " . $cart->id . " è stato confermato, devo trasformarlo in carrello");

            \Monkey::app()->repoFactory->beginTransaction();
            /** Metto il carrello in stato di transizione */
            try {
                $cart->cartTypeId = self::CART_TYPE_TRN;
                $cart->update();
            } catch (\Throwable $e) {
                \Monkey::app()->applicationError('CartRepo',
                    'Error while putting the cart in transaction',
                    'Working with cart: ' . $cart->printId() . ' for user ' . $user->printId(),
                    $e);
                throw new BambooLogicException('Non ho potuto cambiare lo stato a TRN', [], -1, $e);
            }
            /** Creo il nuovo ordine (in OrderRepo) */
            try {
                $order = $orderRepo->prepareNewOrderFromCart($cart);
            } catch (\Throwable $e) {
                $this->app->applicationError('CartRepo', 'CreazioneOrdine', 'Errore nella creazione dell\'ordine', $e);
                throw new BambooLogicException('Non sono riuscito a creare un ordine nuovo', [], -2, $e);
            }
            /** Nascondo il carrello e avvio l'ordine */
            try {
                $cart->cartTypeId = self::CART_TYPE_CART_ORDERED;
                $orderRepo->finalizeNewOrder($order);
            } catch (\Throwable $e) {
                throw new BambooLogicException('Non ho potuto finalizzare l\'ordine', [], -3, $e);
            }
            \Monkey::app()->repoFactory->commit();

            $this->registerEvent($cart->id, 'Carrello trasformato in Ordine', 'Trasformazione completata');
            \Monkey::app()->eventManager->triggerEvent('userCreateOrder',
                [
                    'order' => $order,
                    'user' => $this->app->getUser(),
                ]);

            if ($order instanceof IEntity) {
                return $order;
            } else {
                throw new BambooLogicException('errore sconosciuto', [], -6);
            }

        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            \Monkey::app()->cacheService->getCache('entities')->flush();
            $this->registerEvent($cart->id, 'Errore generico nella trasformazione in oridne', 'Messaggio: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @throws BambooException WriteOut CartHistory
     */
    public function __destruct()
    {
        try {
            \Monkey::app()->repoFactory->commit();
            \Monkey::app()->repoFactory->beginTransaction();
            foreach ($this->registeredEvents as $v) {
                $this->app->dbAdapter->insert('CartHistory', $v);
            }
            \Monkey::app()->repoFactory->commit();
        } catch (\Throwable $e) {
            try {
                $v = json_encode($v);
            } catch (\Throwable $e2) {
                $v = 'Could not retrive cart history row';
            }
            try {
                \Monkey::app()->applicationWarning('CCartRepo', 'Error registering CartHistory', $v, $e);
            } catch (\Throwable $e) {
            }
        }
    }
}