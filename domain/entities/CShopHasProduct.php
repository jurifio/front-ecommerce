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
 */
class CShopHasProduct extends AEntity
{
    protected $entityTable = 'ShopHasProduct';
    protected $primaryKeys = ['productId', 'productVariantId', 'shopId'];

    /**
     * @param $value
     * @param $price
     * @param int $salePrice
     */
    public function updatePrices($value, $price, $salePrice = 0)
    {
        $value = str_replace(',', '.', $value);
        $price = str_replace(',', '.', $price);
        $salePrice = ($salePrice) ? str_replace(',', '.', $salePrice) : 0;
        $this->price = $price;
        $this->salePrice = $salePrice;
        $this->value = $value;
        $this->update();

        //aggiorno gli sku
        foreach ($this->product->productSku as $v) {
            if ($this->shopId == $v->shopId) {
                $v->value = $value;
            }
            if ($price < $v->price) {
                $v->price = $price;
            }
            if ($salePrice) $v->salePrice = $salePrice;
            $v->update();
        }
    }

    public function setReleasedate($value)
    {
        if ((array_key_exists('releaseDate', $this->fields)) && (!empty($this->fields['releaseDate']))) {
            throw new BambooException('releaseDate can\'t be updated');
        } else {
            $this->fields['releaseDate'] = $value;
        }
    }

    public function setProductSizeGroupId($value)
    {
        $this->fields['productSizeGroupId'] = $value;
        if (!isset($this->product->productSizeGroupId) || empty($this->product->productSizeGroupId)) {
            $this->product->productSizeGroupId = $value;
            $this->product->writeHistory('Update ProductSize', 'ProductSize update from ShopHasProduct ' . $this->printId());
            $this->product->update();
        }
    }

    public function insertPrices($value, $price, $salePrice = 0)
    {

        $this->price = $price;
        $this->salePrice = $salePrice;
        $this->value = $value;
        $this->insert();

        //aggiorno gli sku
        foreach ($this->product->productSku as $v) {
            if ($this->shopId == $v->shopId) {
                $v->value = $value;
            }
            if ($price < $v->price) {
                $v->price = $price;
            }
            if ($salePrice) $v->salePrice = $salePrice;
            $v->update();
        }
    }
}