<?php

namespace bamboo\ecommerce\business;

use bamboo\domain\entities\CCart;
use bamboo\domain\entities\COrder;
use bamboo\domain\entities\CUserAddress;
use bamboo\core\application\AApplication;

/**
 * Class CCheckoutStep
 * @package bamboo\app\business
 */
class CCheckoutStep implements \Iterator, \Countable
{
    protected $pointer = 0;
    protected $checkoutSteps = [];
    protected $app;

    /**
     * @param AApplication $app
     * @throws \bamboo\core\exceptions\RedPandaConfigException
     */
    public function __construct(AApplication $app)
    {
        $this->app = $app;
        $this->pointer = 0;
        if(!($this->checkoutSteps = $this->app->cacheService->getCache('misc')->get('checkoutSteps'))) {
            $i= 0;
            foreach (json_decode(file_get_contents($this->app->rootPath().$app->cfg()->fetch('paths','business-data').'/checkoutSteps.json'),true) as $step) {
                if ($step['active']) {
                    $step['pointer'] = $i;
                    $this->checkoutSteps[] = $step;
                    $i++;
                }
            }
            $this->app->cacheService->getCache('misc')->set('checkoutSteps',$this->checkoutSteps);
        }
    }

    /**
     * read present step for page
     * @return mixed|null
     */
    public function fetchPresentStep() {
        foreach ($this->checkoutSteps as $key=>$val) {
            if($this->app->router->getMatchedRoute()->getPattern() == $val['pattern']) {
                $this->pointer = $key;
                return $val;
            }
        }
        return null;
    }

    /**
     * return present step
     * @param null $attribute
     * @return mixed
     */
    public function current($attribute = null)
    {
        if(is_null($attribute)) {
            return $this->checkoutSteps[$this->pointer];
        } else return $this->checkoutSteps[$this->pointer][$attribute];
    }

    /**
     * go to next step if exists
     * @return mixed
     */
    public function next()
    {
        $this->pointer++;
        if ($this->valid()) {
            return $this->current();
        }
        $this->pointer--;
        return $this->current();
    }

    /**
     * go to prev step if exists
     * @return mixed
     */
    public function prev()
    {
        $this->pointer--;
        if ($this->valid()) {
            return $this->current();
        }
        $this->pointer++;
        return $this->current();
    }

    public function setPointer($pointer) {
        $this->pointer = $pointer;
    }
    /**
     * return pointer
     * @return int
     */
    public function key()
    {
        return $this->pointer;
    }

    /**
     * does step exist?
     * @param $pointer
     * @return bool
     */
    public function valid($pointer = null)
    {
        return isset($this->checkoutSteps[$pointer ?? $this->pointer]);
    }

    /**
     * reset pointer to 0;
     */
    public function rewind()
    {
        $this->pointer = 0;
    }

    /**
     * @return int
     */
    public function count()
    {
        return (int) count($this->checkoutSteps);
    }

