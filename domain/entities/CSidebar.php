<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CSidebar
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CSidebar extends AEntity
{
    protected $entityTable = 'Sidebar';
    protected $primaryKeys = array('sidebarGroupId','pageId');
}