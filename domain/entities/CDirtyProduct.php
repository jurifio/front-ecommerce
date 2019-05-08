<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\domain\entities\CProduct;
/**
 * Class CDirtyProduct
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
 * @property CProduct $product
 * @property CObjectCollection $dirtySku
 *
 *
 */
class CDirtyProduct extends AEntity
{
    protected $entityTable = 'DirtyProduct';
    protected $primaryKeys = array('id');
	protected $isCacheable = false;

    /**
     * @return int|null
     */
    public function getDirtyPrice()
    {
        if(!is_null($this->price) && $this->price > 0) {
            return $this->price;
        } else {
            $prezzo = 0;
            foreach ($this->dirtySku as $dirtySku) {
                if($dirtySku->price > $prezzo) {
                    $prezzo = $dirtySku->price;
                }
            }
            return $prezzo == 0 ? null : $prezzo;
        }
    }

    /**
     * @return int|null
     */
    public function getDirtySalePrice()
    {
        if(!is_null($this->salePrice) && $this->salePrice > 0) {
            return $this->salePrice;
        } else {
            $prezzo = 0;
            foreach ($this->dirtySku as $dirtySku) {
                if($dirtySku->salePrice > $prezzo) {
                    $prezzo = $dirtySku->salePrice;
                }
            }
            return $prezzo == 0 ? null : $prezzo;
        }
    }

    /**
     * @return int|null
     */
    public function getDirtyValue()
    {
        if(!is_null($this->value) && $this->value > 0) {
            return $this->value;
        } else {
            $prezzo = 0;
            foreach ($this->dirtySku as $dirtySku) {
                if($dirtySku->value > $prezzo) {
                    $prezzo = $dirtySku->value;
                }
            }
            return $prezzo == 0 ? null : $prezzo;
        }
    }


}