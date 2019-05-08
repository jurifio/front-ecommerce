<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\ARootController;
use bamboo\core\router\ANodeController;

/**
 * Class CApiController
 * @package bamboo\ecommerce\controllers
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 20/03/2019
 * @since 1.0
 */
class CApiController extends ARootController
{
    /**
     * @param $action
     * @return bool
     */
    public function createAction($action)
    {
        $filters = $this->app->router->getMatchedRoute()->getComputedFilters();
        $this->{$action}($filters);
        return true;
    }

    /**
     * @param $filters
     * @return bool
     */
    public function get($filters)
    {
        return $this->reroute($filters);
    }

    /**
     * @param $filters
     * @return bool
     */
    public function post($filters)
    {
        return $this->reroute($filters);
    }

    /**
     * @param $filters
     * @return bool
     */
    public function put($filters)
    {
        return $this->reroute($filters);
    }

    /**
     * @param $filters
     * @return bool
     */
    public function delete($filters)
    {
        return $this->reroute($filters);
    }

    /**
     * @param $filters
     * @return bool
     */
    public function reroute($filters)
    {
        switch ($this->request->getMethod()) {
            case "POST":
                $action = 'post';
                $jsonData = [];
                $jsonData['json'] = json_decode(file_get_contents('php://input'), true);
                $data = $_POST + $filters + $jsonData;
                break;
            case "PUT":
                $action = 'put';
                $put = [];
                foreach (explode('&',file_get_contents('php://input')) as $var) {
                    $a = explode('=',$var);
                    $put[$a[0]] = $a[1];
                }
                $data = $put + $filters;
                break;
            case "DELETE":
                $action = 'delete';
                $delete = [];
                foreach (explode('&',file_get_contents('php://input')) as $var) {
                    $a = explode('=',$var);
                    $delete[$a[0]] = $a[1];
                }
                $data = $delete + $filters;
                break;
            case "GET":
                $action = 'get';
                $data = $_GET + $filters;
                break;
            default:
                $this->response->raiseBadMethodError();
                $this->response->setBody("<h1>405, Method Not Allowed</h1>");
        }

        $endpointName = $filters['endpoint'];
        $endpointController = "bamboo\\controllers\\api\\classes\\".$endpointName;

        if (class_exists($endpointController) && isset($data) && !$this->response->isError()) {
            $endpoint = new $endpointController($this->app, $data);
        } else {
            $this->response->raiseBadMethodError();
            $this->response->setBody("<h1>405, Method Not Allowed</h1>");
        }

        /**
         * Check and execute method
         * @var ANodeController $endpoint
         */
        if (isset($action) && method_exists($endpoint,$action) && !$this->response->isError()){
            try {
                $this->response->setBody(json_encode($endpoint->createAction($action)));
            } catch(\Throwable $e) {
                if($this->app->isDevMode()){
                    $this->response->setBody($e->getMessage());
                    $this->response->raiseProcessingError();
                } else {
                    $this->response->raiseProcessingError();
                }
            }
        } else {
            $this->response->setBody("<h1>405, Method Not Allowed</h1>");
            $this->response->raiseBadMethodError();
        }

        $this->response->sendHeaders();
        echo $this->response->getBody();
        return true;
    }

    /**
     * @param $path
     * @return string
     */
    protected function rewriteAssetUrl($path)
    {
        $pos = strrpos($path,'/');
        if((bool) $pos){
            $name = substr($path, $pos+1);
            return $this->app->baseUrl().'/assets/'.$name;
        } else {
            return $path;
        }
    }

    public function __destruct() {}
}