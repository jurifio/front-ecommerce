<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductDetail
 * @package bamboo\app\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 *
 * @property CObjectCollection $productDetailTranslation
 */
class CProductDetail extends AEntity
{
    protected $entityTable = 'ProductDetail';
    protected $primaryKeys = ['id'];

    /**
     * @param bool $clean
     * @return string
     */
    public function getLocalizedDetail($clean = true) {
        if(!is_null($this->productDetailTranslation->getFirst())) $transl = $this->productDetailTranslation->getFirst()->name;
        else $transl = $this->em()->findChild('productDetailTranslation',$this,true)->getFirst()->name;

        if($clean) return rtrim($transl,' !');
        return $transl;
    }
}