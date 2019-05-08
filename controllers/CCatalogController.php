<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\CRootView;
use bamboo\core\router\ARootController;
use bamboo\core\router\CInternalRequest;
use bamboo\core\theming\CWidgetHelper;

/**
 * Class CCatalogController
 * @package bamboo\app\controllers
 */
class CCatalogController extends ARootController
{
	/**
	 * @param CInternalRequest $request
	 * @return string
	 * @throws \bamboo\core\exceptions\RedPandaInvalidArgumentException
	 */
    public function get(CInternalRequest $request)
    {
        $view = new CRootView($request,$this->app->rootPath().$this->app->cfg()->fetch('paths','store-theme').'/pages/catalog.php');


	    return $view->render([
		    'app' =>  new CWidgetHelper($this->app)
	    ]);
    }
}