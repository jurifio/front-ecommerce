<?php

namespace bamboo\domain\entities;

use bamboo\core\application\AApplication;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\utils\amazonPhotoManager\S3Manager;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CProduct
 * @package bamboo\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 01/11/2014
 * @since 0.0.1
 *
 * @property CProductBrand $productBrand
 * @property CProductSeason $productSeason
 * @property CProductVariant $productVariant
 * @property CObjectCollection $productSheetActual
 * @property CProductSheetPrototype $productSheetPrototype
 * @property CObjectCollection $productSku
 * @property CObjectCollection $productPhoto
 * @property CObjectCollection $productColorGroup
 * @property CObjectCollection $productCategoryTranslation
 * @property CObjectCollection $productCategory
 * @property CObjectCollection $shop
 * @property CObjectCollection $productNameTranslation
 * @property CProductSizeGroup $productSizeGroup
 * @property CObjectCollection $tag
 * @property CProductStatus $productStatus
 * @property CShopHasProduct $shopHasProduct
 * @property CObjectCollection $productDescriptionTranslation
 */
class COrderLineFriendPaymentStatus extends AEntity
{
	protected $entityTable = 'OrderLineFriendPaymentStatus';
	protected $primaryKeys = ['id'];
}