<?php

namespace bamboo\controllers\widget;

use bamboo\core\asset\CHeadTag;
use bamboo\core\router\ANodeController;
use bamboo\ecommerce\views\VBase;
use bamboo\core\router\CInternalResponse;
use bamboo\core\exceptions\RedPandaThemeException;

/**
 * Class CProductphotopreviewController
 * @package bamboo\app\controllers
 */
class CProductsummaryController extends ANodeController
{
	/**
	 * @return CInternalResponse
	 * @throws \Exception
	 * @throws \bamboo\core\exceptions\BambooThemeException
	 * @throws \bamboo\core\exceptions\RedPandaInvalidArgumentException
	 */
    public function get() {

        $this->view = new VBase($this->response->getChildren());
        $this->view->setTemplatePath($this->config['template']['fullpath']);

        $this->fetchData();

	    try {
            $this->app->router->response()->addHeadTag(new CHeadTag('meta', null, null, ["property" => "og:url",
		                                                                                 "content" => $this->app->baseUrl(false).$this->app->router->request()->getUrlPath()]));
            $this->app->router->response()->addHeadTag(new CHeadTag('meta', null, null, ["property" => "og:type",
		                                                                                 "content" => "product"]));
            $this->app->router->response()->addHeadTag(new CHeadTag('meta', null, null, ["property" => "og:title",
		                                                                                 "content" => $this->dataBag->entity->getName()]));
            $this->app->router->response()->addHeadTag(new CHeadTag('meta', null, null, ["property" => "og:image",
		                                                                                 "content" => $this->helper->image($this->dataBag->entity->getPhoto(1,843),'amazon')]));
            $this->app->router->response()->addHeadTag(new CHeadTag('meta', null, null, ["property" => "product:brand",
		                                                                                 "content" => $this->dataBag->entity->productBrand->name]));
	    } catch (\Throwable $e) {
	    }

        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        return $this->show();
    }

    public function post() {return $this->get();}
    public function put() {return $this->get();}
    public function delete() {return $this->get();}
}