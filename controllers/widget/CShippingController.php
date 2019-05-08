<?php

namespace bamboo\controllers\widget;

/**
 * Class CShippingController
 * @package bamboo\app\controllers
 */
class CShippingController extends CFormController
{
    public function post() {
        throw new \Exception('If this happen, you have found the form controller extension, check the code');
        $id = $this->request->getfilter('value');
        $repo = \Monkey::app()->repoFactory->create('Cart');
        $this->response->setContent($repo->updatePaymentMethod($id));
        return $this->response;

    }
    public function put() {return $this->get();}
    public function delete() {return $this->get();}
}