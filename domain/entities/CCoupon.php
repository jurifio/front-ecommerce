<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CCoupon
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
 * @property CCouponType $couponType
 */
class CCoupon extends AEntity
{
    protected $entityTable = 'Coupon';
    protected $primaryKeys = ['id'];

    /**
     * @param CCart $cart
     * @return float
     */
    public function getValueForCart(CCart $cart)
    {
        if (!$this->isValidForCart($cart)) return 0.00;

        if ($this->amountType == 'F') return round(-1 * $this->amount,2);

        $totalDiscount = 0;
        foreach ($cart->cartLine as $cartLine) {
            /** @var CCartLine $cartLine */
            $totalDiscount += $this->getValueForCartLine($cartLine);
        }
        return round($totalDiscount,2);
    }

    /**
     * @param CCartLine $cartLine
     * @return float
     * @throws BambooLogicException
     */
    public function getValueForCartLine(CCartLine $cartLine)
    {
        if (!$this->isValidForCartLine($cartLine)) return 0.00;

        switch ($this->amountType) {
            case 'F':
                $allowedTotal = 0;
                foreach ($cartLine->cart->cartLine as $cartLine2) {
                    /** @var CCartLine $cartLine2 */
                    if ($this->isValidForCartLine($cartLine2)) $allowedTotal += $cartLine2->getLineGrossTotal();
                }
                $res = ( $this->amount * $cartLine->getLineGrossTotal()) / $allowedTotal * -1;
                break;
            // TODO return -1 * $this->amount;
            case 'P':
                $res =  -1 * $cartLine->getLineGrossTotal() * $this->amount * 0.01;
                break;
            case 'G':
                $res = -1 * $cartLine->getLineFullPrice() * $this->amount * 0.01;
                break;
            default:
                throw new BambooLogicException('Coupon type not supported %s', [$this->amountType]);
        }

        return round($res,2);
    }

    /**
     * @param CCart $cart
     * @return bool
     */
    public function isValidForCart(CCart $cart)
    {
        return $this->isValidNow() && $this->isValidForCartTotal($cart) && $this->isValidForCartTags($cart);
    }

    /**
     * @return bool
     */
    public function isValidNow()
    {
        return (new \DateTime())->diff(STimeToolbox::GetDateTimeFromDBValue($this->validThru))->invert >= 0;

    }

    /**
     * @param CCart $cart
     * @return bool
     */
    protected function isValidForCartTotal(CCart $cart)
    {
        return $this->couponType->validForCartTotal <= $cart->getGrossTotal();
    }

    /**
     * @param CCart $cart
     * @return bool
     */
    protected function isValidForCartTags(CCart $cart)
    {
        if ($this->couponType->couponTypeHasTag->isEmpty()) return true;
        foreach ($cart->cartLine as $cartLine) {
            /** @var CCartLine $cartLine */
            if ($this->isValidForCartLine($cartLine)) return true;

        }

        return false;
    }

    /**
     * @param CCartLine $cartLine
     * @return bool
     */
    public function isValidForCartLine(CCartLine $cartLine)
    {
        foreach ($cartLine->productPublicSku->product->tag as $tag) {
            /** @var CTag $tag */
            if ($this->isValidForTag($tag)) return true;
        }
        return false;
    }

    /**
     * @param CTag $tag
     * @return bool
     */
    protected function isValidForTag(CTag $tag)
    {
        if ($this->couponType->couponTypeHasTag->isEmpty()) return true;
        foreach ($this->couponType->couponTypeHasTag as $couponTypeHasTag) {
            /** @var CCouponTypeHasTag $couponTypeHasTag */
            if ($tag->id == $couponTypeHasTag->tagId) return true;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getAmountType()
    {
        return $this->couponType->amountType;
    }
}