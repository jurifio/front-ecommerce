<?php

namespace bamboo\controllers\widget;

use bamboo\helpers\CWidgetCatalogHelper;
use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\router\ANodeController;

/**
 * Class CTabContentController
 * @package bamboo\app\controllers
 */
class CTabContentController extends ANodeController
{
    public function get()
    {
        $this->view = new VBase($this->response->getChildren());
        $this->view->setTemplatePath($this->config['template']['fullpath']);

        $this->fetchData();
        $this->helper = new CWidgetCatalogHelper($this->app);

        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        return $this->show();
    }

    public function post() {return $this->get();}
    public function put() {return $this->get();}
    public function delete() {return $this->get();}
}