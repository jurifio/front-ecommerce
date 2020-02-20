<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CSidebarSectionTranslation
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 20/02/2020
 * @since 1.0
 */
class CSidebarSectionTranslation extends AEntity
{
    protected $entityTable = 'SidebarSectionTranslation';
    protected $primaryKeys = array('sidebarSectionId');
}