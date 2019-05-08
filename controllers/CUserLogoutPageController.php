<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\ARootController;

/**
 * Class CUserLogoutPageController
 * @package bamboo\app\controllers
 */
class CUserLogoutPageController extends ARootController
{
    public function createAction($action)
    {
        $this->app->authManager->auth();
        $this->app->authManager->logout();
        return $this->{$action}();
    }

    public function get()
    {
        $this->app->router->response()->autoRedirectTo($this->app->baseUrl());
    }
}