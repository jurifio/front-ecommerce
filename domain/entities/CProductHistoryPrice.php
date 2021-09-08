<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductBrand
 * @package bamboo\app\domain\entities
 *
 *
 * @property CProductBrandHasPrestashopManufacturer $productBrandHasPrestashopManufacturer
 * @property CProductBrandTranslation $productBrandTranslation
 *
 */
class CProductHistoryPrice extends AEntity
{
    protected $entityTable = 'ProductHistoryPrice';
	protected $primaryKeys = ['id','productId','productVariantId','productSizeId','shopId'];
} 