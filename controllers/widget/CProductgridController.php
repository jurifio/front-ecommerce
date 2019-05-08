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
class CProductgridController extends ANodeController
{
    public function get()
    {
        $this->view = new CNodeView($this->request,$this->config['template']['fullpath']);

        $this->fetchData();
        $this->helper = new CWidgetCatalogHelper($this->app);

        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        return $this->show();
    }

    protected function fetchPandaORM($config)
    {
        /** @var IRepo $repo */
        $repo = \Monkey::app()->repoFactory->create($config->repository);

        if (($get = $this->app->router->request()->getRequestData()) != null) {
            if (isset($get["page"])) {
                $page = $get["page"] - 1;
            }
            if (isset($get["nelem"])) {
                $pageElem = $get["nelem"];
            }
        }

        $sorting = [];
        if (isset($this->app->router->request()->getRequestData()['sortby'])) {
            $prms = explode('-', $this->app->router->request()->getRequestData()['sortby']);
            $sort = [];
            $sort['field'] = $prms[0];
            $sort['order'] = $prms[1];
            array_unshift($sorting, $sort);
        }

        $pe = isset($pageElem) ? $pageElem : $config->limit;
        $p = isset($page) ? $page : $config->offset;
        $p = $p * $pe;
        $sort = !empty($sorting) ? $sorting : $config->sorting;

        $data = $repo->listBy($config->method, array($p, $pe), $sort, $this->bindParams($config->params), []);
        $this->dataBag->addMulti($data);
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