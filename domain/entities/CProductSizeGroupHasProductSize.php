<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductSizeGroupHasProductSize
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
 * @property CProductSizeGroup $productSizeGroup
 * @property CProductSize $productSize
 */
class CProductSizeGroupHasProductSize extends AEntity
{
    protected $entityTable = 'ProductSizeGroupHasProductSize';
    protected $primaryKeys = ['productSizeGroupId','productSizeId','position'];
	protected $isCacheable = false;

    /**
     * @return bool
     */
    public function isProductSizeCorrespondenceDeletable()
    {
        $duplicateSizes = $this->productSizeGroup->productSizeGroupHasProductSize->
                findByKey('productSizeId', $this->productSizeId);

        if (count($duplicateSizes) > 1) return true;
        return count($this->getProductCorrespondences()) === 0;
    }

    public function getProductCorrespondences() {
        $sql = "SELECT psk.productId, psk.productVariantId, psk.productSizeId, psk.productSizeId,psk.shopId, p.productSizeGroupId
            FROM ProductSizeGroupHasProductSize psghps
              JOIN ProductSizeGroup psg ON psghps.productSizeGroupId = psg.id
              JOIN ProductSize ps ON psghps.productSizeId = ps.id
              JOIN Product p ON psg.id = p.productSizeGroupId
              JOIN ProductSku psk
                ON p.id = psk.productId AND p.productVariantId = psk.productVariantId AND ps.id = psk.productSizeId
            WHERE psghps.productSizeId = ? AND psghps.productSizeGroupId = ?";
        $res = \Monkey::app()->dbAdapter->query($sql, [$this->productSizeId, $this->productSizeGroupId])->fetchAll();
        return $res;
    }
}