<?php

namespace bamboo\ecommerce\offline;

use bamboo\core\jobs\ACronJob;

/**
 * Class CUnpublishProducts
 * @package bamboo\ecommerce\offline
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 01/06/2016
 * @since 1.0
 */
class CUnpublishProducts extends ACronJob
{

	public function run($args = null)
	{
		$this->report("Run", "Init CUnpublishProducts");

		$products = \Monkey::app()->repoFactory->create('Product')->em()->findBySql("
                                                                        SELECT
																		p.id,
																		p.productVariantId
																		FROM (Product p
																			JOIN ProductStatus ps ON p.productStatusId = ps.id)
																			JOIN ProductSku pps ON p.id = pps.productId AND p.productVariantId = pps.productVariantId
																		WHERE ps.isVisible = 1
																		GROUP BY p.id, p.productVariantId
																		HAVING sum(IFNULL(stockQty, 0)) = 0", []);

		foreach ($products as $product) {
			$product->productStatusId = 12;
			$product->update();
			$this->report('Run','Unpublished product',$product->printId());
		}
		$this->report('Run','Republish products');
		$products = \Monkey::app()->repoFactory->create('Product')->em()->findBySql("
                                                                        SELECT
																		p.id,
																		p.productVariantId
																		FROM (Product p
																			JOIN ProductStatus ps ON p.productStatusId = ps.id)
																			JOIN ProductSku pps ON p.id = pps.productId AND p.productVariantId = pps.productVariantId
																		WHERE ps.id = 12
																		GROUP BY p.id, p.productVariantId
																		HAVING sum(IFNULL(stockQty, 0)) > 0", []);

		foreach ($products as $product) {
			$product->productStatusId = 5;
			$product->update();
			$this->report('Run','Re upped product',$product->printId());
		}



		$this->report("Run", "Done CUnpublishProducts");
	}
}