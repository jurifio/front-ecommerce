<?php
namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CUser;

/**
 * Class CShopRepo
 * @package bamboo\domain\repositories
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 08/09/2016
 * @since 1.0
 */
class CDatesRepo extends ARepo
{
    /**
     * @return \bamboo\core\base\CObjectCollection
     */
    public function getOldestDate() {
        $res = $this->em()->findBySql('SELECT min(time) FROM _Dates');
        foreach($res as $v) {
            return $v;
        }
        return false;
    }
}
