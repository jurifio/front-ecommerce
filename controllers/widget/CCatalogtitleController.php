<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\CNodeView;
use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\router\ANodeController;
use bamboo\helpers\CWidgetCatalogHelper;

/**
 * Class CCatalogfilterboxController
 * @package bamboo\app\controllers
 */
class CCatalogtitleController extends ANodeController
{
    public function get()
    {
        $this->view = new CNodeView($this->request,$this->config['template']['fullpath']);

        $this->helper = new CWidgetCatalogHelper($this->app);

        $catId = $this->app->router->getMatchedRoute()->getComputedFilters()['categoryId'];
        $repo = \Monkey::app()->repoFactory->create("ProductCategory");
        $cat = $repo->findOne(["id"=>$catId]);

        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        $this->view->pass('cat', $cat->productCategoryTranslation->findOneByKeys(["langId"=>$this->app->getLang()->getId()]));

        return $this->show();
    }

    public function post() {return $this->get();}
    public function put() {return $this->get();}
    public function delete() {return $this->get();}
}