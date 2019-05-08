<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\CInternalRequest;
use bamboo\core\router\ARootController;
use bamboo\core\router\CRootView;
use bamboo\core\theming\CWidgetHelper;

/**
 * Class CUserAccountSupportController
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
class CUserAccountSupportController extends AUserAccountRootController
{
    /**
     * @param CInternalRequest $request
     * @return mixed
     */
    public function get(CInternalRequest $request)
    {
        $view = new CRootView($request,$this->app->rootPath().$this->app->cfg()->fetch('paths','store-theme').'/pages/useraccountsupport.php');


        return $view->render([
            'app' =>  new CWidgetHelper($this->app)
        ]);
    }
}