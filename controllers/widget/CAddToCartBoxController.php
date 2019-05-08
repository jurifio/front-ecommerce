<?php

namespace bamboo\controllers\widget;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\CRepo;
use bamboo\core\router\ANodeController;

/**
 * Class CAddToCartBoxController
 * @package bamboo\app\controllers
 */
class CAddToCartBoxController extends ANodeController
{
    public function post()
    {
        /** @var CRepo $repo */
        $repo = \Monkey::app()->repoFactory->create('ProductPublicSku');

        if(!is_numeric($this->request->getFilter('size'))){
            $this->response->raiseError(500,500);
            return $this->response;
        }

        /** @var CObjectCollection $skus */
        $sku = $repo->findOneBy([
            'productId' => $this->request->getFilter('product'),
            'productVariantId' => $this->request->getFilter('productVariant'),
            'productSizeId' => $this->request->getFilter('size')
            ]);
        if($sku == null){
            $count = -501;
        }
        try{
            $count = \Monkey::app()->repoFactory->create('Cart')->addSku($sku, $this->request->getFilter('qty') == null ? 1 : $this->request->getFilter('qty') );
        }catch(\Throwable $e){
            $count = 0;
        }

        //TODO fix this for pickyshop
        if($count > 0) {
            $this->response->setContent($count);
        } else {
            $this->response->raiseError(500,-1*$count);
        }
        return $this->response;

    }

    public function put() {return $this->get();}
    public function delete() {return $this->get();}
}