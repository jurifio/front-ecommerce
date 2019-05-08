<?php


namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CProductSize;
use bamboo\domain\entities\CProductSizeGroup;
use bamboo\domain\entities\CProductSku;
use bamboo\traits\TCatalogRepoFunctions;

/**
 * Class CProductRepo
 * @package bamboo\app\domain\repositories
 */
class CProductSizeRepo extends ARepo
{
    use TCatalogRepoFunctions;

    public function listByAppliedFilters(array $limit, array $orderBy,array $params){
        $sql = "select distinct size as id from ({$this->catalogInnerQuery}) t {$this->orderBy($orderBy)} {$this->limit($limit)} ";
        $sizes = $this->em()->findBySql($sql,$this->prepareParams($params));
        $sizes->reorder('name');
        return $sizes;
    }

    /**
     * @param CProductSizeGroup $productSizeGroup
     * @param CProductSize $productSize
     * @return CProductSize|null
     */
    public function findStandardFor(CProductSizeGroup $productSizeGroup, CProductSize $productSize)
    {
        try {
            if ($productSizeGroup->locale == 'ST') {
                return $productSize->name;
            } else {
                $sql = "SELECT psghps1.productSizeId 
                        FROM ProductSizeGroup psg1, 
                              ProductSizeGroupHasProductSize psghps1
                        WHERE psg1.id = psghps1.productSizeGroupId
                        AND psg1.locale = 'ST'
                        AND (psghps1.position, psg1.productSizeMacroGroupId) = (
                            SELECT position,psg2.productSizeMacroGroupId
                            FROM ProductSizeGroup psg2,
                                 ProductSizeGroupHasProductSize psghps2,
                                 ProductSize ps
                            WHERE psg2.id = psghps2.productSizeGroupId
                                  AND psghps2.productSizeId = ps.id
                                  AND psg2.id = ?
                                  AND ps.id = ?)";
                $standardProductSize = \Monkey::app()->repoFactory->create('ProductSize')->em()->findBySql($sql, [$productSizeGroup->id, $productSize->id]);
                return $standardProductSize->getFirst();
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getPublicProductSizeForSku(CProductSku $productSku) {
        $sql = "SELECT psghpsPub.productSizeId as id
                FROM
                  ProductSku psk
                  JOIN Product p ON psk.productId = p.id AND psk.productVariantId = p.productVariantId
                  JOIN ShopHasProduct shp
                    ON (psk.productId, psk.productVariantId, psk.shopId) = (shp.productId, shp.productVariantId, shp.shopId)
                  JOIN ProductSizeGroup psgPub ON p.productSizeGroupId = psgPub.id
                  JOIN ProductSizeGroup psgPri ON shp.productSizeGroupId = psgPri.id and psgPub.productSizeMacroGroupId = psgPri.productSizeMacroGroupId
                  JOIN ProductSizeGroupHasProductSize psghpsPub ON psgPub.id = psghpsPub.productSizeGroupId
                  JOIN ProductSizeGroupHasProductSize psghpsPri
                    ON psgPri.id = psghpsPri.productSizeGroupId AND psghpsPri.position = psghpsPub.position and psghpsPri.productSizeId = psk.productSizeId
                WHERE
                  psk.productId = :productId AND
                  psk.productVariantId = :productVariantId AND
                  psk.productSizeId = :productSizeId AND
                  shp.shopId = :shopId
                  LIMIT 1";
        return $this->findOneBySql($sql,$productSku->getIds());
    }
}
