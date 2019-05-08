<?php


namespace bamboo\offline\productsync\import\debiagi;
use bamboo\domain\entities\CShop;
use bamboo\ecommerce\offline\productsync\import\ABlueSealSimpleXMLProductImporter;
use bamboo\core\db\pandaorm\entities\CEntityManager;

/**
 * Class CRetroImporter
 * @package bamboo\htdocs\pickyshop\import\productsync\retro
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, 03/12/2015
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CDebiagiImporter extends ABlueSealSimpleXMLProductImporter
{
	public function getShop(){
		if($this->shop instanceof CShop){
			return $this->shop;
		}
		/** @var CEntityManager $em */
		$em = $this->app->entityManagerFactory->create('Shop');
		$obc = $em->findBySql("SELECT id FROM Shop WHERE `name` = ?", array('debiagi'));
		return $obc->getFirst();
	}

	public function customFill()
	{
		return true;
	}
}