<?php

namespace bamboo\controllers\widget;

use bamboo\core\exceptions\RedPandaException;
use bamboo\core\router\ANodeController;
use bamboo\core\router\CNodeView;
use bamboo\core\router\CInternalResponse;
use bamboo\core\exceptions\RedPandaThemeException;
use bamboo\domain\entities\CCart;
use bamboo\domain\entities\CUser;
use \bamboo\domain\entities\CUserAddress;
use bamboo\domain\repositories\CCartRepo;

/**
 * Class CAddressFormController
 * @package bamboo\app\controllers
 */
class CAddressFormController extends ANodeController
{
    /**
     * @return CInternalResponse
     * @throws RedPandaThemeException
     */
    public function get()
    {
        $this->view = new CNodeView($this->request,$this->config['template']['fullpath']);


        $this->fetchData();

        $defaultAddress = [];
        /** @var CCart $cart */
        $cart = \Monkey::app()->repoFactory->create('Cart')->currentCart();

        if (is_null($cart->billingAddress)) {
            foreach ($this->dataBag->multi as $address) {
                if ($address->isDefault) {
                    $defaultAddress[] = $address;
                }
            }
            if (count($defaultAddress) == 0) {
                foreach ($this->dataBag->multi as $lastAddress) {
                    if ($lastAddress->lastUsed) {
                        $defaultAddress[] = $lastAddress;
                    }
                }
            }
        } else {
            $defaultAddress[] = $cart->billingAddress;
            if (!empty($cart->shipmentAddress)) {
	            $defaultAddress[] = $cart->shipmentAddress;
            }
        }

        $this->dataBag->selectedAddress = new \ArrayIterator($defaultAddress);

        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        $this->view->pass('cart', $cart);

        return $this->show();
    }

    /**
     * @return CInternalResponse
     */
    public function post()
    {
        $filters = $this->request->getFilters();

        return $this->response;
    }

    /**
     * @return CInternalResponse
     * @throws RedPandaException
     */
    public function put()
    {
        $data  = $this->app->router->request()->getRequestData();
        $hasInvoice=$data['hasInvoice'];
        $countryId=$data['countryId'];
        $filters = $this->request->getFilters();

        /** @var CCartRepo $cartRepo */
        $cartRepo = \Monkey::app()->repoFactory->create('Cart');

        unset($filters['primaryAddress']['userAddress.hasInvoice']);

        $primaryAddress = $this->parseUserAddress($filters['primaryAddress']);

        if($filters['secondaryAddress'] == null || empty($filters['secondaryAddress'])) {
            $cartRepo->setBillingAddress($primaryAddress);
            $cartRepo->setHasInvoice($hasInvoice,$countryId);
            $cartRepo->setShipmentAddress($primaryAddress);
        } else {
            $cartRepo->setShipmentAddress($primaryAddress);
            $cartRepo->setBillingAddress($this->parseUserAddress($filters['secondaryAddress']));
            $cartRepo->setHasInvoice($hasInvoice,$countryId);
        }

        return $this->response;
    }

    /**
     * @param $rawAddress
     * @return CUserAddress
     */
    protected function parseUserAddress($rawAddress)
    {
        $userAddressRepo = \Monkey::app()->repoFactory->create('UserAddress');
        /** @var CUserAddress $userAddress*/
        $userAddress = $userAddressRepo->getEmptyEntity();
        foreach ($rawAddress as $key=>$val) {
            $userAddress->__set((explode('.',$key)[1]),$val);
        }

        $insert = false;
        if(isset($userAddress->id) && !empty($userAddress->id)) {
            $oldUserAddress = \Monkey::app()->repoFactory->create('UserAddress')->findOneBy(['id'=>$userAddress->id]);
            foreach ($userAddress->toArray() as $key=>$val) {
                if(levenshtein($val, $oldUserAddress->$key) < 3 ) {
                    $oldUserAddress->$key = strip_tags($val);
                } else {
                    $insert = true;
                    unset($userAddress->id);
                    break;
                }
            }
        } else {
            unset($userAddress->id);
            $insert = true;
        }
        if($insert) {
            $userAddress->userId = \Monkey::app()->getUser()->id;
            $userAddress->smartInsert();
        } else {
            $oldUserAddress->update();
            $userAddress = $oldUserAddress;
        }

        return $userAddress;
    }

    /**
     * @return CInternalResponse
     */
    public function delete() {return $this->get();}
}