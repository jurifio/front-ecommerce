<?php

namespace bamboo\domain\repositories;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\traits\TCatalogRepoFunctions;

/**
 * Class CProductBrandRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CProductBrandRepo extends ARepo
{
    use TCatalogRepoFunctions;
    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return CObjectCollection
     */
    public function listByAppliedFilters(array $limit, array $orderBy, array $params)
    {
	    $sql = "SELECT DISTINCT brand AS id FROM ({$this->catalogInnerQuery}) t {$this->orderBy($orderBy)} {$this->limit($limit)} ";
        $brands = $this->findBySql($sql,$this->prepareParams($params));
        $brands->reorder('name');
        return $brands;
    }

    /**
     * Find the brand written on the filters
     *
     * @param array $params
     * @param array $args
     * @return \bamboo\core\db\pandaorm\entities\AEntity|null
     */
    public function fetchEntityByAppliedFilters(array $params, array $args)
    {
        return $this->findOneBy(['id'=>$this->app->router->getMatchedRoute()->getComputedFilter('brandId')]);
    }


    /**
	 * @param array $limit
	 * @param array $orderBy
	 * @param array $params
	 * @param array $args
	 * @return array|CObjectCollection
	 */
	public function listByMacroCategory(array $limit,array $orderBy, array $params, array $args)
	{
		$sections = [];
		foreach($args as $key=>$val){
		    if(is_object($val)) $val = (array) $val;
			$cat =  \Monkey::app()->repoFactory->create('ProductCategory')->findOneBy(["id"=>$val['category']]);
			$brands = $val['brands'];

			if(empty($brands)){
				$sql = "SELECT DISTINCT brand as id from ({$this->catalogInnerQuery}) t limit 10";
				$res = $this->em()->findBySql($sql,$this->prepareParams(['category'=>$val['category']]));
			} else {
				$res = new CObjectCollection();
				foreach($brands as $brand) {
					$res->add($this->em()->findOne([$brand]));
				}
			}
			$sections[] = ["category"=>$cat,"brands"=>$res];

		}
		return $sections;
	}
}
