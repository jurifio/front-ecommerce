<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\CNodeView;
use bamboo\domain\entities\CPagination;
use bamboo\core\router\ANodeController;
use bamboo\ecommerce\views\VBase;
use bamboo\helpers\CWidgetCatalogHelper;

/**
 * Class CPaginationController
 * @package bamboo\app\controllers
 */
class CPaginationController extends ANodeController
{

    public function get()
    {

        $this->view = new CNodeView($this->request,$this->config['template']['fullpath']);
        $this->fetchData();

        $this->helper = new CWidgetCatalogHelper($this->app);
        $rollback = $this->config['data'][$this->request->getDataAddress()]->remote->limit;
        $defaultLimit = isset($this->dataBag->defaultLimit) && is_numeric($this->dataBag->defaultLimit) && $this->dataBag->defaultLimit > 0 ? $this->dataBag->defaultLimit : $rollback;
        $pagination = new CPagination($this->dataBag->count, $defaultLimit ,$this->app->router->request()->getRequestData());

        $this->view->pass('app', $this->helper);
        $this->view->pass('pagination', $pagination);
        $this->view->pass('data', $this->dataBag);
        return $this->show();
    }

    protected function fetchPandaORM($config)
    {
        $repo = \Monkey::app()->repoFactory->create($config->repository);
        /** @var IRepo $repo */
        $data = $repo->countBy($config->method,  $this->bindParams($config->params));
        $this->dataBag->count = $data;
    }

    public function post() {return $this->get();}
    public function put() {return $this->get();}
    public function delete() {return $this->get();}
}