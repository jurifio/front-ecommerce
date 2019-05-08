<?php
namespace bamboo\controllers\widget;

use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\application\AApplication;
use bamboo\core\asset\CAssetCollection;
use bamboo\core\base\CObjectCollection;
use bamboo\core\io\CJsonAdapter;
use bamboo\core\router\ANodeController;
use bamboo\core\router\CInternalRequest;

/**
 * Class CFocusPageBannersController
 * @package bamboo\htdocs\pickyshop\app\controllers\widget
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 06/11/2015
 * @since 1.0
 */
class CFocusPageBannersController extends ANodeController
{
    /**
     * @var CAssetCollection
     */
    protected $assetCollection;

    /**
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaConfigException
     */
    public function fetchData()
    {
        $json = new CJsonAdapter($this->app->rootPath().$this->app->cfg()->fetch("paths","store-theme").'/layout/focusPage.'.$this->app->getLang()->getLang().'.json');
        $fp = $json->slice($this->app->router->getMatchedRoute()->getComputedFilter('id'));

        $this->fetchJsonData();

        $banners = [];
        foreach ($fp['sections'] as $section) {
            array_push($banners,[$section['banner'],$section['bannerColor'],$section['bannerLink']]);
        }
        array_push($banners,[$fp['banner'],$fp['bannerColor'],$fp['bannerLink']]);

        $bc = new CObjectCollection();

        foreach ($banners as $banner) {
            $b = new \stdClass();
            $b->banner = $banner[0];
            $b->bannerColor = $banner[1];
            $b->bannerLink = $banner[2];

            $bc->add($b);
        }

        $this->dataBag->addMulti($bc);
    }

    /**
     * @return \bamboo\core\router\CInternalResponse
     * @throws \Exception
     */
    public function get()
    {
        $this->view = new VBase($this->response->getChildren());
        $this->view->setTemplatePath($this->config['template']['fullpath']);

        $this->fetchData();
        $this->view->pass('assets', $this->assetCollection);
        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        return $this->show();
    }

    /**
     * @return \bamboo\core\router\CInternalResponse
     */
    public function post() {return $this->get();}

    /**
     * @return \bamboo\core\router\CInternalResponse
     */
    public function put() {return $this->get();}

    /**
     * @return \bamboo\core\router\CInternalResponse
     */
    public function delete() {return $this->get();}
}