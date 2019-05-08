<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\CRootView;
use bamboo\core\router\ARootController;
use bamboo\core\router\CInternalRequest;
use bamboo\core\theming\CWidgetHelper;
use bamboo\ecommerce\business\CCheckoutStep;

/**
 * Class CArticleController
 * @package bamboo\app\controllers
 */
class CActivationRequiredController extends ARootController
{
    /**
     * @param CInternalRequest $request
     * @return string
     * @throws \bamboo\core\exceptions\RedPandaInvalidArgumentException
     */
    public function get(CInternalRequest $request)
    {
        $step = new CCheckoutStep($this->app);
        $presentStep = $step->fetchPresentStep();
        $ok = $step->validate($presentStep['name']);

        if ($ok != $presentStep) {
            $this->app->router->response()->autoRedirectTo($step->fullAddress());
            return "";
        }
        $next = $step->fetchNextStep();
        if($next == $step->validate($next['name'])) {
            $this->app->router->response()->autoRedirectTo($step->stepAddress($next['pointer']));
            return "";
        }

        $view = new CRootView($request, $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'store-theme') . '/pages/activationrequired.php');

        return $view->render([
            'app' => new CWidgetHelper($this->app)
        ]);
    }
}