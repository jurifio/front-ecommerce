<?php


namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\domain\entities\CAddressBook;
use bamboo\domain\entities\COrder;
use bamboo\domain\entities\CUserAddress;
use bamboo\ecommerce\business\CForm;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\io\CValidator;
use bamboo\core\exceptions\RedPandaException;

/**
 * Class CUserAddressRepo
 * @package bamboo\app\domain\repositories
 */
class CUserAddressRepo extends ARepo
{
    public function insertAddress(CForm $form)
    {
        $user = $this->app->getUser();
        $entity = $this->em()->getEmptyEntity();

        foreach($form->getValues() as $key => $value) {
            if ($key == 'widgetAddress') {
                continue;
            }
            $entity->{explode('_',$key)[1]} = $value;
        }
        $entity->userId = $user->getId();

        /**
         * Form validation
         */
        $v = new CValidator();

        $validationErrors = [];
        $validationErrors['name'] = $v->validate($entity->name, 'name');
        $validationErrors['surname'] = $v->validate($entity->surname, 'name');
        $validationErrors['company'] = (!empty($entity->company)) ? $v->validate($entity->company, 'name') : 'ok';
        $validationErrors['phone'] = $v->validate($entity->phone, 'phone');
        $validationErrors['address'] = $v->validate($entity->address, 'text');
        $validationErrors['extra'] = (!empty($entity->extra)) ? $v->validate($entity->extra, 'text') : 'ok';
        $validationErrors['postcode'] = $v->validate($entity->postcode, 'postcode');
        $validationErrors['city'] = $v->validate($entity->city, 'text');

        $errors = [];
        foreach ($validationErrors as $key => $errorValue) {
            if ($errorValue != 'ok') {
                $errors[$key] = $errorValue;
            }
        }
        $form->setErrors($errors);

        if (!$form->hasErrors()) {
            try {
	            foreach($user->userAddress as $oldAddress){
		            if($oldAddress->isBilling == $entity->isBilling) {
			            $oldAddress->lastUsed = 0;
			            $oldAddress->update();
		            }
	            }
	            $entity->id = $entity->insert();
	            $form->insertId = $entity->id;
                if ($entity->isBilling == 1) {
                    \Monkey::app()->repoFactory->create('Cart')->setBillingAddress($entity->id);

                } else if ($entity->isBilling == 0) {
                    \Monkey::app()->repoFactory->create('Cart')->setShipmentAddress($entity->id);
                } else {
                    throw new RedPandaException('Invalid address type');
                }

            } catch (\Throwable $e) {
                throw new \Exception($e->getMessage(),$e->getCode(),$e);
            }
        }

        return $form;
    }

    /**
     * @return bool|mixed
     */
    public function listByUser()
    {
        $user = $this->app->getUser();
        try{

            $address = $this->findBy(['userId' => $user->id],"","ORDER BY isBilling DESC");
            return $address;
        } catch(\Throwable $e) {
            return false;
        }
    }

    public function listByCartPrefill()
    {
        $cartBilling = $this->fetchEntityByCurrentBilling();
        $res = [];
        if(!is_null($cartBilling)) {
            $res[] = $cartBilling;
            $cartShipping = $this->fetchEntityByCurrentShipping();
            if(!is_null($cartShipping) && $cartBilling->id != $cartShipping->id) $res[] = $cartShipping;
        } else {
            /** @var COrder $lastOrder */
            $lastOrder = \Monkey::app()->repoFactory->create('Order')->lastOrder();
            if(!is_null($lastOrder) && !is_null($lastOrder->billingAddress)) {
                $res[] = $lastOrder->billingAddress;
                if(!is_null($lastOrder->shipmentAddress) && $lastOrder->shipmentAddress->id != $lastOrder->billingAddress->id) $res[] = $lastOrder->shipmentAddress;
            }
        }
        return $res;
    }

    /**
     * @param $id
     * @param $type
     */
    public function setAddressAsLastUsed($id, $type)
    {
        $user = $this->app->getUser();
        $billing = ($type == 'b') ? 1 : 0;

	    foreach($user->userAddress as $oldAddress){
		    if($oldAddress->isBilling == $billing && $oldAddress->isDefault == 0) {
			    $oldAddress->lastUsed = 0;
			    $oldAddress->update();
		    }
		    if($oldAddress->id == $id){
			    $oldAddress->lastUsed = 1;
			    $oldAddress->isBilling = 1;
			    $oldAddress->update();
		    }
	    }
        //$this->em()->query('UPDATE UserAddress SET lastUsed = 0 WHERE isDefault = 0 AND userId = :userId AND isBilling = :isBilling',array('userId'=>$user->getId(),'isBilling'=>$billing));

        //$this->em()->query('UPDATE UserAddress SET lastUsed = 1, isBilling = :isBilling WHERE id=:id',['isBilling'=>$billing,'id'=>$id]);
    }

    /**
     * @return null|AEntity
     */
    public function fetchEntityByCurrentShipping()
    {
        try{
            return \Monkey::app()->repoFactory->create('Cart')->currentCart()->shipmentAddress;
        } catch(\Throwable $e) {
            return null;
        }
    }

	/**
	 * @return mixed|null
	 */
    public function fetchEntityByCurrentBilling()
    {
        try{
            return \Monkey::app()->repoFactory->create('Cart')->currentCart()->billingAddress;
        } catch(\Throwable $e) {
            return null;
        }
    }


    /**
     * @param $userId
     * @param $name
     * @param $surname
     * @param $address
     * @param $province
     * @param $city
     * @param $postcode
     * @param $countryId
     * @param $phone
     * @param $iban
     * @param $fiscalCode
     * @param $isBilling
     * @param bool $addressBook
     * @return array|AEntity|CAddressBook|CUserAddress|null
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    public function insertUserAddressFromData($userId, $name, $surname, $address, $province, $city, $postcode, $countryId, $phone, $iban, $fiscalCode, $isBilling, bool $addressBook){

        $extUser = $this->findOneBy(['userId'=>$userId]);

        if(is_null($extUser)){

            /** @var CUserAddress $userAddress */
            $userAddress = $this->getEmptyEntity();
            $userAddress->userId = $userId;
            $userAddress->isBilling = $isBilling;
            $userAddress->isDefault = 0;
            $userAddress->name = $name;
            $userAddress->surname = $surname;
            $userAddress->address = $address;
            $userAddress->province = $province;
            $userAddress->city = $city;
            $userAddress->postcode = $postcode;
            $userAddress->countryId = $countryId;
            $userAddress->phone = $phone;
            $userAddress->fiscalCode = $fiscalCode;
            $userAddress->smartInsert();

            /** @var CAddressBookRepo $usAddBookRepo */
            $usAddBookRepo = \Monkey::app()->repoFactory->create('AddressBook');

            if($addressBook) {
                /** @var CAddressBook $addressBook */
                $addressBook = $usAddBookRepo->userAddressToAddressBookObject($userAddress);

                $addressBook->iban = $iban;
                $addressBook->smartInsert();

                $res = $addressBook;
            } else {
                $res = $userAddress;
            }

        } else {

            $extUser->name = $name;
            $extUser->surname = $surname;
            $extUser->address = $address;
            $extUser->province = $province;
            $extUser->city = $city;
            $extUser->postcode = $postcode;
            $extUser->countryId = $countryId;
            $extUser->phone = $phone;
            $extUser->fiscalCode = $fiscalCode;
            $extUser->update();

            $res = $extUser;
        }

        return $res;
    }
}