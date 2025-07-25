<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCartLine
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
 * @property CProductPublicSku $productPublicSku
 * @property CCart $cart
 */
class CCartLine extends AEntity
{
    protected $entityTable = 'CartLine';
    protected $primaryKeys = ['id','cartId'];

    /**
     * @return float
     */
    public function getLineGrossTotal() {
        return $this->productPublicSku->getActivePrice();
    }

    /**
     * @return float
     */
    public function getLineFullPrice()
    {
        return $this->productPublicSku->price;
    }

    /**
     * @return float
     */
    public function getCouponDiscount()
    {
        return $this->cart->coupon ? $this->cart->coupon->getValueForCartLine($this) : 0;
    }
}