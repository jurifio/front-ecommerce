<?php


namespace bamboo\domain\repositories;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;


/**
 * Class CArticleRepo
 * @package bamboo\app\domain\repositories
 */
class CArticleRepo extends ARepo
{
    public function fetchEntityByParams()
    {
        $arg = $this->app->router->getMatchedRoute()->getComputedFilter('article');
        $lang = $this->app->getLang();
        return $this->em()->findOneBy(array("id" => $arg,"langId" =>$lang->getId()));

    }
}
