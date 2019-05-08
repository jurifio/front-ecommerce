<?php


namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CProductPublicSku;
use bamboo\domain\entities\CProductSizeGroup;
use bamboo\domain\entities\CProductSku;

/**
 * Class CProductSkuRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CProductPublicSkuRepo extends ARepo
{
    /**
     * Ritorna tutti gli sku a partire da un prodotto, pescando i parametri dai parametri
     * @return \bamboo\core\base\CObjectCollection
     */
    public function listByProduct()
    {
        $sql = "SELECT DISTINCT pps.productId, pps.productVariantId, pps.productSizeId 
                FROM Product p JOIN ProductSizeGroup psg ON p.productSizeGroupId = psg.id
                  JOIN ProductSizeGroupHasProductSize psghps ON psg.id = psghps.productSizeGroupId 
                  JOIN ProductPublicSku pps ON p.id = pps.productId AND p.productVariantId = pps.productVariantId AND pps.productSizeId = psghps.productSizeId
                WHERE p.id = ? AND p.productVariantId = ?
                ORDER BY psghps.position ASC";
        $args = $this->app->router->getMatchedRoute()->getComputedFilters();
        return $this->em()->findBySql($sql, array($args['item'], $args['variant']));
    }

    /**
     * Trova l'id per un ProductPublicSku alternativo
     * @param CProductPublicSku $productPublicSku
     * @param CProductSizeGroup $productSizeGroup
     * @return array
     */
    public function findPublicSkuIdsForDifferentProductSizeGroup(CProductPublicSku $productPublicSku, CProductSizeGroup $productSizeGroup)
    {
        $sql = "SELECT DISTINCT p.id,p.productVariantId,psghps2.productSizeId
                FROM ProductPublicSku pps 
                  JOIN Product p ON pps.productId = p.id AND pps.productVariantId = p.productVariantId
                  JOIN ProductSizeGroup psg ON p.productSizeGroupId = psg.id
                  JOIN ProductSizeGroupHasProductSize psghps ON psg.id = psghps.productSizeGroupId AND psghps.productSizeId = pps.productSizeId
                  JOIN ProductSizeGroup psg2 ON psg2.productSizeMacroGroupId = psg.productSizeMacroGroupId
                  JOIN ProductSizeGroupHasProductSize psghps2 ON psg2.id = psghps2.productSizeGroupId AND psghps.position = psghps2.position
                WHERE
                  pps.stockQty > 0 AND
                  pps.productId = :productId AND 
                  pps.productVariantId = :productVariantId AND
                  pps.productSizeId = :productSizeId AND 
                  psg2.id = :productSizeGroupId";
        return \Monkey::app()->dbAdapter->query($sql, $productPublicSku->getIds() + ['productSizeGroupId' => $productSizeGroup->id])->fetchAll()[0] ?? null;
    }

    /**
     * @param CProductSku $productSku
     * @return CProductPublicSku|null
     */
    public function findPublicSkuForProductSku(CProductSku $productSku)
    {
        $sql = "SELECT pps.productId,
                        pps.productVariantId, 
                       pps.productSizeId
                FROM
                  ProductSku psk
                  JOIN Product p ON (psk.productId, psk.productVariantId) = (p.id, p.productVariantId)
                  JOIN ShopHasProduct shp
                    ON (psk.productId, psk.productVariantId, psk.shopId) = (shp.productId, shp.productVariantId, shp.shopId)
                  JOIN ProductSizeGroup psgPri ON shp.productSizeGroupId = psgPri.id 
                  JOIN ProductSizeGroup psgPub ON p.productSizeGroupId = psgPub.id AND psgPub.productSizeMacroGroupId = psgPri.productSizeMacroGroupId
                  JOIN ProductSizeGroupHasProductSize psghpsPri
                      ON psgPri.id = psghpsPri.productSizeGroupId AND psghpsPri.productSizeId = psk.productSizeId
                  JOIN ProductSizeGroupHasProductSize psghpsPub ON psgPub.id = psghpsPub.productSizeGroupId AND psghpsPri.position = psghpsPub.position
                  JOIN ProductPublicSku pps ON (p.id, p.productVariantId) = (pps.productId, pps.productVariantId) AND psghpsPub.productSizeId = pps.productSizeId
                WHERE
                  psk.productId = :productId AND
                  psk.productVariantId = :productVariantId AND
                  psk.productSizeId = :productSizeId AND
                  psk.shopId = :shopId
                  LIMIT 1";
        return $this->findOneBySql($sql, $productSku->getIds());
    }
}
