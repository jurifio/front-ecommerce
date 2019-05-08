<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 28/05/2015
 * Time: 16:36
 */

namespace bamboo\offline\productsync\import\girardi;

use bamboo\domain\entities\CShop;
use bamboo\core\db\pandaorm\entities\CEntityManager;
use bamboo\offline\productsync\import\AEdsTemaImporter;

class CGirardiImport extends AEdsTemaImporter {

    public function getShop(){
        if($this->shop instanceof CShop){
            return $this->shop;
        }
        /** @var CEntityManager $em */
        $em = $this->app->entityManagerFactory->create('Shop');
        $obc = $em->findBySql("SELECT id FROM Shop WHERE `name` = ?", array('girardi'));
        return $obc->getFirst();
    }
}