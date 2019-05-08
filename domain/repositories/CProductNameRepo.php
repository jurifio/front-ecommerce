<?php
namespace bamboo\domain\repositories;

use bamboo\domain\entities\CProduct;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;

class CProductNameRepo extends ARepo
{
    public function findAll($limit = "", $orderBy = "", $entity = 'default'){
        $pnRepo = \Monkey::app()->repoFactory->create('ProductName');
        return $pnRepo->findAll($limit = "", $orderBy = "", $entity = 'default');
    }
}
