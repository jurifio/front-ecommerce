<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\ANodeController;

/**
 * Class CCouponBoxController
 * @package bamboo\controllers\widget
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
class CCouponBoxController extends ANodeController
{
    public function post() {return $this->get();}

    public function put()
    {
        $coup = trim($this->request->getFilter('coupon_name'));
        $repo = \Monkey::app()->repoFactory->create('Coupon');
        $coupon = $repo->findOneBy(['valid'=>1,'code'=>$coup]);

        if($coupon == false) {
            $coupon = \Monkey::app()->repoFactory->create('CouponEvent')->getCouponFromEvent($coup);
        }

        if ($coupon != false) {
            $cart = \Monkey::app()->repoFactory->create('Cart')->currentCart();
            if ($coupon->couponType->validForCartTotal > 0){
                if(\Monkey::app()->repoFactory->create('Cart')->calculateGrossTotal($cart) > $coupon->couponType->validForCartTotal) {
                    $cart->couponId = $coupon->id;
                } else {
                    $this->response->raiseError('500', 300);
                    return $this->response;
                }
            } else {
                $cart->couponId = $coupon->id;
            }
            try {
                $cart->couponId = $coupon->id;
                $cart->update();
            } catch (\Throwable $e) {
                $this->app->router->response()->raiseUnauthorized();
            }
            //$this->app->dbAdapter->update('Order',['couponId'=>$coupon->id],['id'=>$cart->id]);
        } else {
            $this->response->raiseError('500',200);
            return $this->response;
        }

        return $this->get();
    }

    public function delete() {
        \Monkey::app()->repoFactory->create('Cart')->removeCoupon();
        return $this->get();
    }
}