<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CBillRegistryContract
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
class CBillRegistryContractRow extends AEntity
{

    protected $entityTable = 'BillRegistryContractRow';
    protected $primaryKeys = ['id'];
}