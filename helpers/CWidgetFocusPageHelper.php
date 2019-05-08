<?php

namespace bamboo\helpers;

use bamboo\core\application\AApplication;
use bamboo\core\io\CJsonAdapter;
use bamboo\core\theming\CWidgetHelper;

/**
 * Class CWidgetFocusPageHelper
 * @package bamboo\app\helpers
 */
class CWidgetFocusPageHelper extends CWidgetHelper
{
    public function __construct(AApplication $app)
    {
        $this->app = $app;
        $json = new CJsonAdapter($app->rootPath().$this->app->cfg()->fetch("paths","store-theme").'/layout/focusPage.'.$this->app->getLang()->getLang().'.json');
        $this->config = $json->slice($this->app->router->getMatchedRoute()->getComputedFilter('id'));
    }

    public function getPageTitle()
    {
        return $this->config['title'];
    }

    public function getPageTitleText()
    {
        return $this->config['subtitle'];
    }

    public function getPageBackground()
    {
        return $this->baseUrlLang().'/assets/'.$this->config['background'];
    }

    public function getIntroTitle()
    {
        return $this->config['introTitle'];
    }

    public function getIntroText()
    {
        return $this->config['introText'];
    }

}