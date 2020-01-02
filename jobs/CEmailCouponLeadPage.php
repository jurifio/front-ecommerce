<?php

namespace bamboo\ecommerce\jobs;

use bamboo\core\base\CObjectCollection;
use bamboo\domain\entities\CCoupon;
use bamboo\domain\entities\CFixedPage;
use bamboo\domain\entities\CFixedPagePopup;
use bamboo\domain\entities\CNewsletterUser;
use bamboo\core\jobs\ACronJob;
use bamboo\domain\entities\CUser;
use bamboo\domain\repositories\CCouponRepo;
use bamboo\domain\repositories\CEmailRepo;
use bamboo\domain\repositories\CFixedPageRepo;
use bamboo\domain\repositories\CNewsletterUserRepo;
use bamboo\domain\repositories\CUserRepo;
use bamboo\utils\time\SDateToolbox;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CEmailCouponLeadPage
 * @package bamboo\ecommerce\jobs
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 17/04/2019
 * @since 1.0
 */
class CEmailCouponLeadPage extends ACronJob
{
    /**
     * @param null $args
     * @throws \Exception
     */
    public function run($args = null)
    {

        $this->report('CouponLeadPage', 'init');

        /** @var CFixedPageRepo $fixedPageRepo */
        $fixedPageRepo = \Monkey::app()->repoFactory->create('FixedPage');

        /** @var CNewsletterUserRepo $newsletterUserRepo */
        $newsletterUserRepo = \Monkey::app()->repoFactory->create('NewsletterUser');

        /** @var CCouponRepo $couponRepo */
        $couponRepo = \Monkey::app()->repoFactory->create('Coupon');

        /** @var CUserRepo $userRepo */
        $userRepo = \Monkey::app()->repoFactory->create('User');

        /** @var CEmailRepo $emailRepo */
        $emailRepo = \Monkey::app()->repoFactory->create('Email');

        /** @var CObjectCollection $leadPages */
        $leadPages = $fixedPageRepo->findBy(['fixedPageTypeid' => 3]);


        /** @var CFixedPage $leadPage */
        foreach ($leadPages as $leadPage) {

            if ($leadPage->havePopup()) {
                /** @var CFixedPagePopup $fixedPagePopup */
                $fixedPagePopup = $leadPage->getActivePopup();

                if (!is_null($fixedPagePopup->couponEventId)) {
                    /** @var CObjectCollection $newsletterUsers */
                    $newsletterUsers = $newsletterUserRepo->findBy(['fixedPageId' => $leadPage->id]);

                    /** @var CNewsletterUser $newsletterUser */
                    foreach ($newsletterUsers as $newsletterUser) {

                        /** @var CUser $user */
                        $user = $userRepo->findOneBy(['email' => $newsletterUser->email]);

                        $coupon = null;
                        if (!is_null($user)) {
                            /** @var CCoupon $coupon */
                            $coupon = $couponRepo->findOneBy(['userId' => $user->id, 'valid' => 0, 'couponEventId' => $fixedPagePopup->couponEventId]);
                        }

                        if (is_null($coupon)) {

                            $subscriptionDate = STimeToolbox::GetDateTime($newsletterUser->subscriptionDate);
                            $today = STimeToolbox::GetDateTime()->format('Y-m-d');

                            $name = null;

                            if ($user) {
                                $name = $user->userDetails->name;
                            } else {
                                $name = $newsletterUser->nameNewsletter ?: null;
                            }


                            switch ($today) {
                                case SDateToolbox::removeOrAddDaysFromDate($subscriptionDate, 3, '+'):
                                    $emailRepo->newPackagedTemplateMail(
                                        'reminederleadcoupon',
                                        'no-reply@pickyshop.com',
                                        [$newsletterUser->email],
                                        [],
                                        [],
                                        [
                                            'couponCode' => $fixedPagePopup->couponEvent->name,
                                            'name' => $name,
                                            'remindNumber' => 1
                                        ]
                                    );
                                    break;
                                case SDateToolbox::removeOrAddDaysFromDate($subscriptionDate, 5, '+'):
                                    $emailRepo->newPackagedTemplateMail(
                                        'reminederleadcoupon',
                                        'no-reply@pickyshop.com',
                                        [$newsletterUser->email],
                                        [],
                                        [],
                                        [
                                            'couponCode' => $fixedPagePopup->couponEvent->name,
                                            'name' => $name,
                                            'remindNumber' => 2
                                        ]
                                    );
                                    break;
                                case SDateToolbox::removeOrAddDaysFromDate($subscriptionDate, 7, '+'):
                                    $emailRepo->newPackagedTemplateMail(
                                        'reminederleadcoupon',
                                        'no-reply@pickyshop.com',
                                        [$newsletterUser->email],
                                        [],
                                        [],
                                        [
                                            'couponCode' => $fixedPagePopup->couponEvent->name,
                                            'name' => $name,
                                            'remindNumber' => 3
                                        ]
                                    );
                                    break;
                            }

                            $this->report('CouponLeadPage', 'Mail to: ' . $newsletterUser->email);
                        }
                    }
                }

            }
        }
        $this->report('CouponLeadPage', 'end');
    }

}