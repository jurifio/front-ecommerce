<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductHasTag
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
 *
 *
 * @property CTag $tag
 * @property CProduct $product
 *
 *
 */
class CProductHasTag extends AEntity
{
	protected $entityTable = 'ProductHasTag';
	protected $primaryKeys = ['productId', 'productVariantId','tagId'];
}