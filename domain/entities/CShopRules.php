<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CShopRules
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 * @property CAddressBook $billingAddressBook
 * @property CObjectCollection $shippingAddressBook
 * @property CObjectCollection $user
 * @property CObjectCollection $sectional
 */
class CShopRules extends AEntity
{

    protected $entityTable = 'ShopRules';
    protected $primaryKeys = ['id'];

}