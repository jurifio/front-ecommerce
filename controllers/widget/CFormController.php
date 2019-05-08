<?php

namespace bamboo\controllers\widget;

use bamboo\ecommerce\business\CForm;
use bamboo\core\exceptions\RedPandaConfigException;
use bamboo\core\router\ANodeController;
use bamboo\ecommerce\views\widget\VBase;

/**
 * Class CFormController
 * @package bamboo\app\controllers
 */
class CFormController extends ANodeController
{
    /**
     * @param CForm $form
     * @return \bamboo\core\router\CInternalResponse
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaThemeException
     */
    public function get(CForm $form = null)
    {
        $this->fetchJsonData();

        if ($form == null && !empty($this->dataBag->onGet)){
            $repo = \Monkey::app()->repoFactory->create($this->dataBag->repository);
            return $this->get($repo->{$this->dataBag->onGet}($this->request->getDataAddress()));
        }

        $this->view = new VBase($this->response->getChildren());
        $this->view->setTemplatePath($this->config['template']['fullpath']);

        if ($form != null) {
            $this->view->pass('errors',json_encode($form->getErrors(),JSON_FORCE_OBJECT|JSON_HEX_APOS|JSON_HEX_QUOT  ));
            $this->view->pass('presets',json_encode($form->getValues(),JSON_FORCE_OBJECT|JSON_HEX_APOS|JSON_HEX_QUOT  ));
            $this->view->pass('outcome',json_encode($form->getOutcome(),JSON_FORCE_OBJECT|JSON_HEX_APOS|JSON_HEX_QUOT  ));
            $this->app->bubble($form->getName(),$form);
        } else {
            $this->view->pass('errors','{}');
            $this->view->pass('presets','{}');
            $this->view->pass('outcome','{}');
        }

        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);

        return $this->show();
    }

    /**
     * @return \bamboo\core\router\CInternalResponse
     * @throws RedPandaConfigException
     * @throws \bamboo\core\exceptions\RedPandaThemeException
     */
    public function post()
    {
        $this->fetchJsonData();
        $form = new CForm($this->request->getDataAddress(),$this->app->router->request()->getRequestData());
        $repo = \Monkey::app()->repoFactory->create($this->dataBag->repository);

        return $this->get($repo->{$this->dataBag->onPost}($form));
    }

    /**
     * @return \bamboo\core\router\CInternalResponse
     * @throws \bamboo\core\exceptions\RedPandaThemeException
     */
    public function put()
    {
        $this->fetchJsonData();
        $form = new CForm($this->request->getDataAddress(),$this->request->getFilters());
        $repo = \Monkey::app()->repoFactory->create($this->dataBag->repository);

        return $this->get($repo->{$this->dataBag->onPut}($form));
    }

    public function delete() {return $this->get();}

    /**
     * @param CForm $form
     */
    protected function auth(CForm $form)
    {
        if($form->getValue('email') != null) {
            return $this->app->authManager->auth($form->getValue('email'));
        }
        return $this->app->authManager->auth();
    }

    /**
     * @param $where
     */
    protected function redirect($where)
    {
        $this->app->router->response()->autoRedirectTo($where);
    }

}