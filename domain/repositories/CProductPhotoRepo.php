<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductPhoto;

/**
 * Class CProductPhotoRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 31/05/2017
 * @since 1.0
 */
class CProductPhotoRepo extends ARepo
{
    /**
     * @return mixed
     */
    public function listByProduct()
    {
        $args = $this->app->router->getMatchedRoute()->getComputedFilters();
        $sql = "SELECT DISTINCT productPhotoId AS id FROM ProductHasProductPhoto WHERE productId = :productId AND productVariantId = :productVariantId";
        return $this->em()->findBySql($sql, array("productId" => $args['item'], "productVariantId" => $args['variant']));
    }

    /**
     * @param CProduct $product
     * @param $size
     * @param $order
     * @return \bamboo\core\db\pandaorm\entities\AEntity|null
     */
    public function getPhotoForProductSizeOrder(CProduct $product, $size, $order)
    {
        $sql = "SELECT id 
                FROM ProductHasProductPhoto phpp JOIN 
                  ProductPhoto pp ON phpp.productPhotoId = pp.id
                WHERE phpp.productId = ? AND 
                      phpp.productVariantId = ? AND 
                      pp.size = ? AND 
                      pp.`order` = ?";
        return $this->em()->findBySql($sql, [$product->id, $product->productVariantId, $size, $order])->getFirst();
    }

    /**
     * @param $productId
     * @param $productVariantId
     * @param $productSizeId
     * @return CProductPhoto
     */
    public function getPhotoFromSkuSize($productId, $productVariantId, $productSizeId)
    {
        $sql = "SELECT DISTINCT P.id FROM ProductHasProductPhoto SP, ProductPhoto P WHERE SP.productId =:productId AND SP.productVariantId =:productVariantId AND size =:photoSize LIMIT 0,1";
        return $this->em()->findBySql($sql, array("productId" => $productId, "productVariantId" => $productVariantId, "productSizeId" => $productSizeId))->getFirst();
    }
}
