<?php


namespace bamboo\domain\repositories;
use bamboo\core\base\CEnum;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\exceptions\RedPandaPaginationException;
use bamboo\core\exceptions\RedPandaRepositoryException;
use bamboo\core\theming\nestedCategory\CCategoryManager;
use bamboo\traits\TCatalogRepoFunctions;

/**
 * Class CProductColorGroupRepo
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
class CProductColorGroupRepo extends ARepo
{
    use TCatalogRepoFunctions;
    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return CObjectCollection
     */
    public function listByAppliedFilters(array $limit, array $orderBy,array $params){
        $sql = "SELECT DISTINCT color AS id FROM ({$this->catalogInnerQuery}) t where color is not null {$this->orderBy($orderBy)} {$this->limit($limit)} ";
        $colors = $this->em()->findBySql($sql,$this->prepareParams($params));
        $colors->reorder('name');
        return $colors;
    }
}
