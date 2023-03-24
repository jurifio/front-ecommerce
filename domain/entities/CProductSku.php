<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductSku
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
 * @property CProduct $product
 * @property CProductSize $productSize
 * @property CShopHasProduct $shopHasProduct
 * @property CCartLine $cartLine
 * @property CShop $shop
 * @property CObjectCollection $dirtySku
 * @property CProductEan $productEan
 *
 */
class CProductSku extends AEntity
{
    protected $entityTable = 'ProductSku';
    protected $primaryKeys = ['productId','productVariantId', 'productSizeId', 'shopId']; //Shop?

    /**
     * @return string
     */
    public function froze() {
        $r = [];
        foreach($this->ownersFields as $field){
            if(!isset($this->$field) || is_null($this->$field)) {
                $r[$field] = null;
            } else {
                $r[$field] = $this->$field;
            }
        }
        return json_encode($r);
    }

    /**
     * @return bool|string
     */
    public function printPublicSku()
    {
        try{
            return $this->getPublicProductSku()->printId();
        } catch(\Throwable $e){
            return false;
        }
    }

    /**
     * @return CProductSize|null
     */
    public function getPublicSize()
    {
        return \Monkey::app()->repoFactory->create('ProductSize')->getPublicProductSizeForSku($this);
    }

    /**
     * @return mixed
     */
    public function getPublicProductSku()
    {
        return \Monkey::app()->repoFactory->create('ProductPublicSku')->findPublicSkuForProductSku($this);
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
     * @return bool|string
     */
    public function printFullSku()
    {
        try{
            return $this->productId.'-'.$this->productVariantId.'-'.$this->productSizeId.'-'.$this->shopId;
        } catch(\Throwable $e){
            return false;
        }
    }

    /**
     * @return string
     */
    public function getPrice()
    {
        return number_format($this->fields['price'],2,'.','');
    }

    /**
     * @return string
     */
    public function getSalePrice()
    {
        return number_format($this->fields['salePrice'],2,'.','');
    }

    /**
     * @param bool $suppressEvent
     * @return int
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     * @throws \bamboo\core\exceptions\RedPandaException
     */
    public function update($suppressEvent = false)
    {
        if($this->isChanged()) $suppressEvent = true;
        $x = parent::update();
        if(!$suppressEvent) {
            \Monkey::app()->eventManager->triggerEvent('productSku.change',['productSkuId'=>$this->printId()]);
        }
        return $x;

    }

    /**
     * @param bool $lowerValue
     * @param bool $withQty
     * @return null
     */
    public function findRightDirtySku($lowerValue = true, $withQty = true)
    {
        $lower = null;
        foreach ($this->dirtySku as $dirtySku) {
            if($withQty && $dirtySku->qty < 1) continue;
            if(is_null($lower)) {
                $lower = $dirtySku;

            } elseif($lowerValue) {
                $actuarLower = is_null($lower->value) ? $lower->dirtyProduct->value : $lower->value;
                $newLower = is_null($dirtySku->value) ? $dirtySku->dirtyProduct->value : $dirtySku->value;
                if(!is_null($newLower) && $newLower < $actuarLower) {
                    $lower = $dirtySku;
                }
            } elseif(!is_null($lower)) {
                break;
            }
        }
        return $lower;
    }

    /**
     * @return mixed
     */
    public function findRightValue() {
        /** @var CDirtySku $dirtySku */
        $dirtySku = $this->findRightDirtySku();
        if($dirtySku && $dirtySku->value) return $dirtySku->value;
        else return $this->value;
    }

    /**
     * @return string
     */
    public function getExternalId() {
        if(!is_null($dirtySku = $this->findRightDirtySku())) {
            if($dirtySku->extSkuId && !empty($dirtySku->extSkuId)) {
                return $dirtySku->extSkuId;
            } elseif($dirtySku->barcode && !empty($dirtySku->barcode)) {
                return $dirtySku->barcode;
            } elseif($dirtySku->barcode && !empty($dirtySku->dirtyProduct->extId)) {
                return $dirtySku->dirtyProduct->extId.' '.$dirtySku->dirtyProduct->var.' '.$dirtySku->size;
            } else {
                return $dirtySku->dirtyProduct->itemno.' '.$dirtySku->dirtyProduct->var.' '.$dirtySku->size;
            }
        } elseif($this->shopHasProduct->extId && !empty($this->shopHasProduct->extId)) {
            return $this->shopHasProduct->extId.' '.$this->productSize->name;
        } else return "";
    }
}