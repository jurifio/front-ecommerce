<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooApplicationException;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CBillRegistryActivePaymentSlip
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

class CBillRegistryActivePaymentSlip extends AEntity
{

    protected $entityTable = 'BillRegistryPaymentSlip';
    protected $primaryKeys = ['id'];
    CONST Cd = 'SUPP';
    CONST SvcLvl_Cd = 'SEPA';
    CONST CtgyPurp_Cd = 'SUPP';
    CONST Issr = 'CBI';
    CONST InstrPrty = 'NORM';
    CONST PmtMtd = 'TRF';
    CONST Nm = 'INTERNATIONAL WEB ECOMMERCE SERVICES S.N.C.';

}