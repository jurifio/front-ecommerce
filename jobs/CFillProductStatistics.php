<?php
namespace bamboo\ecommerce\jobs;

use bamboo\core\db\pandaorm\adapter\CMySQLAdapter;
use bamboo\core\jobs\ACronJob;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CCleanOrders
 * @package bamboo\blueseal\jobs
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
class CFillProductStatistics extends ACronJob
{
    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $pstR = \Monkey::app()->repoFactory->create('ProductStatistics');

            $sql = "INSERT into ProductStatistics
                    SELECT
                      p.id                                              AS productId,
                      p.productVariantId                                AS productVariantId,
                      shp.shopId                                        AS shopId,
                      ifnull((Select sum(CAST(ps.stockQty as int) - cast(ps.padding as int)) from ProductSku ps where
                        shp.productId = ps.productId AND
                        shp.productVariantId = ps.productVariantId AND
                        shp.shopId = ps.shopId),0) AS qty,
                      isVisible                                         AS isVisible,
                      IF(count(distinct psa.productDetailId) > 1, 1, 0)          AS hasDetails,
                      IF(p.dummyPicture = 'bs-dummy-16-9.png', 0, 1)    AS hasPhotos,
                      if(shp.price > 0, 1, 0)                           AS hasPrices,
                      p.isOnSale                                        AS isOnSale,
                      current_date                                      AS date
                    FROM
                      ShopHasProduct shp
                      JOIN Shop s ON shp.shopId = s.id
                      JOIN Product p ON p.id = shp.productId AND
                                        p.productVariantId = shp.productVariantId
                      JOIN ProductStatus pst
                        ON p.productStatusId = pst.id
                    
                      LEFT JOIN ProductSheetActual psa
                        ON p.id = psa.productId AND
                           p.productVariantId = psa.productVariantId
                    WHERE pst.id NOT IN (8, 13) and s.isActive = 1
                    GROUP BY shp.productId, shp.productVariantId, shp.shopId";

            /** @var CMySQLAdapter $dba */
            $res = \Monkey::app()->dbAdapter->query($sql, [])->fetchAll();
            $count = \Monkey::app()->dbAdapter->countAffectedRows();
            $this->report('Registration Effectuated','Inserted '.$count.' as today product');

        $this->report('Registration Not Effectuated','Operation already happened today');
    }
}