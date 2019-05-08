<?php
namespace bamboo\controllers\widget;

use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\application\AApplication;
use bamboo\core\asset\CAssetCollection;
use bamboo\core\base\CObjectCollection;
use bamboo\core\exceptions\RedPandaConfigException;
use bamboo\core\io\CJsonAdapter;
use bamboo\core\router\ANodeController;
use bamboo\core\router\CInternalRequest;

/**
 * Class CFocusPageButtonsController
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
class CFocusPageButtonsController extends ANodeController
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
        try {
            $links = $json->slice($this->app->router->getMatchedRoute()->getComputedFilter('id')."\\links");

            $this->fetchJsonData();

            $lk = new CObjectCollection();

            foreach ($links as $link) {
                $l = new \stdClass();
                $l->label = $link['label'];
                $l->href = $link['href'];
                $lk->add($l);
            }

            $this->dataBag->addMulti($lk);
        } catch (RedPandaConfigException $e) {
            $lk = new CObjectCollection();
            $this->dataBag->addMulti($lk);
        }
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