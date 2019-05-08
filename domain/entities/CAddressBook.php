<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CAddressBook
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 *
 * @property CCountry $country
 */
class CAddressBook extends AEntity
{
    protected $entityTable = 'AddressBook';
    protected $primaryKeys = ['id'];

    /**
     * @param null $checksum
     */
    public function setChecksum($checksum = null) {
        if(!isset($this->fields['checksum']) && $checksum == null) {
            $this->fields['checksum'] = $this->calculateChecksum();
        } else {
            $this->fields['checksum'] = $checksum;
        }
    }

    /**
     * @return string
     */
    public function calculateChecksum() {
        return md5($this->subject.
            $this->address ?? ''.
            $this->extra ?? ''.
            $this->city ?? ''.
            $this->countryId.
            $this->postcode.
            $this->phone ?? ''.
            $this->cellphone ?? ''.
            $this->vatNumber ?? ''
        );
    }

    /**
     * @param bool $reCalculate
     * @return mixed
     */
    public function getChecksum($reCalculate = true) {
        if($reCalculate) $this->setChecksum($this->calculateChecksum());
        return $this->fields['checksum'];
    }

    /**
     * @return int|mixed
     */
    public function insert()
    {
        if(($this->fields['checksum'] ?? '') != $this->calculateChecksum()) {
            $this->fields['checksum'] = $this->calculateChecksum();
        };
        return parent::insert();
    }
}