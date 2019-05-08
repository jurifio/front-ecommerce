<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CUserAddress
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
 * @property CCountry $country
 */
class CUserAddress extends AEntity
{
    protected $entityTable = 'UserAddress';
    protected $primaryKeys = ['id','userId'];
	protected $isCacheable = false;
    /**
     * @return int
     */
    public function checkSum()
    {
        $checksum = $this->id.$this->name.$this->surname.$this->company.$this->address.$this->extra.$this->province.$this->city.$this->postcode.$this->countryId.$this->phone;
        return crc32($checksum);
    }

    /**
     * @return string
     */
    public function froze() {
        $r = [];
        foreach($this->ownersFields as $field){
            if(!isset($this->$field) || is_null($this->$field)) {
                $r[$field] = null;
            } else {
                $r[$field] = $this->$field;
            }
        }
        return json_encode($r);
    }
}