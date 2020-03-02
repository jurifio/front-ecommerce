<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooApplicationException;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CBillRegistryActivePaymentSlipStatus
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 02/03/2020
 * @since 1.0
 */

class CBillRegistryActivePaymentSlipStatus extends AEntity
{

    protected $entityTable = 'BillRegistryActivePaymentSlipStatus';
    protected $primaryKeys = ['id'];

}