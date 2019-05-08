<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductSheetModelPrototypeSupport
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 22/10/2018
 * @since 1.0
 */
class CProductSheetModelPrototypeSupport extends AEntity
{
    protected $entityTable = 'ProductSheetModelPrototypeSupport';
    protected $primaryKeys = ['id'];
}