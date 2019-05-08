<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\RedPandaOrderLogicException;
use bamboo\core\router\ARootController;
use bamboo\core\router\CInternalRequest;
use bamboo\ecommerce\business\CCheckoutStep;
use bamboo\core\ecommerce\APaymentGateway;

/**
 * Class CPaymentController
 * @package bamboo\app\controllers
 */
class CPaymentController extends ARootController
{
    /**
     * @param CInternalRequest $request
     * @throws \Exception
     */
    public function get(CInternalRequest $request)
    {
        try {
            $step = new CCheckoutStep($this->app);
            $presentStep = $step->fetchPresentStep();
            $ok = $step->validate($presentStep['name']);

            if($ok != $presentStep) {
                $this->app->router->response()->autoRedirectTo($step->fullAddress());
                return "";
            }

            try{
                $order = \Monkey::app()->repoFactory->create('Cart')->cartToOrder();
            } catch(RedPandaOrderLogicException $e){
                $this->app->applicationError('PaymentController','Error Ordering','Error converting order to cart',$e);
                $step->prev();
                $this->app->router->response()->autoRedirectTo($step->fullAddress().'?e='.$e->getCode());
                return "";
            }

            if(!$order) {
                $step->prev();
                $this->app->router->response()->autoRedirectTo($step->fullAddress());
                return "";
            }

            $this->app->eventManager->triggerEvent("newOrder",['orderId'=>$order->id]);
            $gateway = $this->app->orderManager->getPaymentGateway($order);

            /** @var APaymentGateway $gateway */
            if($url = $gateway->getUrl($order)) {
                $this->app->router->response()->autoRedirectTo($url);
                return "";
            } else {
                throw new BambooException('Could not find payment url for Order: '.$order->id);
            }
        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            $this->app->applicationError("PaymentController",'Error Ordering','Error while going to payment',$e);
            $this->app->router->response()->autoRedirectTo($this->app->baseUrl(true));
            return "";
        }
    }
}