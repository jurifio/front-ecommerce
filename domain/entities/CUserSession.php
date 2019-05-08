<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CUserSession
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CUserSession extends AEntity
{
    protected $entityTable = 'UserSession';
    protected $primaryKeys = array('id');
	protected $isCacheable = true;
}