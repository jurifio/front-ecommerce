<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\CInternalRequest;
use bamboo\core\router\ARootController;
use bamboo\core\router\CRootView;
use bamboo\core\theming\CWidgetHelper;

/**
 * Class CUserAccountMyAddressesController
 * @package bamboo\app\controllers
 */
class CUserAccountMyAddressesController extends AUserAccountRootController
{
    /**
     * @param CInternalRequest $request
     * @return string
     */
    public function get(CInternalRequest $request)
    {
        $view = new CRootView($request,$this->app->rootPath().$this->app->cfg()->fetch('paths','store-theme').'/pages/useraccountaddresses.php');


        return $view->render([
            'app' =>  new CWidgetHelper($this->app)
        ]);
    }
}