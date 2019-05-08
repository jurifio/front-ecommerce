<?php


namespace bamboo\domain\repositories;
use bamboo\core\db\pandaorm\repositories\ARepo;

/**
 * Class CProductHistoryRepo
 * @package bamboo\app\domain\repositories
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 19/01/2016
 * @since 1.0
 */
class CProductHistoryRepo extends ARepo
{
    /**
     * Add a product history record
     *
     * @param int $pid
     * @param int $vid
     * @param int $uid
     * @param string $action
     * @param string|null $description
     */
    public function add($pid,$vid,$uid,$action,$description = null)
    {
        $entity = $this->em()->getEmptyEntity();
        $entity->productId = $pid;
        $entity->productVariantId = $vid;
        $entity->userId = $uid;
        $entity->action = $action;
        $entity->description = $description;
        $entity->insert();
    }
}