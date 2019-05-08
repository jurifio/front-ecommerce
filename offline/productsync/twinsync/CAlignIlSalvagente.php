<?php

namespace bamboo\ecommerce\offline\productsync\twinsync;


/**
 * Class CAlignIlSalvagente
 * @package bamboo\front\ecommerce\offline\productsync\twinsync
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, 19/04/2016
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CAlignIlSalvagente extends AAlignSites
{
    /**
     *
     */
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
					         FROM   " . $this->origin->getComponentOption('name') . ".ProductSku sps
					         WHERE sps.shopId = ? ON DUPLICATE KEY 
					         UPDATE " . $this->destination->getComponentOption('name') . ".ProductSku.stockQty = sps.stockQty,".
										$this->destination->getComponentOption('name') . ".ProductSku.padding = sps.padding";
		$rows = $this->admin->query($sql,
			[$this->getShop()->id])->countAffectedRows();
		$this->report('Work', 'Done query ProductSku, rows: '.$rows, $sql);
	}

    /**
     *
     */
	public function fillProduct()
	{
		$sql = "insert INTO " . $this->destination->getComponentOption('name') . ".Product 
							 SELECT p.* 
							 FROM " . $this->origin->getComponentOption('name') . ".Product p,
							  " . $this->origin->getComponentOption('name') . ".ShopHasProduct sp 
							  WHERE p.id = sp.productId AND 
                                    p.productVariantId = sp.productVariantId AND 
                                    sp.shopId = ?
							 on duplicate key update 
							 " . $this->destination->getComponentOption('name') . ".Product.itemno = p.itemno,
							 " . $this->destination->getComponentOption('name') . ".Product.dummyPicture = p.dummyPicture,
							 " . $this->destination->getComponentOption('name') . ".Product.productSeasonId = p.productSeasonId,
							 " . $this->destination->getComponentOption('name') . ".Product.productBrandId = p.productBrandId,
							 " . $this->destination->getComponentOption('name') . ".Product.productSizeGroupId = p.productSizeGroupId,
							 " . $this->destination->getComponentOption('name') . ".Product.productSheetPrototypeId = p.productSheetPrototypeId";
		$rows = $this->admin->query($sql,
			[$this->getShop()->id])->countAffectedRows();
		$this->report('Work', 'Done query Product, rows: '.$rows, $sql);
	}
}