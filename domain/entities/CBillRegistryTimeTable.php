<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CBillRegistryTimeTable
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 27/01/2020
 * @since 1.0
 */
class CBillRegistryTimeTable extends AEntity
{

    protected $entityTable = 'BillRegistryTimeTable';
    protected $primaryKeys = ['id'];
}