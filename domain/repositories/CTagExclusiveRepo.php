<?php

namespace bamboo\domain\repositories;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CTagExclusive;
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
class CTagExclusiveRepo extends ARepo
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
        $sql = "SELECT DISTINCT tagExclusive AS id FROM ({$this->catalogInnerQuery}) t {$this->orderBy($orderBy)} {$this->limit($limit)} ";
        return $this->em()->findBySql($sql, $this->prepareParams($params));
    }

}