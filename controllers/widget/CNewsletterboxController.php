<?php

namespace bamboo\controllers\widget;

use bamboo\core\exceptions\RedPandaException;
use bamboo\core\io\CValidator;
use bamboo\domain\entities\CCoupon;
use bamboo\domain\entities\CCouponEvent;
use bamboo\domain\repositories\CCouponRepo;
use bamboo\domain\repositories\CEmailRepo;
use bamboo\domain\repositories\CNewsletterUserRepo;
use bamboo\site\controllers\widget\CGenericWidgetController;

/**
 * Class CCartSummaryController
 * @package bamboo\app\controllers\widget
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CNewsletterboxController extends CGenericWidgetController
{
    public function post() {
        $v = new CValidator();
        try{
            $data = trim($this->app->router->request()->getRequestData("data"));
            $name = $this->app->router->request()->getRequestData("name");
            $surname = $this->app->router->request()->getRequestData("surname");
            $sex = $this->app->router->request()->getRequestData("sex");
            $typeModal = $this->app->router->request()->getRequestData("typeModal");
            $fixedPageId = $this->app->router->request()->getRequestData('fixedPageId');


            if($v->validate($data,'email') != 'ok') throw new RedPandaException('Email not valid');

            /** @var CNewsletterUserRepo $newsletterUserRepo */
            $newsletterUserRepo = \Monkey::app()->repoFactory->create('NewsletterUser');

            $fpId = (!$fixedPageId) ? null : $fixedPageId;
            $sended = $newsletterUserRepo->insertNewEmail($data, null, null, $name, $surname, $sex, $fpId);

            if($sended == 'new'){
                if(!$typeModal) {
                    /** @var CCouponRepo $couponRepo */
                    $couponRepo = \Monkey::app()->repoFactory->create('Coupon');

                    /** @var CCoupon $coupon */
                    $coupon = $couponRepo->createCouponFromType(1);
                    $couponCode = $coupon->code;

                } else if ($typeModal === 'showAll'){
                    $couponEventId = $this->app->router->request()->getRequestData('couponEventId');

                    /** @var CCouponEvent $couponEvent */
                    $couponEvent = \Monkey::app()->repoFactory->create('CouponEvent')->findOneBy(['id'=>$couponEventId]);
                    $couponCode = $couponEvent->name;
                }

                /** @var CEmailRepo $mailRepo */
                $mailRepo = \Monkey::app()->repoFactory->create('Email');
                $mailRepo->newPackagedTemplateMail('onNewsletterSendCoupon', 'no-reply@pickyshop.com', [$data], [], [], ['couponCode' => $couponCode],'MailGun',null);

            }

            $this->response->setContent(1);
            return $this->response;
        } catch(\Throwable $e) {
            $this->response->setCode(500);
            return $this->response;
        }
    }
}