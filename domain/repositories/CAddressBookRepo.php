<?php


namespace bamboo\domain\repositories;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOrderLineException;
use bamboo\core\exceptions\RedPandaException;
use bamboo\domain\entities\CAddressBook;
use bamboo\domain\entities\COrderLine;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\COrderLineStatus;
use bamboo\domain\entities\CUserAddress;

/**
 * Class CAddressBookRepo
 * @package bamboo\domain\repositories
 */
class CAddressBookRepo extends ARepo
{

    /**
     * Fetch Config and retur the main hub AddressBook Object
     * @return CAddressBook
     */
    public function getMainHubAddressBook()
    {
        return $this->findOne([$this->app->cfg()->fetch('general','main-shop-address')]);
    }

    /**
     * Cerca l'indirizzo all'interno di AddressBook o lo inserisce,
     * restituisce l'oggetto AddressBook
     * @param CUserAddress $userAddress
     * @return CAddressBook
     */
    public function findOrInsertUserAddress(CUserAddress $userAddress)
    {
        $addressBook = $this->userAddressToAddressBookObject($userAddress);
        $existing = $this->findOneBy(['checksum'=>$addressBook->checksum]);
        if($existing) return $existing;
        else {
            $addressBook->id = $addressBook->insert();
            return $this->findOne($addressBook->getIds());
        }
    }

    /**
     * Costruisce un CAddressBook da un CUserAddress
     * @param CUserAddress $userAddress
     * @return CAddressBook
     */
    public function userAddressToAddressBookObject(CUserAddress $userAddress) {
        /** @var CAddressBook $addressBook */
        $addressBook = $this->getEmptyEntity();
        $addressBook->subject = $userAddress->name.' '.$userAddress->surname;
        $addressBook->address = $userAddress->address;
        $addressBook->extra = $userAddress->extra;
        $addressBook->city = $userAddress->city;
        $addressBook->countryId = $userAddress->countryId;
        $addressBook->province = $userAddress->province;
        $addressBook->postcode = $userAddress->postcode;
        $addressBook->phone = $userAddress->phone;
        $addressBook->setChecksum();

        return $addressBook;
    }
}