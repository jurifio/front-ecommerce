<?php

namespace bamboo\domain\repositories;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CTag;
use bamboo\traits\TCatalogRepoFunctions;

/**
 * Class CTagRepo
 * @package bamboo\domain\repositories
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 27/04/2016
 * @since 1.0
 */
class CTagRepo extends ARepo
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
        $sql = "SELECT DISTINCT tag AS id FROM ({$this->catalogInnerQuery}) t {$this->orderBy($orderBy)} {$this->limit($limit)} ";
        return $this->em()->findBySql($sql, $this->prepareParams($params));
    }

    public function getAllSpecialTag(){

        /** @var CObjectCollection $tags */
        $tags = $this->findAll();

        $special = new CObjectCollection();
        /** @var CTag $tag */
        foreach ($tags as $tag){

            $isSpecial = substr($tag->slug, 0, 3);

            if($isSpecial == 'spc'){
                $special->add($tag);
            }

        }

        return $special;
    }
}