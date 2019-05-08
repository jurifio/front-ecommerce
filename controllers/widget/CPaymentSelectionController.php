<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\ANodeController;
use bamboo\core\router\CInternalResponse;
use bamboo\core\exceptions\RedPandaThemeException;
use bamboo\ecommerce\views\widget\VBase;

/**
 * Class CPaymentSelectionController
 * @package bamboo\app\controllers
 */
class CPaymentSelectionController extends ANodeController
{
    /**
     * @return CInternalResponse
     * @throws RedPandaThemeException
     */
    public function get()
    {
        $this->view = new VBase($this->response->getChildren());
        $this->view->setTemplatePath($this->config['template']['fullpath']);

        $this->fetchData();

        $cart = \Monkey::app()->repoFactory->create('Cart')->currentCart();

        if (!empty($cart->orderPaymentMethodId)) {
            $this->view->pass('paymentMethod', $cart->orderPaymentMethodId);
        }
        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        return $this->show();
    }

    /**
     * @return CInternalResponse
     */
    public function post()
    {
        $id = $this->request->getfilter('value');
        $repo = \Monkey::app()->repoFactory->create('Cart');
        $this->response->setContent($repo->updatePaymentMethod($id));
        return $this->response;
    }

    public function put() {return $this->get();}
    public function delete() {return $this->get();}
}