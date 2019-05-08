<?php

namespace bamboo\ecommerce\offline;

use bamboo\core\jobs\ACronJob;

class CBuildSearchIndex extends ACronJob {

	public function run($args = null)
	{
		$this->report("Run", "Init Build Search Index");
		$truncate = "TRUNCATE table ProductSearch;";

		$this->report("Run", "Trunc table");
		$this->app->dbAdapter->query($truncate, [], true);
		
		$populate = "INSERT INTO ProductSearch 
                        SELECT
                          NULL,
                          p.id                                  AS productId,
                          p.productVariantId                    AS productVariantId,
                          CONCAT(p.id, '-', p.productVariantId) AS productCode,
                          l.id                                  AS langId,
                          pb.name                               AS productBrandName,
                          pcg.name                              AS productColorName,
                          ifnull(pn.name,'')                    AS productName,
                          ifnull(pd.description,'')             AS productDescription,
                          p.itemno                              AS productItemno,
                          group_concat(DISTINCT pct.path) as productCategory
                        FROM Product p
                          JOIN ProductBrand pb ON p.productBrandId = pb.id
                          JOIN Lang l
                          JOIN ProductColorGroupTranslation pcg ON pcg.productColorGroupId = p.productColorGroupId AND
                                                                   l.id = pcg.langId
                          JOIN ProductHasProductCategory phpc ON p.id = phpc.productId AND p.productVariantId = phpc.productVariantId
                          JOIN
                          (SELECT group_concat(DISTINCT pct.name ORDER BY parent.lft) as path, node.id, langId
                           FROM ProductCategory node
                              JOIN ProductCategory parent ON parent.id != 1 AND
                                                             node.lft BETWEEN parent.lft AND parent.rght
                              JOIN ProductCategoryTranslation pct ON parent.id = pct.productCategoryId
                            GROUP BY node.id, langId
                          ) pct on phpc.productCategoryId = pct.id AND pct.langId = l.id
                        LEFT JOIN ProductNameTranslation pn ON p.id = pn.productId AND
                                                                 p.productVariantId = pn.productVariantId AND
                                                                 l.id = pn.langId
                        LEFT JOIN ProductDescriptionTranslation pd ON p.id = pd.productId AND
                                                                        p.productVariantId = pd.productVariantId AND
                                                                        l.id = pd.langId
                        GROUP BY productId, productVariantId, langId";
		$this->report("Run", "Fill Table");
		$this->app->dbAdapter->query($populate, [],true);

		$this->report("Run", "Reset Cache");
		$this->app->cacheService->getCache('widgets')->flush();

		$this->report("Run", "Done building search index");
	}
}