<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\CInternalRequest;
use bamboo\core\router\ARootController;
use bamboo\core\router\CRootView;
use bamboo\core\theming\CWidgetHelper;

/**
 * Class CUserAccountMyOrdersController
 * @package bamboo\ecommerce\controllers
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
class CUserAccountMyOrderController extends AUserAccountRootController
{
    /**
     * @param CInternalRequest $request
     * @return string
     */
    public function get(CInternalRequest $request)
    {
        $view = new CRootView($request,$this->app->rootPath().$this->app->cfg()->fetch('paths','store-theme').'/pages/useraccountorder.php');

        return $view->render([
            'app' =>  new CWidgetHelper($this->app)
        ]);
    }
}