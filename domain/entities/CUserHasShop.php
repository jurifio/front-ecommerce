<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CUserDetails
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CUserHasShop extends AEntity
{
    protected $entityTable = 'UserHasShop';
    protected $primaryKeys = ['userId','shopId'];
}