<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductAttribute
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CUserSessionHasOrder extends AEntity
{
    protected $entityTable = 'UserSessionHasOrder';
    protected $primaryKeys = ['userSessionId', 'orderId'];
}