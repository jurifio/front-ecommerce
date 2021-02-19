<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooException;

/**
 * Class CProductSizeSoldDay
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 *
 * @property CProduct $product
 * @property CShop $shop
 * @property CDirtyProduct $dirtyProduct
 * @property CProductSku $productSku
 * @property CProductSizeGroup $productSizeGroup
 * @property CDirtySku $dirtySku
 */
class CProductSizeSoldDay extends AEntity
{
    protected $entityTable = 'ProductSoldDay';
    protected $primaryKeys = ['productId', 'productVariantId','productSizeId','shopId','day','month','year'];

}