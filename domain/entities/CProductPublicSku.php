<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductPublicSku
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
 * @property CProduct $product
 * @property CProductSize $productSize
 * @property CCartLine $cartLine
 */
class CProductPublicSku extends AEntity
{
    protected $entityTable = 'ProductPublicSku';
    protected $primaryKeys = ['productId','productVariantId', 'productSizeId'];
    protected $isCacheable = false;

    /**
     * @return string
     */
    public function printPublicSku()
    {
        return $this->printId();
    }

    /**
     * @return CProductSize|null
     * @deprecated
     */
    public function getPublicSize()
    {
        return $this->productSize;
    }

    /**
     * @return mixed
     */
	public function getActivePrice()
	{
		return ($this->salePrice < $this->price && $this->salePrice > 0 && $this->getIsOnSale()) ? $this->salePrice : $this->price;
	}

    /**
     * @return mixed
     */
	public function getIsOnSale()
    {
        return $this->product->isOnSale();
    }

    /**
     * @return string
     */
    public function getPrice()
    {
        return money_format("%.2n", $this->fields['price']);
    }

    /**
     * @return string
     */
    public function getSalePrice()
    {
        return money_format("%.2n", $this->fields['salePrice']);
    }

    /**
     * @param CShop|null $shop
     * @return CProductSku
     */
    public function getActualDisposableSku(CShop $shop = null) {
        return $this->getActualSku($shop,true);
    }

    public function getActualSku(CShop $shop = null, $onlyDisposable = false) {
        return \Monkey::app()->repoFactory->create('ProductSku')->findOneSkuFromPublicSku($this,$shop,$onlyDisposable);
    }
}