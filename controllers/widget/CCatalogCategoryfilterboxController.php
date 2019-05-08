<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\CNodeView;
use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\router\ANodeController;
use bamboo\helpers\CWidgetCatalogHelper;

/**
 * Class CCatalogCategoryfilterboxController
 * @package bamboo\controllers\widget
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 01/11/2014
 * @since 1.0
 */
class CCatalogCategoryfilterboxController extends ANodeController
{
    public function get()
    {
        $this->view = new CNodeView($this->request,$this->config['template']['fullpath']);

        $this->fetchData();
        $this->helper = new CWidgetCatalogHelper($this->app);
        $catRepo = \Monkey::app()->repoFactory->create('ProductCategory');
        $this->dataBag->parent = $catRepo->fetchParent($this->app->router->getMatchedRoute()->getComputedFilters()['categoryId']);
        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        $this->view->pass('position', $this->app->router->getMatchedRoute()->getComputedFilters()['categoryId']);
        return $this->show();
    }

    public function post()
    {
        return $this->get();
    }

    public function put()
    {
        return $this->get();
    }

    public function delete()
    {
        return $this->get();
    }
}