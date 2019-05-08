<?php

namespace bamboo\ecommerce\offline\productsync\twinsync;

use bamboo\core\db\pandaorm\adapter\CMySQLAdapter;
use bamboo\core\db\pandaorm\adapter\CMySQLStandAloneAdapter;
use bamboo\core\jobs\ACronJob;
use bamboo\domain\entities\CShop;
use bamboo\core\exceptions\BambooConfigException;
use bamboo\core\base\CConfig;


/**
 * Class AAlingSites
 * @package bamboo\ecommerce\offline\productsync\twinsync
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 18/04/2016
 * @since 1.0
 */
class AAlignSites extends ACronJob
{

	/** @var string[] */
	protected $tables = [];

	/** @var CMySQLAdapter */
	protected $origin;

	/** @var CMySQLStandAloneAdapter */
	protected $destination;
	/** @var CMySQLStandAloneAdapter */
	protected $admin;

	/** @var CShop */
	protected $shop;

	/**	@var CConfig */
	protected $config;



	public function run($args = null) 
	{
		$this->admin = new CMySQLStandAloneAdapter($this->app,'mysql','db',null,'utf8','redpanda','xMm8H4Do');
		$this->admin->connect();

		try {
			$this->fetchShop($args);
			$this->readConfig();
			$destinationConfig = $this->config->fetchAll('destination');
			$this->destination = new CMySQLStandAloneAdapter($this->app,$destinationConfig['engine'],$destinationConfig['host'],$destinationConfig['name'],'utf8',$destinationConfig['user'],$destinationConfig['pass']);
			$this->destination->connect();
			$this->origin = $this->app->dbAdapter;

			$this->tables = $this->config->fetchAll('tables');
			$this->work();
			$this->report('run','done all work');
		} catch (\Throwable $e) {
			$this->error('run','Error while running',$e);
			iwesMail('it@iwes.it','Errore Job Allineamento',$e->getMessage()."\n".$e->getTraceAsString());
		} finally {
			$this->admin->query('set FOREIGN_KEY_CHECKS = 1',[]);
			$this->report('run','set FOREIGN_KEY_CHECKS = 1');
		}

		foreach ($this->app->cacheService->getCaches() as $cache) {
			$cache->flush();
		}
	}

	/**
	 * @throws \bamboo\core\exceptions\BambooDBALException
	 */
	public function work()
	{
		$this->report('work','set FOREIGN_KEY_CHECKS = 0');
		$this->admin->query('set FOREIGN_KEY_CHECKS = 0',[]);
		foreach($this->tables as $table) {
			$this->report('Work','working '.$table['name'],$table);
			try {
				$this->truncate($table);
				$this->admin->beginTransaction();
				if($table['mode'] == 'full') {
					$sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.".$table['name']." 
								 select * from `".$this->origin->getComponentOption('name')."`.".$table['name'];
					$rows = $this->admin->query($sql,[])->countAffectedRows();
					$this->report('Work','Done query '.$table['name'].', rows: '.$rows,$sql);
				} elseif($table['mode'] == 'filter') {
					$sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.".$table['name']." 
								 select * from `".$this->origin->getComponentOption('name')."`.".$table['name']." where `".$this->origin->getComponentOption('name')."`.".$table['name'].".shopId = ?";
					$rows = $this->admin->query($sql,[$this->getShop()->id])->countAffectedRows();
                    $this->report('Work','Done query '.$table['name'].', rows: '.$rows,$sql);
				} elseif($table['mode'] == 'fill') {
                    $sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.".$table['name']." 
								 select * from `".$this->origin->getComponentOption('name')."`.".$table['name'];
                    $rows = $this->admin->query($sql,[])->countAffectedRows();
                    $this->report('Work','Done query '.$table['name'].', rows: '.$rows,$sql);
                } elseif($table['mode'] == 'fillFilter') {
					$sql = "INSERT IGNORE INTO `".$this->destination->getComponentOption('name')."`.".$table['name']." 
								 select * from `".$this->origin->getComponentOption('name')."`.".$table['name']." where `".$this->origin->getComponentOption('name')."`.".$table['name'].".shopId = ?";
					$rows = $this->admin->query($sql,[$this->getShop()->id])->countAffectedRows();
                    $this->report('Work','Done query '.$table['name'].', rows: '.$rows,$sql);
				} elseif($table['mode'] == 'custom') {
					$this->{'fill'.$table['name']}();
				}

				$this->admin->commit();
				$this->report('Work','Done working '.$table['name'],$table);
				
			} catch(\Throwable $e) {
				$this->admin->rollBack();
				$this->error('work','Errore nella lavorazione di '.$table['name'],$e);
				$this->admin->query('set FOREIGN_KEY_CHECKS = 1',[]);
				throw $e;
			}
		}
		$this->admin->query('set FOREIGN_KEY_CHECKS = 1',[]);
		foreach($this->app->cacheService->getCaches() as $key => $cache) {
			echo "Cancello cache ".$key."\n";
			$cache->flush();
		}
	}

	/**
	 * @param $table
	 */
	public function truncate($table) {
		if(isset($table['truncate']) && $table['truncate'] == true) {
			$sql = "TRUNCATE TABLE `".$this->destination->getComponentOption('name')."`.".$table['name'];
			$this->admin->query($sql,[],true);
			$this->report('Truncate','Done query ',$sql);
		}
	}

