<?php

namespace bamboo\events\listeners;

use bamboo\core\base\CSerialNumber;
use bamboo\core\events\AEvent;
use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;

/**
 * Class CCreateCoupon
 * @package bamboo\app\events\listeners
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 10/12/2015
 * @since 1.0
 */
class CCreateCoupon extends AEventListener
{
    public function work($event)
    {
        $this->app = \Monkey::app();
        if(!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');
        if ($this->app->getUser()->isEmailChanged()) {
            return;
        }

        $couponTypeRepo = \Monkey::app()->repoFactory->create("CouponType");
        $couponType = $couponTypeRepo->em()->findOneBy(["name"=>"onActivate"]);

        $couponRepo = \Monkey::app()->repoFactory->create("Coupon");
        $coupon = $couponRepo->em()->getEmptyEntity();

        $serial = new CSerialNumber();
        $serial->generate();

        $today = new \DateTime();
        $interval = $couponType->validity;
        $expire = new \DateInterval($interval);

        $coupon->code = $serial->__toString();
        $coupon->issueDate = $today->format('Y-m-d');

        $today->add($expire);
        $coupon->validThru = $today->format('Y-m-d');
        $coupon->amount = $couponType->amount;
        $coupon->amountType = $couponType->amountType;
        $coupon->couponTypeId = $couponType->id;
        $coupon->userId = $this->app->getUser()->getId();

        $this->app->bubble('couponId', $couponRepo->em()->insert($coupon));
    }
}