<?php

namespace bamboo\controllers\widget;

use bamboo\core\asset\CHeadTag;
use bamboo\core\router\CNodeView;
use bamboo\ecommerce\views\widget\VBase;
use bamboo\core\router\ANodeController;

/**
 * Class CHeadController
 * @package bamboo\controllers\widget
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CHeadController extends ANodeController
{
    protected $assetCollection;

    public function get()
    {
        $this->view = new CNodeView($this->request,$this->config['template']['fullpath']);
        $this->fetchData();

	    $uhash = $this->app->getUser()->getId() == 0 ? '' : md5($this->app->getUser()->email);
	    $this->response->getHeadTagCollection()->addConditionate(new CHeadTag('meta','uhash',null,['content'=>$uhash]));

        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        return $this->show();
    }

    public function post() {return $this->get();}
    public function put() {return $this->get();}
    public function delete() {return $this->get();}
}