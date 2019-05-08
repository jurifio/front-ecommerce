<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\CNodeView;
use bamboo\core\router\ANodeController;

/**
 * Class CGenericWidgetController
 * @package bamboo\app\controllers
 */
class COverlayController extends ANodeController
{
    public function get()
    {
        $this->view = new CNodeView($this->request,$this->config['template']['fullpath']);

        $this->fetchData();

        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);

        /**
         * Specific promo
         */
        $couponEventRepo = \Monkey::app()->repoFactory->create('CouponEvent');

        if ($eventName = $this->app->router->request()->getRequestData('bsevent')) {

            $coupon = \Monkey::app()->repoFactory->create('CouponEvent')->getCouponFromEvent($eventName);

            if ($coupon) {
                $this->view->pass('coupon', $coupon->code);
                $this->view->pass('couponDiscount', $coupon->amount . (($coupon->couponType == 'F') ? '&euro;' : '%'));

                return $this->show();

            }
        }

        $setting = $this->app->cfg()->fetch('miscellaneous', 'newsletterOverly');
        $cookie = \Monkey::app()->router->request()->getCookie('newsletterModalShown');
        if ((is_null($cookie) || $cookie == "false") && (!$setting || $this->app->getUser()->id != 0)) {
            setcookie("newsletterModalShown", "true", time()+365*24*60*60,"","",true,false);
        }


        return $this->show();
    }

    public function post()
    {
        return $this->get();
    }

    public function put()
    {
        return $this->get();
    }

    public function delete()
    {
        return $this->get();
    }
}