<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\CNodeView;
use bamboo\domain\repositories\CCartRepo;
use bamboo\ecommerce\business\CCheckoutStep;
use bamboo\core\router\ANodeController;
use bamboo\helpers\CWidgetCatalogHelper;

/**
 * Class CCartSideSummaryController
 * @package bamboo\app\controllers
 */
class CCartSideSummaryController extends ANodeController
{
    public function get()
    {
        $this->view = new CNodeView($this->request, $this->config['template']['fullpath']);

        $this->fetchData();

        /** @var CCartRepo $cartRepo */
        $cart = $this->dataBag->entity;
        $totale = $cart->getGrossTotal();
        if (!is_null($this->request->getFilter('shipping'))) {
            $cart->getShippingModifier($totale,$this->request->getFilter('shipping'));
        }
	    $couponSale = $cart->getCouponModifier();

        $checkoutStep = new CCheckoutStep($this->app);

        if(isset($this->request->getArgs()['checkoutStep'])) {
            $pointer = $checkoutStep->pointerNumber($this->request->getArgs()['checkoutStep']);
            $checkoutStep->setPointer($pointer);
        } else $checkoutStep->fetchPresentStep();

        $this->helper = new CWidgetCatalogHelper($this->app);

        $this->view->pass('total', $totale);
        $this->view->pass('couponSale', $couponSale);
        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        $this->view->pass('checkoutStep', $checkoutStep->current());
        $nextCheckoutStep = $checkoutStep->fetchNextStep(true,$checkoutStep->current());
        $this->view->pass('nextCheckoutStepAddress', $checkoutStep->stepAddress($nextCheckoutStep['pointer']));

        return $this->show();
    }

    public function post() {return $this->get();}

    public function put()
    {
        /** @var CCartRepo $cartRepo */
        $cartRepo = \Monkey::app()->repoFactory->create('Cart');
        if ($this->request->getFilter('value')>0) {
            $cart = $cartRepo->currentCart();
            $cartLine = $cart->cartLine->findOneByKey('id',$this->request->getfilter('id'));
            if($cartLine) {
                unset($cartLine->id);
                $cartLine->insert();
            }
        } else {
            $cartRepo->removeSku($this->request->getFilter('id'));
        }
        return $this->get();
    }

    public function delete()
    {
	    \Monkey::app()->repoFactory->create('Cart')->removeSku($this->request->getFilter('id'));
        return $this->get();
    }
}