    public function pointerNumber($valueForInfo)
    {
        foreach($this->checkoutSteps as $key => $value) {
            if ($value['name'] == $valueForInfo || $value['address'] == $valueForInfo ) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Returns the full address of the current step
     * @return string
     */
    public function fullAddress()
    {
        return $this->app->baseUrl(false).$this->stepAddress($this->current('pointer'));
    }

    /**
     * @param $index
     * @return string
     */
    public function stepAddress($index) {
        return "/".$this->app->getLang()->getLang()."/".(
            $this->checkoutSteps[$index]['address'][$this->app->getLang()->getLang()] ??
            $this->checkoutSteps[$index]['address'][$this->app->getDefaultLanguage()] );
    }

    /**
     * @param bool $skipIfValid
     * @param null $step
     * @return mixed|null
     */
    public function fetchNextStep($skipIfValid = true, $step = null)
    {
        $step = $step ?? $this->fetchPresentStep();
        if($this->valid($step['pointer'] +1)) {
            if($skipIfValid &&
                $this->checkoutSteps[$step['pointer']+1]['skipIfValid'] &&
                $this->{$this->checkoutSteps[$step['pointer']+1]['name']}()) {
                return $this->fetchNextStep($skipIfValid, $this->checkoutSteps[$step['pointer']+1]);
            }
            return $this->checkoutSteps[$step['pointer'] +1];
        }
        return null;
    }

    /**
     * Move to the required phase or to the most advanced Valid step
     * Returns the phase
     * @param $phase
     * @return bool|mixed
     */
    public function validate($phase = null)
    {
        $phase = $phase ?? $this->fetchPresentStep()['name'];

        if ($phase == 'thankyou') {
            return true;
        }

        if (\Monkey::app()->repoFactory->create('Cart')->isEmpty()) {
            return false;
        }

        $phasePointer = $this->pointerNumber($phase);
        $this->rewind();
        while($this->pointer < $phasePointer){
            if(!$this->{$this->current('name')}()){
                break;
            }
            $this->next();
        }
        return $this->current();
    }

    public function isValidPhase($phase = null) {
        $phase = $phase ?? $this->fetchPresentStep()['name'];
        return $this->validate($phase)['name'] == $phase;
    }

    protected function cart() {
        return true;
    }

    /**
     * True if the regestration step is valid false otherwise
     * @return bool
     */
    protected function registration()
    {
        if($this->app->getUser()->getId() > 0){
            return true;
        }
        return false;
    }

    /**
     * True if the email confirmation step is valid false otherwise
     * @return bool
     */
    protected function activateAccount()
    {
        if($this->app->getUser()->isActive == 1){
            return true;
        }
        return false;
    }

    /**
     * True if the payment method step step is valid false otherwise
     * @return bool
     */
    protected function paymentMethod()
    {
        $cart = \Monkey::app()->repoFactory->create('Cart')->currentCart();

        if (!is_null($cart->orderPaymentMethod)){
            return true;
        }

        if ($order = $this->app->orderManager->lastOrder()) {
            if (\Monkey::app()->repoFactory->create('Cart')->setPaymentMethodId($order->orderPaymentMethodId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function shipping()
    {
        $autoFillAddresses = false;
        /** @var CCart $cart*/
        $cart = \Monkey::app()->repoFactory->create('Cart')->currentCart();
        if (!is_null($cart->billingAddress) && !is_null($cart->shipmentAddress)) return true;
        else if(is_null($cart->billingAddress) && is_null($cart->shipmentAddress) && !$autoFillAddresses) return false;
        else if(is_null($cart->billingAddress) && is_null($cart->shipmentAddress) && $autoFillAddresses) {
            $billingAddress = $this->app->getUser()->userAddress->findOneByKeys(['isDefault'=>'1','isBilling'=>1]);
            if($billingAddress instanceof CUserAddress) {
                \Monkey::app()->repoFactory->create('Cart')->setBillingAddress($billingAddress,$cart);
            } else return false;
            $shippingAddress = $this->app->getUser()->userAddress->findOneByKeys(array('isDefault'=>'1','isBilling'=>0));
            if($shippingAddress instanceof CUserAddress) {
                \Monkey::app()->repoFactory->create('Cart')->setShipmentAddress($shippingAddress,$cart);
            } else \Monkey::app()->repoFactory->create('Cart')->setShipmentAddress($billingAddress,$cart);
            return true;
        } else if(!is_null($cart->billingAddress) && is_null($cart->shipmentAddress)) {
            \Monkey::app()->repoFactory->create('Cart')->setShipmentAddress($cart->billingAddress, $cart);
            return true;
        } else return false;
    }

    /**
     * True if the checkout step is valid false otherwise
     * @return bool
     */
    protected function checkout()
    {
        return true;
    }

    /**
     * True if the payment step is valid false otherwise
     * @return bool
     */
    protected function payment()
    {
        if($order = \Monkey::app()->repoFactory->create('Cart')->lastOrder()){
            if($order instanceof COrder){
                return true;
            }
        }
        return false;
    }

    /**
     * True if the thanks step is valid false otherwise
     * @return bool
     */
    protected function thanks()
    {
        return true;
    }
}