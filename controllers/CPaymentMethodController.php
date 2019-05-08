<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\CRootView;
use bamboo\ecommerce\business\CCheckoutStep;
use bamboo\core\router\ARootController;
use bamboo\core\router\CInternalRequest;
use bamboo\core\theming\CWidgetHelper;

/**
 * Class CPaymentMethodController
 * @package bamboo\app\controllers
 */
class CPaymentMethodController extends ARootController
{
    /**
     * @param CInternalRequest $request
     * @return string
     */
	public function get(CInternalRequest $request)
	{
        $step = new CCheckoutStep($this->app);
        $presentStep = $step->fetchPresentStep();
        $ok = $step->validate($presentStep['name']);

        if($ok != $presentStep) {
            $this->app->router->response()->autoRedirectTo($step->fullAddress());
            return "";
        }

        $view = new CRootView($request,$this->app->rootPath().$this->app->cfg()->fetch('paths','store-theme').'/pages/paymentmethod.php');


        return $view->render([
            'app' =>  new CWidgetHelper($this->app)
        ]);
	}
}