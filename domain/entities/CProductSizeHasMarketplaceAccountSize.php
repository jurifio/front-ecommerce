<?php

namespace bamboo\domain\entities;

use bamboo\core\application\AApplication;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\utils\amazonPhotoManager\S3Manager;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CProductColorGroupHasMarketplaceColorGroup
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 30/03/2021
 * @since 1.0
 */
class CProductSizeHasMarketplaceAccountSize extends AEntity
{
    protected $entityTable = 'ProductSizeHasMarketplaceAccountSize';
    protected $primaryKeys = ['marketplaceId','marketplaceAccountId','marketplaceSizeId','productSizeId'];
}