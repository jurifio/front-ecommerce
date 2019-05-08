<?php

namespace bamboo\ecommerce\offline\productsync\twinsync;


/**
 * Class CAlignCartechini
 * @package bamboo\ecommerce\offline\productsync\twinsync
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CAlignCartechini extends AAlignSites
{
	public function fillProduct()
	{
		$sql = "insert INTO `" . $this->destination->getComponentOption('name') . "`.Product 
							 SELECT p.* FROM `" . $this->origin->getComponentOption('name') . "`.Product p, `" . $this->origin->getComponentOption('name') . "`.ShopHasProduct sp WHERE p.id = sp.productId AND p.productVariantId = sp.productVariantId AND sp.shopId = ?
							 on duplicate key update 
							 `" . $this->destination->getComponentOption('name') . "`.Product.itemno = p.itemno,
							 `" . $this->destination->getComponentOption('name') . "`.Product.dummyPicture = p.dummyPicture,
							 `" . $this->destination->getComponentOption('name') . "`.Product.productSeasonId = p.productSeasonId,
							 `" . $this->destination->getComponentOption('name') . "`.Product.productBrandId = p.productBrandId,
							 `" . $this->destination->getComponentOption('name') . "`.Product.productColorGroupId = p.productColorGroupId,
							 `" . $this->destination->getComponentOption('name') . "`.Product.productStatusId = p.productStatusId,
							 `" . $this->destination->getComponentOption('name') . "`.Product.productSizeGroupId = p.productSizeGroupId,
							 `" . $this->destination->getComponentOption('name') . "`.Product.productSheetPrototypeId = p.productSheetPrototypeId";
		$this->admin->query($sql,
			[$this->getShop()->id]);
		$this->report('Work', 'Done query ', $sql);
	}

	public function fillProductHasTag()
	{
		$sql = "insert ignore INTO `".$this->destination->getComponentOption('name')."`.ProductHasTag 
							 select p.* from `".$this->origin->getComponentOption('name')."`.ProductHasTag p, `".$this->origin->getComponentOption('name')."`.ShopHasProduct sp where p.productId = sp.productId and p.productVariantId = sp.productVariantId AND sp.shopId = ?";
		$this->report('Work','Done query ',$sql);
		$this->admin->query($sql,[$this->getShop()->id]);
	}

    public function fillProductSku()
    {
        $sql = "INSERT INTO " . $this->destination->getComponentOption('name') . ".ProductSku (productId, 
																							productVariantId, 
																							productSizeId,
																							shopId,
																							shippingBoxId,
																							currencyId,
																							barcode,
																							stockQty,
																							padding,
																							value,
																							price,
																							salePrice)
							 SELECT sps.productId, 
									sps.productVariantId, 
									sps.productSizeId,
									sps.shopId,
									sps.shippingBoxId,
									sps.currencyId,
									sps.barcode,
									sps.stockQty,
									sps.padding,
									sps.value,
									sps.price,
									sps.salePrice
					         FROM  " . $this->origin->getComponentOption('name') . ".ProductSku sps
					         WHERE sps. shopId = ? ON DUPLICATE KEY 
					         UPDATE " . $this->destination->getComponentOption('name') . ".ProductSku.stockQty = sps.stockQty,".
                                        $this->destination->getComponentOption('name') . ".ProductSku.padding = sps.padding";
        $this->admin->query($sql,
            [$this->getShop()->id]);
        $this->report('Work', 'Done query ', $sql);
    }

    public function fillProductSizeGroup() {
        $sql = "REPLACE INTO " . $this->destination->getComponentOption('name') . ".ProductSizeGroup (id,macroName,name,locale) 
							 SELECT psg.id, psmg.name, psg.name, psg.locale
					         FROM  " . $this->origin->getComponentOption('name') . ".ProductSizeGroup psg
                                JOIN " . $this->origin->getComponentOption('name') . ".ProductSizeMacroGroup psmg on psg.productSizeMacroGroupId = psmg.id";
        $this->admin->query($sql,
            [$this->getShop()->id]);
        $this->report('Work', 'Done query ', $sql);
    }
}