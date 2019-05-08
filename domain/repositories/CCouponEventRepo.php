<?php


namespace bamboo\domain\repositories;

use bamboo\core\base\CSerialNumber;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\application\AApplication;
use bamboo\core\db\pandaorm\entities\CEntityManager;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CCouponEventRepo
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
class CCouponEventRepo extends ARepo
{
    /**
     * @param $eventName
     * @return \bamboo\core\db\pandaorm\entities\AEntity|\bamboo\core\db\pandaorm\entities\IEntity|null
     */
    public function getCouponFromEvent($eventName)
    {
        if (!is_null($couponEvent = $this->findOneBySql("SELECT id FROM CouponEvent WHERE name=? AND current_timestamp BETWEEN startDate AND endDate", [$eventName]))) {

            $couponRepo = \Monkey::app()->repoFactory->create("Coupon");
            $coupon = $couponRepo->em()->getEmptyEntity();

            $serial = new CSerialNumber();
            $serial->generate();

            $today = new \DateTime();
            $coupon->issueDate = $today->format('Y-m-d');

            $today = $today->add(new \DateInterval($couponEvent->couponType->validity));
            $endDate = STimeToolbox::GetDateTime($couponEvent->endDate);

            $coupon->validThru = $today->getTimestamp() > $endDate->getTimestamp() ? STimeToolbox::DbFormattedDateTime($today) : STimeToolbox::DbFormattedDateTime($endDate);

            $coupon->code = $serial->__toString();
            $coupon->amount = $couponEvent->couponType->amount;
            $coupon->couponTypeId = $couponEvent->couponType->id;
            $coupon->couponEventId = $couponEvent->id;

            if ($this->app->getUser()->getId() != 0) {
                $coupon->userId = $this->app->getUser()->getId();
            }

            $coupon->smartInsert();

            $couponEvent->click++;
            $couponEvent->update();
            return $coupon;


        }
        return null;
    }
}