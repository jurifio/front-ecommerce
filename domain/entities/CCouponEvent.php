<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCouponEvent
 * @package bamboo\app\domain\entities
 */
class CCouponEvent extends AEntity
{
    protected $entityTable = 'CouponEvent';
    protected $primaryKeys = array('id');
}