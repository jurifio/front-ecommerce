<?php

namespace bamboo\controllers\widget;

use bamboo\domain\repositories\CProductRepo;
use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\application\AApplication;
use bamboo\core\asset\CAssetCollection;
use bamboo\core\base\CObjectCollection;
use bamboo\core\io\CJsonAdapter;
use bamboo\core\router\ANodeController;
use bamboo\core\router\CInternalRequest;

/**
 * Class CProductSliderController
 * @package bamboo\app\controllers
 */
class CProductSliderController extends ANodeController
{
    /**
     * @var CAssetCollection
     */
    protected $assetCollection;

    /**
     * @return CJsonAdapter
     */
    public function getJsonConfig()
    {
        if(!$json = $this->app->cacheService->getCache('misc')->get('FocusPage::it')) {
            $json = $json = new CJsonAdapter($this->app->rootPath() . $this->app->cfg()->fetch("paths", "store-theme") . '/layout/focusPage.' . $this->app->getLang()->getLang() . '.json');
            $this->app->cacheService->getCache('misc')->set('FocusPage::it', $json);
        }
        return $json;
    }


    /**
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaConfigException
     */
    public function fetchData()
    {
        $json = $this->getJsonConfig();
        $youmightlike = $json->slice($this->request->getFilter('id')."\\youmightlike");

        $this->fetchJsonData();

        /**
         * @var CProductRepo $repo
         */
        $repo = \Monkey::app()->repoFactory->create("Product");

        $products = [];

        foreach ($youmightlike as $type=>$ids) {
            $news = [];
            switch (trim($type)) {
                case 'brands':
                    foreach($ids as $id) {
                        $news[] = $repo->listByBrand([0,20],[['field'=>'creation','order'=>'desc']],[],['brand'=>$id]);
                    }
                    break;
                case 'products':
                    foreach ($ids as $id) {
                        $news[] = [$repo->findOneByStringId($id)];
                    }
                    break;
                default:
                    foreach ($ids as $id) {
                        $news[] = $repo->listByCategory([0,20],[['field'=>'creation','order'=>'desc']],[],['category'=>$id]);
                    }
            }
            foreach ($news as $new) {
                foreach ($new as $product) {
                    $products[$product->printId()] = $product;
                }
            }
        }

        $this->dataBag->addMulti($products);
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