<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooException;

/**
 * Class CShop
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 *
 * @property CProduct $product
 * @property CShop $shop
 * @property CDirtyProduct $dirtyProduct
 * @property CProductSku $productSku
 */
class CShopHasProductPrice extends AEntity
{
    protected $entityTable = 'ShopHasProductPrice';
    protected $primaryKeys = ['productId', 'productVariantId', 'shopId','shopIdDestination'];



}