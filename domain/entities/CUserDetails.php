<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CUserDetails
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CUserDetails extends AEntity
{
    protected $entityTable = 'UserDetails';
    protected $primaryKeys = array('userId');
}