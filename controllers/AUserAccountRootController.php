<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\CInternalRequest;
use bamboo\core\router\ARootController;

/**
 * Class AUserAccountRootController
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
abstract class AUserAccountRootController extends ARootController
{
    /**
     * @param $action
     * @return string|null
     */
    public function createAction($action)
    {
        if (\Monkey::app()->authManager->auth() === false) {
            \Monkey::app()->router->response()->autoRedirectTo(\Monkey::app()->baseUrl()."/login",302,"Log in to enter User Account");
            return "";
        }
        $filters = $this->app->router->getMatchedRoute()->getComputedFilters();

        $request = new CInternalRequest("",
            $filters['loc'] ?? $this->app->getDefaultLanguage(),
            $filters,
            $this->request->getRequestData(),
            $this->app->router->request()->getMethod());
        return $this->{$action}($request);
    }
}