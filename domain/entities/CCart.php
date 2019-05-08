<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\utils\price\SPriceToolbox;

/**
 * Class CCart
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
 * @property CObjectCollection $cartLine
 * @property CCoupon $coupon
 * @property COrderPaymentMethod $orderPaymentMethod
 * @property CUser $user
 * @property CCartType $cartType
 * @property CUserAddress $billingAddress
 * @property CUserAddress $shipmentAddress
 */
class CCart extends AEntity
{
    protected $entityTable = 'Cart';
    protected $primaryKeys = ['id'];

    /**
     * @return float
     */
    public function getEloyVoucerModifier()
    {
        if(is_null($this->eloyVoucher)) return 0.00;
        else return  $this->eloyVoucher->getValueForCart($this);
    }

    /**
     * @return bool|float
     */
    public function getGrossTotal()
    {
        /** Totale prodotti * GrossTotal */
        try {
            $grossTotal = 0;
            foreach ($this->cartLine as $line) {
                /** @var CCartLine $line */
                $grossTotal += $line->getLineGrossTotal();
            }
            return $grossTotal;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return bool|float
     */
    public function getFullPriceTotal()
    {
        /** Totale prodotti * GrossTotal */
        try {
            $grossTotal = 0;
            foreach ($this->cartLine as $line) {
                /** @var CCartLine $line */
                $grossTotal += $line->getLineFullPrice();
            }
            return $grossTotal;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param null $grossTotal
     * @param null $country
     * @return float
     */
    public function getShippingModifier($grossTotal = null, $country = null)
    {
        if(!is_null($this->coupon) &&
            $this->coupon->isValidForCart($this) &&
            $this->coupon->couponType->hasFreeShipping === 1)
                return 0;

        if (is_null($grossTotal)) {
            $grossTotal = $this->getGrossTotal();
        }

        if (!is_null($country) && !$country instanceof IEntity) {
            $country = \Monkey::app()->repoFactory->create('Country')->findOne([$country]);
        } elseif (!is_null($this->shipmentAddress)) {
            $country = $this->shipmentAddress->country;
        } elseif (!is_null($this->billingAddress)) {
            $country = $this->billingAddress->country;
        } elseif (!is_null($this->user) && (
            $address = $this->user->userAddress->findOneByKeys(['isDefault' => 1, 'isBilling' => 0]) ||
                $address = $this->user->userAddress->findOneByKeys(['isDefault' => 1, 'isBilling' => 1]))
        ) {
            $country = $address->country;
        } elseif (\Monkey::app()->getUser()->id != 0 && (
            $address = \Monkey::app()->getUser()->userAddress->findOneByKeys(['isDefault' => 1, 'isBilling' => 0]) ||
                $address = \Monkey::app()->getUser()->userAddress->findOneByKeys(['isDefault' => 1, 'isBilling' => 1]))
        ) {
            $country = $address->country;
        } else {
            /** TODO spostare la configurazione del paese da un altra parte */
            $country = \Monkey::app()->repoFactory->create('Country')->findOneBy(['ISO3' => 'ITA']);
        }

        if (($grossTotal ?? $this->getGrossTotal()) < $country->freeShippingLimit) return $country->shippingCost;
        else return (double)0;
    }

    /**
     * @param null $grossTotal
     * @return bool|float|int|mixed|null
     */
    public function getPaymentModifier($grossTotal = null)
    {
        /** Metodo di pagamento */
        if (!is_null($this->orderPaymentMethod) && ($this->orderPaymentMethod->modifier) != null) {
            if (strstr($this->orderPaymentMethod->modifier, '%')) {
                $mod = trim($this->orderPaymentMethod->modifier,'%');
                return ($grossTotal ?? $this->getGrossTotal()) * $mod * 0.01;
            } else {
                return $this->orderPaymentMethod->modifier;
            }
        } else return 0;
    }

    /**
     * @return float
     */
    public function getCouponModifier()
    {
        if(is_null($this->coupon)) return 0;
        else return $this->coupon->getValueForCart($this);
    }

    /**
     * @return bool|float
     */
    public function getNetTotal() {
        $grossTotal = $this->getGrossTotal();
        return $grossTotal +
                $this->getCouponModifier() +
                $this->getPaymentModifier($grossTotal) +
                $this->getEloyVoucerModifier() +
                $this->getShippingModifier($grossTotal);
    }

    /**
     * @return int
     */
    public function getVat()
    {
        try {
            if (is_null($this->billingAddress)) return 0;
            $vatPercent  = $this->billingAddress->country->vat;
            return SPriceToolbox::vatFromGross($this->getNetTotal(), $vatPercent, true);
        } catch (\Throwable $e) {
            \Monkey::app()->applicationWarning('Cart Manager', 'Error Calculating Vat', 'Cart: ' . $this->id . ' netTotal:' . $this->getNetTotal(), $e);
        }
        return 0;
    }
}