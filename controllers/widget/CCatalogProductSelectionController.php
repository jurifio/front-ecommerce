<?php

namespace bamboo\controllers\widget;

use bamboo\domain\entities\CProduct;
use bamboo\domain\repositories\CProductRepo;
use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\application\AApplication;
use bamboo\core\asset\CAssetCollection;
use bamboo\core\asset\CHeadTag;
use bamboo\core\base\CObjectCollection;
use bamboo\core\base\CStdCollectibleItem;
use bamboo\core\io\CJsonAdapter;
use bamboo\core\router\ANodeController;
use bamboo\core\router\CInternalRequest;

/**
 * Class CHeadController
 * @package bamboo\app\controllers
 */
class CCatalogProductSelectionController extends ANodeController
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
        try {
            $json = $this->getJsonConfig();
            $sections = $json->slice($this->app->router->getMatchedRoute()->getComputedFilter('id') . "\\sections");

            $this->fetchJsonData();

            /**
             * @var CProductRepo $repo
             */
            $repo = \Monkey::app()->repoFactory->create("Product");

            if (false) {
                $this->app->router->response()->addHeadTag(new CHeadTag('link', 'canonical', null, [["rel" => "canonical"], "href" => "/it"]));
            }

            $styles = new CObjectCollection();

            foreach ($sections as $section) {

                $data = new \stdClass();
                $data->title = $section['title'];
                $data->text = $section['text'];

                $products = [];

                foreach ($section['style'] as $style) {
                    if (isset($style['product']) && isset($style['variant'])) {
                        $product = $repo->findOne(["id" => $style['product'], "productVariantId" => $style['variant']]);
                        if ($product instanceof CProduct) {
                            $products[$product->printId()] = $product;
                        }
                        continue;
                    }
                    $productCollection = [];
                    if (isset($style['category']) && isset($style['brand'])) {
                        $productCollection = $repo->listByAfterAppliedFilters([0, 999], [["field" => "tagPriority", "order" => "ASC"], ["field" => "productPriority", "order" => "DESC"], ["field" => "creation", "order" => "DESC"]], ['brand' => $style['brand'], 'category' => $style['category']]);
                    } else if (isset($style['category']) && !isset($style['brand'])) {
                        $productCollection = $repo->listByAfterAppliedFilters([0, 999], [["field" => "tagPriority", "order" => "ASC"], ["field" => "productPriority", "order" => "DESC"], ["field" => "creation", "order" => "DESC"]], ['category' => $style['category']]);
                    } else if (!isset($style['category']) && isset($style['brand'])) {
                        $productCollection = $repo->listByAfterAppliedFilters([0, 999], [["field" => "tagPriority", "order" => "ASC"], ["field" => "productPriority", "order" => "DESC"], ["field" => "creation", "order" => "DESC"]], ['brand' => $style['brand']]);
                    }

                    foreach ($productCollection as $product) {
                        $products[$product->printId()] = $product;
                    }
                }

                $data->products = $products;
                $styles->add(new CStdCollectibleItem($data));
            }

            $this->dataBag->addMulti($styles);
        } catch (\ErrorException $e) {
            parent::fetchData();
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