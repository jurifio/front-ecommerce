<?php

namespace bamboo\domain\repositories;

use bamboo\core\base\CSerialNumber;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CCouponType;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CCouponRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CCouponRepo extends ARepo
{
    /**
     * @param $eventName
     * @return \bamboo\core\db\pandaorm\entities\AEntity|\bamboo\core\db\pandaorm\entities\IEntity|null
     */
    public function createCouponFromEvent($eventName)
    {
        return \Monkey::app()->repoFactory->create('CouponEvent')->getCouponFromEvent($eventName);
    }

    /**
     * @param $couponType
     * @param null $userId
     * @return \bamboo\core\db\pandaorm\entities\AEntity
     */
    public function createCouponFromType($couponType, $userId = null) {
        if(!$couponType instanceof CCouponType) {
            $couponType = \Monkey::app()->repoFactory->create('CouponType')->findOneByStringId($couponType);
        }

        $coupon = $this->getEmptyEntity();

        $serial = new CSerialNumber();
        $serial->generate();

        $today = new \DateTime();
        $coupon->issueDate = $today->format('Y-m-d');

        $coupon->validThru = STimeToolbox::DbFormattedDateTime($today->add(new \DateInterval($couponType->validity)));

        $coupon->code = $serial->__toString();
        $coupon->amount = $couponType->amount;
        $coupon->couponTypeId = $couponType->id;

        if ($userId != 0) {
            $coupon->userId = $userId;
        }

        $coupon->smartInsert();
        return $coupon;
    }
}