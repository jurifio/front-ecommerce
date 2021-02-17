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
 * @property CProductSizeGroup $productSizeGroup
 * @property CDirtySku $dirtySku
 */
class CDictionaryMonth extends AEntity
{
    protected $entityTable = 'DictionaryMonth';
    protected $primaryKeys = ['id'];

}