<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CSidebarGroupTranslation
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CSidebarGroupTranslation extends AEntity
{
    protected $entityTable = 'SidebarGroupTranslation';
    protected $primaryKeys = array('sidebarGroupId');
}