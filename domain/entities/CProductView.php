<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\repositories\CRepo;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\utils\slugify\CSlugify;
use bamboo\domain\repositories\CDocumentRepo;
use bamboo\domain\repositories\CProductHistoryRepo;

/**
 * Class CProductView
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
 * @property CObjectCollection $productPublicSku
 * @property CObjectCollection $productPhoto
 * @property CProductColorGroup $productColorGroup
 * @property CObjectCollection $productCategoryTranslation
 * @property CObjectCollection $productCategory
 * @property CObjectCollection $shop
 * @property CObjectCollection $productNameTranslation
 * @property CProductSizeGroup $productSizeGroup
 * @property CObjectCollection $tag
 * @property CProductStatus $productStatus
 * @property CObjectCollection $shopHasProduct
 * @property CObjectCollection $productDescriptionTranslation
 * @property CObjectCollection $marketplaceAccountHasProduct
 * @property CObjectCollection $cartLine
 * @property CObjectCollection $orderLine
 * @property CObjectCollection $shooting
 * @property CProductCardPhoto $productCardPhoto
 * @property CObjectCollection $productHasTag
 * @property CObjectCollection $productEan
 * @property CObjectCollection $product
 * @property CPrestashopHasProduct $prestashopHasProduct
 * @property CProductBrandHasPrestashopManufacturer $productBrandHasPrestashopManufacturer
 * @property CProductColorGroupHasPrestashopColorOption $productColorGroupHasPrestashopColorOption
 *
 *
 */
class CProductView extends AEntity
{
    protected $entityTable = 'ProductView';
    protected $primaryKeys = ["id"];



}