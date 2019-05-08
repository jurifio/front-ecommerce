<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\CNodeView;
use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\router\ANodeController;
use bamboo\helpers\CWidgetCatalogHelper;

/**
 * Class CProductgridController
 * @package bamboo\controllers\widget
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CProductCatalogDisplayController extends ANodeController
{
    public function get()
    {
        $this->view = new CNodeView($this->request,$this->config['template']['fullpath']);

        $this->fetchJsonData();
        $this->helper = new CWidgetCatalogHelper($this->app);
        $product = \Monkey::app()->repoFactory->create('Product')->findOneBy($this->request->getParams()['productIds']);
        $this->view->pass('app', $this->helper);
        $this->dataBag->entity = $product;
        $this->view->pass('data', $this->dataBag);
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