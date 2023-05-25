<?php
namespace bamboo\domain\repositories;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CUser;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CShopRepo
 * @package bamboo\domain\repositories
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 08/09/2016
 * @since 1.0
 */
class CShopRepo extends ARepo
{
    /**
     * @param CUser|null $user
     * @return array
     */
    public function getAutorizedShopsIdForUser(CUser $user = null): array
    {
        $shopIds = [];
        if (is_null($user)) $user = $this->app->getUser();
        if ($user->hasPermission('allShops') or $user->hasPermission("worker")) {
            foreach ($this->findAll() as $shop) {
                $shopIds[] = $shop->id;
            }
        } else if (!$user->hasPermission('allShops')) {
            foreach ($user->shop as $shop) {
                $shopIds[] = $shop->id;
            }
        }
        return $shopIds;
    }

    /**
     * @param CUser $user
     * @return CObjectCollection
     */
    public function getAutorizedShopsForUser(CUser $user = null)
    {
        $shops = new CObjectCollection();
        foreach ($this->getAutorizedShopsIdForUser($user) as $shopId) {
            $shops->add($this->findOne([$shopId]));
        }
        return $shops;
    }

    /**
     * @param $shopId
     * @return bool
     */
    public function shutdownFriend($shopId) {

        $shop = $this->findOneByStringId($shopId);
        if(is_null($shop->importer)) {
            /** @var CStorehouseOperationRepo $soR */
            $soR = \Monkey::app()->repoFactory->create('StorehouseOperation');
            $soR->AllSkusByFriendToZero($shopId);
        } else {
            $skus = \Monkey::app()->repoFactory->create('ProductSku')->findBySql('SELECT productId, productVariantId, shopId, productSizeId FROM ProductSku WHERE shopId = ? AND (stockQty > 0 || padding < 0)',[$shopId]);
            foreach($skus as $sku) {
                $sku->stockQty = 0;
                $sku->padding = 0;
                $sku->update();
            }
        }

        $shp = \Monkey::app()->repoFactory->create('Shop')->findOne([$shopId]);
        $shp->isActive = 0;
        $shp->update();

        foreach($shp->user as $v) {
            if($v->userHasRbacRole) $v->userHasRbacRole->delete();
        }
        return true;
    }

    /**
     * @param $shopId
     * @return bool
     */
    public function restartFriend($shopId) {
        /** @var CStorehouseOperationRepo $soR */
        $soR = \Monkey::app()->repoFactory->create('StorehouseOperation');
        $soR->resumeFriendFromZero($shopId);

        $shp = \Monkey::app()->repoFactory->create('Shop')->findOne([$shopId]);
        $shp->isActive = 1;
        $shp->update();

        $uhrR = \Monkey::app()->repoFactory->create('UserHasRbacRole');
        foreach($shp->user as $v) {
            $role = $uhrR->getEmptyEntity();
            $role->userId = $v->id;
            $role->rbacRoleId = 22;
            $role->insert();
        }

        return true;
    }

    /**
     * @return \bamboo\core\db\pandaorm\entities\IEntity|null
     */
    public function getMainShop(){
        $mainShopName = \Monkey::app()->repoFactory->create('Configuration')->findOneBy(['context' => 'core', 'name' => 'main-shop-name'])->value;
        return \Monkey::app()->repoFactory->create('Shop')->findOneBy(['name' => $mainShopName]);
    }

    /**
     * Conta i prodotti Attivi al momento attuale
     * @param $shopId
     * @return int
     */
    public function getActiveProductCountForShop($shopId)
    {
        $sql = "SELECT count(DISTINCT p.id,p.productVariantId) as conto 
                FROM ShopHasProduct shp 
                  JOIN Product p ON shp.productId = p.id AND shp.productVariantId = p.productVariantId
                  JOIN ProductStatus ps ON p.productStatusId = ps.id
                WHERE ps.isVisible = 1 and shopId = ?";
        $res = $this->app->dbAdapter->query($sql, [$shopId])->fetch();
        if($res) return $res['conto'];
        return 0;
    }

    /**
     * Restituisce una statistica dei prodotti attivi nel tempo, tra le date from e to per lo shop specificato
     * o per tutti gli shop, se non viene specificato alcuno shop
     * @param null $from
     * @param null $to
     * @return array, l'array è composto da "shopId": id dello shop , "data", "products": numero dei prodotti
     */
    public function getDailyActiveProductStatistics($from = null, $to = null, $shopId = null)
    {
        $sql = "SELECT shopId, date, count(distinct productId,productVariantId) as products 
                FROM ProductStatistics 
                where isVisible = 1 and 
                    shopId = ifnull(?,shopId) AND
                    date BETWEEN ifnull(?,date) and ifnull(?,date)
                GROUP BY shopId,date";
        $from = $from === null ? $from : STimeToolbox::DbFormattedDate($from);
        $to = $to === null ? $to : STimeToolbox::DbFormattedDate($to);
        return $this->app->dbAdapter->query($sql,[$shopId,$from,$to])->fetchAll();
    }

    /**
     * count order for friends grouping by date
     * @param null $from
     * @param null $to
     * @param null $shopId
     * @return array
     */
    public function getDailyOrderFriendStatistics($from = null, $to = null, $shopId = null) {
        $sql = "SELECT shopId, 
                       date(orderDate) as date, 
                       count(distinct orderId, ol.id) as orders,
                       sum(friendRevenue) as ordersValue
                from 
                  `Order` o join 
                  OrderLine ol ON o.id = ol.orderId JOIN 
                  OrderLineStatus ols on ol.status = ols.code
                WHERE 
                ols.phase BETWEEN 6 and 98 AND
                shopId = ifnull(?,shopId) AND
                orderDate BETWEEN ifnull(?,orderDate) and ifnull(?,orderDate)
                GROUP BY shopId, date";
        $from = $from === null ? $from : STimeToolbox::DbFormattedDate($from);
        $to = $to === null ? $to : STimeToolbox::DbFormattedDate($to);
        return $this->app->dbAdapter->query($sql,[$shopId,$from,$to])->fetchAll();
    }

}
