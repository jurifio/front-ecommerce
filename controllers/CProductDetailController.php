<?php

namespace bamboo\ecommerce\controllers;

use bamboo\core\router\CRootView;
use bamboo\core\router\ARootController;
use bamboo\core\router\CInternalRequest;
use bamboo\core\theming\CWidgetHelper;
use bamboo\domain\entities\CProduct;

class CProductDetailController extends ARootController
{
    /**
     * @param CInternalRequest $request
     * @return string
     */
    public function get(CInternalRequest $request)
    {
        $view = new CRootView($request,$this->app->rootPath().$this->app->cfg()->fetch('paths','store-theme').'/pages/productdetail.php');

        $productData = \Monkey::app()->router->getMatchedRoute()->getComputedFilters();

        /** @var CProduct $product */
        $product = \Monkey::app()->repoFactory->create('Product')->findOneBy(['id'=>$productData['item'], 'productVariantId'=>$productData['variant']]);

        if($product->productStatusId != 6){
            \Monkey::app()->router->response()->autoRedirectTo(\Monkey::app()->baseUrl());
        }

        return $view->render([
            'app' =>  new CWidgetHelper($this->app)
        ]);
    }
}