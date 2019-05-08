<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CFixedPagePopup
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 11/04/2019
 * @since 1.0
 *
 * @property CFixedPage $fixedPage
 * @property CCouponEvent $couponEvent
 *
 *
 *
 */
class CFixedPagePopup extends AEntity
{
    protected $entityTable = 'FixedPagePopup';
    protected $primaryKeys = array('id');

    /**
     * @return bool
     */
    public function haveCoupon(): bool
    {
        if(!is_null($this->couponEvent)) return true;

        return false;
    }

    /**
     * @return bool
     */
    public function haveImage(){
        if(!is_null($this->img)) return true;

        return false;
    }
}