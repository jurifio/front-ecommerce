<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductCategoryHasMarketplaceAccountCategory
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 30/01/2019
 * @since 1.0
 */
class CProductCategoryHasMarketplaceAccountCategory extends AEntity
{
    protected $entityTable = 'ProductCategoryHasMarketplaceAccountCategory';
    protected $primaryKeys = ['productCategoryId','marketplaceId','marketplaceAccountId','marketplaceAccountCategoryId'];
}