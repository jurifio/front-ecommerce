<?php

namespace bamboo\traits;

/**
 * Trait TObjectMerge
 * @package bamboo\core\traits
 */
trait TCatalogRepoFunctions
{
    protected $catalogInnerQuery = "
        SELECT
         `Product`.`id`                                      AS `product`,
         `Product`.`productVariantId`                        AS `variant`,
         `ProductHasTag`.`tagId`                             AS `tag`,
         `Product`.`sortingPriorityId`                       AS `productPriority`,
         `Product`.`productBrandId`                          AS `brand`,
         `ProductPublicSku`.`productSizeId`                  AS `size`,
         ProductPublicSku.price                              AS price,
         `Product`.`productColorGroupId`                     AS `color`,
         `ProductHasProductCategory`.`productCategoryId`     AS `category`,
         Product.creationDate                                AS creation,
         tt.sortingPriorityId                                AS sortingPriority
       FROM
         `Product`
         JOIN `ProductHasTag` ON (`ProductHasTag`.`productId` = `Product`.`id`) AND
                                 (`ProductHasTag`.`productVariantId` = `Product`.`productVariantId`)
         JOIN `ProductStatus` ON (`Product`.`productStatusId` = `ProductStatus`.`id`)
         JOIN `ProductPublicSku` ON (`Product`.`id` = `ProductPublicSku`.`productId`) AND
                              (`Product`.`productVariantId` = `ProductPublicSku`.`productVariantId`)
         JOIN `ProductHasProductCategory` ON (`ProductHasProductCategory`.`productId` = `Product`.`id`) AND
                                             (`ProductHasProductCategory`.`productVariantId` =
                                              `Product`.`productVariantId`) AND
                                             ProductHasProductCategory.productCategoryId != 1
         JOIN (SELECT a.id
               FROM ProductCategory a, ProductCategory b
               WHERE a.lft BETWEEN b.lft AND b.rght AND b.id = ifnull(:category, 1)) tc
           ON tc.id = ProductHasProductCategory.productCategoryId
         JOIN ProductHasTag pht2 ON (pht2.productId, pht2.productVariantId) = (Product.id,Product.productVariantId)
         JOIN Tag tt ON tt.id = pht2.tagId
       WHERE
         `ProductStatus`.`isVisible` = 1 AND
         `ProductHasProductCategory`.`productCategoryId` <> 1 AND
         `Product`.`qty` > 0 AND
         ProductHasTag.tagId = ifnull(:tag, ProductHasTag.tagId) AND
         ProductPublicSku.productSizeId = ifnull(:size, ProductPublicSku.productSizeId) AND
         Product.productColorGroupId = ifnull(:color, Product.productColorGroupId) AND
         Product.productBrandId = ifnull(:brand, Product.productBrandId)
        ORDER BY tt.sortingPriorityId";


    /**
     * @param array $params
     * @return array
     */
    public function prepareParams(array $params)
    {
        $fullParams = [
            ':category' => $params['category'] ?? null,
            ':tag' => $params['tag'] ?? null,
            ':size' => $params['size'] ?? null,
            ':color' => $params['color'] ?? null,
            ':brand' => $params['brand'] ?? null
        ];
        return $fullParams;
    }
}