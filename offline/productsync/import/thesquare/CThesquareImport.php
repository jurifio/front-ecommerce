<?php
namespace bamboo\offline\productsync\import\thesquare;

use bamboo\domain\entities\CShop;
use bamboo\core\application\AApplication;
use bamboo\core\base\CConfig;
use bamboo\core\db\pandaorm\entities\CEntityManager;
use bamboo\core\utils\amazonPhotoManager\ImageEditor;
use bamboo\ecommerce\offline\productsync\import\AGf888Import;


/**
 * Class CGf888Import
 * @package bamboo\import\productsync\dellamartira
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 *
 * @since ${VERSION}
 */

class CThesquareImport extends AGf888Import {

	public function getShop()
	{
		if($this->shop instanceof CShop){
			return $this->shop;
		}
		/** @var CEntityManager $em */
		$em = $this->app->entityManagerFactory->create('Shop');
		$obc = $em->findBySql("SELECT id FROM Shop WHERE `name` = ?", array('thesquare'));
		return $obc->getFirst();
	}

	/**
	 * @param $dirtyProductId
	 * @param AApplication $app
	 * @return bool|string
	 */
	public static function getDummyPic($dirtyProductId, AApplication $app)
	{
		$repo = $app->repoFactory->create('DirtyProduct');
		$dp = $repo->findOneBy(['id'=>$dirtyProductId]);

		$config = new CConfig(__DIR__."/import.thesquare.config.json");
		$config->load();

		$ieditor = new ImageEditor();
		$dummyFolder = $app->rootPath().$app->cfg()->fetch('paths', 'dummyFolder') . '/';
		$photosLocation = $app->cfg()->fetch('paths', 'productSync') . '/' . $app->getName() . '/' . $config->fetch('miscellaneous', 'photos') . '/';

		$a = preg_grep("/".$dp->itemno.'-'.$dp->var."/u",glob($photosLocation . '*'));

		foreach($a as $photo) {
			$ieditor->load($photo);
			$ieditor->resizeToWidth(500);
			$dummyName = rand(0,9999999999).'.'.pathinfo($photo)['extension'];
			$ieditor->save($dummyFolder.$dummyName);
			return $dummyFolder.$dummyName;
		}

		return false;
	}
}