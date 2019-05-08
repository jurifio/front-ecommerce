<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CSidebarGroup
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CSidebarGroup extends AEntity
{
    protected $entityTable = 'SidebarGroup';
    protected $primaryKeys = array('id');
}