	public function fillProduct()
	{
		$sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.Product 
							 select p.* from `".$this->origin->getComponentOption('name')."`.Product p, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp where p.id = sp.productId and p.productVariantId = sp.productVariantId AND sp.shopId = ?";
		$this->admin->query($sql,[$this->getShop()->id]);
		$this->report('Work','Done query ',$sql);
	}

	public function fillProductSheetActual()
	{
		$sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.ProductSheetActual 
							 select p.* from `".$this->origin->getComponentOption('name')."`.ProductSheetActual p, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp where p.productId = sp.productId and p.productVariantId = sp.productVariantId AND sp.shopId = ?";
		$this->admin->query($sql,[$this->getShop()->id]);
		$this->report('Work','Done query ',$sql);
	}

	public function fillProductNameTranslation()
	{
		$sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.ProductNameTranslation
							 select p.* from `".$this->origin->getComponentOption('name')."`.ProductNameTranslation p, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp where p.productId = sp.productId and p.productVariantId = sp.productVariantId AND sp.shopId = ?";
		$this->admin->query($sql,[$this->getShop()->id]);
		$this->report('Work','Done query ',$sql);
	}

	public function fillProductDescriptionTranslation()
	{
		$sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.ProductDescriptionTranslation 
							 select p.* from `".$this->origin->getComponentOption('name')."`.ProductDescriptionTranslation p, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp where p.productId = sp.productId and p.productVariantId = sp.productVariantId AND sp.shopId = ?";
		$this->admin->query($sql ,[$this->getShop()->id]);
		$this->report('Work','Done query ',$sql);
	}
	
	public function fillProductHasProductPhoto()
	{
		$sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.ProductHasProductPhoto 
							 select p.* from `".$this->origin->getComponentOption('name')."`.ProductHasProductPhoto p, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp where p.productId = sp.productId and p.productVariantId = sp.productVariantId AND sp.shopId = ?";
		$this->admin->query($sql,[$this->getShop()->id]);
		$this->report('Work','Done query ',$sql);
	}
	
	public function fillProductPhoto()
	{
		$sql = "REPLACE INTO `".$this->destination->getComponentOption('name')."`.ProductPhoto 
							 select pp.* from `".$this->origin->getComponentOption('name')."`.ProductPhoto pp, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp, `".$this->origin->getComponentOption('name')."`.ProductHasProductPhoto phpp where phpp.productPhotoId = pp.id and phpp.productId = sp.productId and phpp.productVariantId = sp.productVariantId AND sp.shopId = ?";
		$this->admin->query($sql,[$this->getShop()->id]);
		$this->report('Work','Done query ',$sql);
	}

	public function fillProductHasProductCategory()
	{
		$this->admin->query("TRUNCATE TABLE `".$this->destination->getComponentOption('name')."`.ProductHasProductCategory",[]);
		$sql = "insert IGNORE INTO `".$this->destination->getComponentOption('name')."`.ProductHasProductCategory 
							 select p.* from `".$this->origin->getComponentOption('name')."`.ProductHasProductCategory p, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp where p.productId = sp.productId and p.productVariantId = sp.productVariantId AND sp.shopId = ?";
		$this->report('Work','Done query ',$sql);
		$this->admin->query($sql,[$this->getShop()->id]);
	}

	public function fillProductHasTag()
	{
		$sql = "insert ignore INTO `".$this->destination->getComponentOption('name')."`.ProductHasTag 
							 select p.* from `".$this->origin->getComponentOption('name')."`.ProductHasTag p, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp where p.productId = sp.productId and p.productVariantId = sp.productVariantId AND sp.shopId = ? and tagId = 1";
		$this->report('Work','Done query ',$sql);
		$this->admin->query($sql,[$this->getShop()->id]);
	}

	public function fillShopHasProduct()
    {
        $sql = "replace into `".$this->destination->getComponentOption('name')."`.ShopHasProduct Select productId,
                                                productVariantId,
                                                shopId,
                                                extId,
                                                productPhotoDownloadTime,
                                                value,
                                                price,
                                                salePrice,
                                                releaseDate
                                                FROM `".$this->origin->getComponentOption('name')."`.ShopHasProduct shp
                                                where shp.shopId = ?";
        $this->report('Work','Doing query ',$sql);
        $this->admin->query($sql, [$this->getShop()->id]);
        $this->report('Work','Done query ',$sql);
    }

	/**
	 * @param $args
	 * @return CShop
	 * @throws BambooConfigException
	 */
	public function fetchShop($args)
	{
		if ($args === null) throw new BambooConfigException('No Shop Selected in args, you need to set up the jobs with the shop id in "args" field');
		if (!$this->shop instanceof CShop) {
			if (is_numeric($args)) $this->shop = \Monkey::app()->repoFactory->create('Shop')->findOne(['id' => $args]);
			else  $this->shop = \Monkey::app()->repoFactory->create('Shop')->findOne(['name' => $args]);

			if (!$this->shop instanceof CShop) throw new BambooConfigException('Shop could not be fetched with arg: %s ', [json_encode($args)]);
		}

		return $this->shop;
	}

	/**
	 * @return CShop
	 */
	public function getShop()
	{
		return $this->shop;
	}

	/**
	 * @throws BambooConfigException
	 */
	public function readConfig()
	{
		$filePath = __DIR__ . '/config';
		$filePath .= '/' . $this->getShop()->name . '.json';

		if (!file_exists($filePath)) throw new BambooConfigException('Configuration not found for Importer: ' . $filePath);

		$this->config = new CConfig($filePath);
		$this->config->load();
	}

}