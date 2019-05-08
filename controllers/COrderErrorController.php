<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\ARootController;
use bamboo\core\router\CInternalRequest;
use bamboo\core\router\CRootView;
use bamboo\ecommerce\business\CCheckoutStep;
use bamboo\core\theming\CWidgetHelper;


/**
 * Class COrderErrorController
 * @package bamboo\app\controllers
 */
class COrderErrorController extends ARootController
{
    /**
     * @param CInternalRequest $request
     * @return string
     */
    public function get(CInternalRequest $request)
    {

        $step = new CCheckoutStep($this->app);
        $ok = $step->validate("payment");
        if($ok != "payment") {
            $personalAreaUrl = $this->app->cfg()->fetch('miscellaneous','personalAreaUrl');
            $this->app->router->response()->autoRedirectTo($this->app->baseUrl().$personalAreaUrl.'/myorders');
            return "";
        }
        $view = new CRootView($request,$this->app->rootPath().$this->app->cfg()->fetch('paths','store-theme').'/pages/ordererror.php');


        return $view->render([
            'app' =>  new CWidgetHelper($this->app)
        ]);

    }


}