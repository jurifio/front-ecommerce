<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\ARootController;
use bamboo\core\router\CInternalRequest;
use bamboo\core\router\CRootView;
use bamboo\ecommerce\business\CCheckoutStep;
use bamboo\core\theming\CWidgetHelper;


/**
 * Class CThankYouController
 * @package bamboo\app\controllers
 */
class CThankYouController extends ARootController
{
    /**
     * @param CInternalRequest $request
     * @return string|void
     */
    public function get(CInternalRequest $request)
    {
        $step = new CCheckoutStep($this->app);
        $ok = $step->validate("thankyou");
        if($ok != "thankyou") {
            $this->app->router->response()->autoRedirectTo($step->fullAddress());
            return;
        }

        $view = new CRootView($request,$this->app->rootPath().$this->app->cfg()->fetch('paths','store-theme').'/pages/thankyou.php');


        return $view->render([
            'app' =>  new CWidgetHelper($this->app)
        ]);
    }
}