<?php
namespace bamboo\domain\repositories;

use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\CCart;
use bamboo\domain\entities\CCartLine;
use bamboo\domain\entities\CProduct;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CProductPublicSku;
use bamboo\domain\entities\CProductSheetModelPrototype;
use bamboo\domain\entities\CProductSizeGroup;
use bamboo\traits\TCatalogRepoFunctions;

/**
 * Class CProductRepo
 * @package bamboo\domain\repositories
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 01/11/2014
 * @since 1.0
 */
class CProductRepo extends ARepo
{
    use TCatalogRepoFunctions;

    public function listBySearch($limit, $orderBy, $params, $args)
    {
        $query = $this->app->dbAdapter->quote(urldecode($this->app->router->getMatchedRoute()->getComputedFilter('query')));

        $sql = "select id,productVariantId from 
          (SELECT p.id, p.productVariantId, group_concat(distinct t.sortingPriorityId order by t.sortingPriorityId asc) as tagPriority, t.sortingPriorityId as productPriority,max(match(ps.productBrandName, ps.productColorName, ps.productName, ps.productDescription, ps.productItemno, ps.productCategoryName, ps.productCode) against($string IN BOOLEAN MODE)) as score
         FROM Product p, ProductHasTag pht,ProductHasTagExclusive phte, Tag t, TagExclusive te, ProductSearch ps
          WHERE p.id = ps.productId AND 
                p.productVariantId = ps.productVariantId AND
                p.id = pht.productId AND
                p.productVariantId = pht.productVariantId AND 
                pht.tagId = t.id and
               p.id = phte.productId AND
                p.productVariantId = phte.productVariantId AND 
                phte.tagExclusiveId = te.id AND p.productStatusId in (6,11)
          GROUP BY p.id, p.productVariantId
          ORDER BY score DESC
          LIMIT $limit[0],$limit[1]) q1 where score > 0 order by tagPriority, productPriority";
        //\Monkey::app()->applicationLog('CProductRepo','search','print search',$sql);
        return $this->em()->findBySql($sql);
    }

    /**
     * @return int
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function listByCountDefault()
    {
        $sql = "SELECT count(DISTINCT product,variant) FROM ({$this->catalogInnerQuery}) t";

        return $this->em()->findCountBySql($sql, $this->prepareParams([]));
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return CObjectCollection
     */
    public function listByDefault(array $limit, array $orderBy, array $params)
    {
        $sql = "SELECT DISTINCT product AS id, variant AS productVariantId FROM ({$this->catalogInnerQuery}) t";

        return $this->em()->findBySql($this->prepareParams($params));
    }

    /**
     * @return CObjectCollection
     */
    public function listByVariants()
    {
        $args = $this->app->router->getMatchedRoute()->getComputedFilters();
        return $this->getVariantsOfProduct($args['item']);
    }

    /**
     * @param $productId
     * @return CObjectCollection
     */
    public function getVariantsOfProduct($productId) {
        $sql = "SELECT DISTINCT p.id, p.productVariantId 
                FROM Product p JOIN ProductStatus ps ON p.productStatusId = ps.id 
                WHERE ps.isVisible = 1 AND p.id = ?";
        return $this->em()->findBySql($sql, [$productId] );
    }

    public function countProductsByCategoryFullTree($productCategoryId) {
        return count(\Monkey::app()->categoryManager->categories()->getProductsInCategory($productCategoryId));
    }

    /**
     * @param $productCategoryId
     * @return CObjectCollection
     */
    public function getProductsByCategoryFullTree($productCategoryId) {
        $productsIds = \Monkey::app()->categoryManager->categories()->getProductsInCategory($productCategoryId);
        $res = new CObjectCollection();
        foreach ($productsIds as $productIds) {
            try {
                $res->add($this->findOne(array_values($productIds)));
            }catch (\Throwable $e) {
                \Monkey::dump($productIds);
                throw $e;
            }

        }
        return $res;
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @return CObjectCollection
     */
    public function listBySameBrand($limit = [], $orderBy = [])
    {
        $sql = "SELECT DISTINCT p.id,p.productVariantId 
                FROM Product p, ProductStatus ps, ProductHasProductCategory phpc, ProductCategory pc1,
                 ProductCategory pc2
                where p.productStatusId = ps.id
                and p.qty > 0
                and ps.isVisible = 1
                and p.id = phpc.productId
                and p.productVariantId = phpc.productVariantId
                and phpc.productCategoryId = pc1.id
                and pc1.lft BETWEEN pc2.lft and pc2.rght
                and pc2.id = ?
                and p.productBrandId = ? 
                AND p.id != ? 
                AND p.productVariantId != ? {$this->orderBy($orderBy)} {$this->limit($limit)}";

        $args = $this->app->router->getMatchedRoute()->getComputedFilters();
        $product = $this->findOneBy(['id' => $args['item'], 'productVariantId' => $args['variant']]);
        /** @var CProduct $product */


        return $this->findBySql($sql, [$product->getGendersId()[0], $product->productBrandId, $product->id, $product->productVariantId]);
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @return CObjectCollection
     */
    public function listBySameCategory($limit = [], $orderBy = [])
    {
        $args = $this->app->router->getMatchedRoute()->getComputedFilters();
        $product = $this->findOneBy(['id' => $args['item'], 'productVariantId' => $args['variant']]);

        $inter = [];
        $params = [];
        foreach ($product->productCategory as $productCategory) {
            if ($productCategory->id > 1) {
                $inter[] = '?';
                $params[] = $productCategory->id;
            }
        }

        $sql = "SELECT DISTINCT p.id,p.productVariantId 
                FROM Product p, ProductStatus ps, ProductHasProductCategory phpc, ProductCategory pc1,
                 ProductCategory pc2
                where p.productStatusId = ps.id
                and p.qty > 0
                and ps.isVisible = 1
                and p.id = phpc.productId
                and p.productVariantId = phpc.productVariantId
                and phpc.productCategoryId = pc1.id
                and pc1.lft BETWEEN pc2.lft and pc2.rght
                AND p.id != ? 
                AND p.productVariantId != ?
                and pc2.id in (" . implode(',', $inter) . ") {$this->orderBy($orderBy)} {$this->limit($limit)}";

        return $this->findBySql($sql, array_merge([$product->id, $product->productVariantId], $params));
    }

    public function findByAnyString($search, $limit = 10)
    {
        $query = "SELECT myView.id AS id, myView.productVariantId AS productVariantId FROM " .
            "(SELECT p.id, p.productVariantId, concat_ws(',',concat(p.id,'-', p.productVariantId), concat(p.itemno, '#', v.name), concat(p.itemno, ' # ', v.name)) AS ricerca " .
            "FROM Product p JOIN  ProductVariant v ON p.productVariantId = v.id GROUP BY p.id, p.productVariantId) AS myView" .
            " WHERE ricerca LIKE ? LIMIT " . $limit;
        return \Monkey::app()->repoFactory->create('Product')->findBySql($query, ['%' . $search . '%']);
    }

    /**
     * @return \bamboo\core\db\pandaorm\entities\IEntity
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function fetchEntityById()
    {
        $args = $this->app->router->getMatchedRoute()->getComputedFilters();
        $obj = $this->em()->findOne([$args['item'], $args['variant']]);

        return $obj;
    }


    /**
     * @param array|null $exactIds
     * @return \bamboo\core\db\pandaorm\entities\AEntity
     */
    public function fetchEntityByExactId(array $exactIds = null)
    {
        return $this->em()->findOne($exactIds);
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @param array $args
     * @return CObjectCollection
     */
    public function listByBrand(array $limit, array $orderBy, array $params, array $args)
    {
        return $this->listByCategory($limit, $orderBy, $params, $args);
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @param array $args
     * @return CObjectCollection
     */
    public function listByCategory(array $limit, array $orderBy, array $params, array $args)
    {
        return $this->listByAfterAppliedFilters($limit, $orderBy, $args);
        /*$sql = "select product as id, variant as productVariantId from
					(SELECT product, variant, min(tagPriority) as tagPriority
					FROM vProductSortingView " . $this->where($args, ' AND ') . "
					GROUP BY product, variant {$this->orderBy($orderBy)} ) t {$this->limit($limit)}";
        $products = $this->em()->findBySql($sql, array_values($args));

        return $products;*/
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return CObjectCollection
     */
    public function listByAfterAppliedFilters(array $limit, array $orderBy, array $params)
    {
        if (empty($orderBy)) {
            $sql = "select product as id,
                           variant as productVariantId,
                           group_concat(distinct LPAD(t.sortingPriority,10,'0') order by t.sortingPriority) as sorting
                    from ({$this->catalogInnerQuery}) t 
                    GROUP BY t.product, t.variant
                    ORDER BY sorting asc, t.productPriority, creation 
                    {$this->limit($limit)}";
        } else {
            $sql = "select product as id,
                           variant as productVariantId 
                    from ({$this->catalogInnerQuery}) t 
                    GROUP BY id, productVariantId 
                    {$this->orderBy($orderBy)} 
                    {$this->limit($limit)}";
        }

        $products = $this->em()->findBySql($sql, $this->prepareParams($params));
        return $products;
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return CObjectCollection
     */
    public function listByTagPriority(array $limit, array $orderBy, array $params)
    {
        $sql = "SELECT DISTINCT product as id, 
                                variant as productVariantId
                FROM ({$this->catalogInnerQuery}) t 
                {$this->orderBy($orderBy)} 
                {$this->limit($limit)} ";
        $products = $this->em()->findBySql($sql, $this->prepareParams($params));

        return $products;
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return CObjectCollection
     */
    public function listBySales(array $limit, $orderBy, $params = [])
    {
        $sql = "SELECT ord1.productId, ord1.productVariantId
                FROM OrderLine ord1, (SELECT productId, productVariantId, COUNT(*) AS sales FROM OrderLine GROUP BY orderId) ord2
                WHERE (ord2.productId, ord2.productVariantId) = (ord1.productId, ord1.productVariantId)
                {$this->orderBy($orderBy)} {$this->limit($limit)} ";
        $products = $this->em()->findBySql($sql);

        return $products;
    }

    /**
     * @param array $params
     * @return int
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function countByAfterAppliedFilters(array $params)
    {
        $sql = "SELECT COUNT(DISTINCT product,variant) FROM ({$this->catalogInnerQuery}) t";
        $productsNum = $this->em()->findCountBySql($sql, $this->prepareParams($params));

        return $productsNum;
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return null|CObjectCollection|\bamboo\core\db\pandaorm\entities\IEntity
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function listByProductDetail(array $limit, array $orderBy, array $params)
    {
        $get = $this->app->router->request()->getRequestData();
        if (isset($get['pr'])) {
            $ids = explode('-', $get['pr']);

            return $this->em()->findOne(['id' => $ids[0], 'productVariantId' => $ids[1]]);
        } else {
            $actionArgs = $this->app->router->getMatchedRoute()->getComputedFilters();
            if (isset($actionArgs['article'])) {
                $sql = "SELECT DISTINCT id, productVariantId FROM Product WHERE itemno = {$actionArgs['article']} limit 1";

                return $this->em()->findBySql($sql);
            }
        }

        return null;
    }

    public function updateFromModel(CProductSheetModelPrototype $emModel, $product)
    {

        $dba = \Monkey::app()->dbAdapter;

        try {
            \Monkey::app()->repoFactory->beginTransaction();
            foreach ($product->productSheetActual as $psa) {
                $psa->delete();
            }

            $product->productSheetPrototypeId = $emModel->productSheetPrototypeId;
            $product->update();

            $psaRepo = \Monkey::app()->repoFactory->create('ProductSheetActual');
            foreach ($emModel->productSheetModelActual as $psm) {
                $psa = $psaRepo->getEmptyEntity();
                $psa->productId = $product->id;
                $psa->productVariantId = $product->productVariantId;
                $psa->productDetailLabelId = $psm->productDetailLabelId;
                $psa->productDetailId = $psm->productDetailId;
                $psa->insert();
            }

            $pntRepo = \Monkey::app()->repoFactory->create('ProductNameTranslation');
            $pntRepo->updateProductName($product->id, $product->productVariantId, $emModel->productName);
            \Monkey::app()->repoFactory->commit();
        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            throw new BambooException($e->getMessage());
        }
    }

    /**
     * @param $product CProduct
     * @param $prototypeId Int
     * @param $details CObjectCollection | Array
     * @param $name String
     */
    public function updateDetailsFromData($product, $prototypeId, $details, $name)
    {
        $product->productSheetPrototypeId = $prototypeId;
        $product->update();

        $pntRepo = \Monkey::app()->repoFactory->create('ProductNameTranslation');
        $pntRepo->updateProductName($product->id, $product->productVariantId, $name);
        $this->updateDetails($product, $details);
    }

    public function updateDetails($product, $details)
    {
        $psaRepo = \Monkey::app()->repoFactory->create('ProductSheetActual');
        $psaOC = $psaRepo->findBy(['productId' => $product->id, 'productVariantId' => $product->productVariantId]);

        foreach ($psaOC as $v) {
            $v->delete();
        }
        $dets = [];

        if (is_object($details)) {
            foreach ($details as $k => $v) {
                $dets[$k] = $v;
            }
        } else {
            $dets =& $details;
        }
        foreach ($dets as $k => $v) {
            $psa = $psaRepo->getEmptyEntity();
            $psa->productId = $product->id;
            $psa->productVariantId = $product->productVariantId;
            $psa->productDetailLabelId = $k;
            $psa->productDetailId = $v;
            $psa->insert();
        }
    }

    /**
     * @param $productStringId
     * @param null $seasonId
     * @throws BambooException
     */
    public function createMovementsOnChangingSeason($productStringId, $seasonId = null)
    {
        $loggingDate = date('Y-m-d H:i:s');
        if (is_a($productStringId, 'AEntity')) {
            $p = $productStringId;
        } else {
            $pR = \Monkey::app()->repoFactory->create('Product');
            if (strpos($productStringId, '-')) $p = $pR->findOneByStringId($productStringId);
            else throw new BambooException('il codice prodotto inserito non è valido');
        }
        if (!$p) throw new BambooException('il prodotto fornito non esiste');
        if (!$seasonId) $seasonId = $p->productSeasonId;
        if ($p->qty) {
            $skus = $p->productSku;

            $availables = [];
            $total = 0;

            foreach ($skus as $k => $s) {
                if ($s->stockQty) {
                    $total += $s->stockQty;
                    if (!array_key_exists($s->shopId, $availables)) {
                        $availables[$s->shopId] = [];
                        $availables[$s->shopId]['total'] = 0;
                        $availables[$s->shopId]['moves'] = [];
                    }
                    if ($s->stockQty) {
                        $availables[$s->shopId]['total'] += $s->stockQty;
                        $singleSkuData = [];
                        $singleSkuData['id'] = $s->productId;
                        $singleSkuData['productVariantId'] = $s->productVariantId;
                        $singleSkuData['productSizeId'] = $s->productSizeId;
                        $singleSkuData['qtMove'] = $s->stockQty;
                        $availables[$s->shopId]['moves'][] = $singleSkuData;
                    }
                }
            }

            foreach ($availables as $k => $s) {
                $soR = \Monkey::app()->repoFactory->create('StorehouseOperation');
                $dba = \Monkey::app()->dbAdapter;
                //preparo le quantità per scalarle
                if ($s['total']) {
                    foreach ($s['moves'] as $km => $m) {
                        $s['moves'][$km]['qtMove'] = $m['qtMove'] * -1;
                    }
                    $soR->registerOperation($s['moves'], $k, 14);


                    $q = "SELECT id, shopId, storehouseId, creationDate FROM StorehouseOperation WHERE id = (SELECT max(id) AS id FROM `StorehouseOperation` WHERE shopId = ?)";
                    $resSO = $dba->query($q, [$k])->fetch();
                    $newDate = date('Y-m-d H:i:s', strtotime($resSO['creationDate']) - 3);
                    $loggingDate = date('Y-m-d H:i:s', strtotime($resSO['creationDate']) - 2);
                    $q = "UPDATE `StorehouseOperation` SET creationDate = ? WHERE id = ? AND shopId = ? AND storehouseId = ?";

                    $soR->findOneBy(['id' => $resSO['id'], 'shopId' => $resSO['shopId'], 'storehouseId' => $resSO['storehouseId']]);
                    try {
                        $dba->query($q, [$newDate, $resSO['id'], $resSO['shopId'], $resSO['storehouseId']])->countAffectedRows();
                    } catch (BambooException $e) {
                        throw $e;
                    }

                    foreach ($s['moves'] as $km => $m) {
                        $s['moves'][$km]['qtMove'] = $m['qtMove'] * -1;
                    }

                    $soR->registerOperation($s['moves'], $k, 15);
                }
            }
        }

        \Monkey::app()->eventManager->triggerEvent('changeSeason', [
            'product' => $p,
            'seasonId' => $seasonId,
            'time' => $loggingDate,
        ]);
    }

    /**
     * @param CProduct $product
     * @param CProductSizeGroup $productSizeGroup
     * @return bool
     * @throws \Throwable
     */
    public function changeProductSizeGroup(CProduct $product, CProductSizeGroup $productSizeGroup) {
        /** @var CProductPublicSkuRepo $productPublicSkuRepo */
        $productPublicSkuRepo = \Monkey::app()->repoFactory->create('ProductPublicSku');
        \Monkey::app()->repoFactory->beginTransaction();
        try {
            $cartLineMemory = [];
            foreach ($product->productPublicSku as $productPublicSku) {
                /** @var CProductPublicSku $productPublicSku */
                /** @var CCartLine $cartLine */
                foreach ($productPublicSku->cartLine as $cartLine) {
                    $ids = $productPublicSkuRepo->findPublicSkuIdsForDifferentProductSizeGroup($cartLine->productPublicSku, $productSizeGroup);
                    if($ids) {
                        $cartLine->productId = $ids['id'];
                        $cartLine->productVariantId = $ids['productVariantId'];
                        $cartLine->productSizeId = $ids['productSizeId'];
                        $cartLineMemory[] = clone $cartLine;
                    }
                    $cartLine->delete();
                }
                $productPublicSku->delete();
            }
            $product->productSizeGroupId = $productSizeGroup->id;
            $product->update();

            \Monkey::app()->dbAdapter->query(
                "INSERT INTO ProductPublicSku
                      SELECT
                        p.id,
                        p.productVariantId,
                        psghpsPub.productSizeId,
                        sum(stockQty)      AS stockQty,
                        max(psk.price)     AS price,
                        min(psk.salePrice) AS salePrice
                      FROM
                        Product p
                        JOIN ProductSizeGroup psgPub ON p.productSizeGroupId = psgPub.id
                        JOIN ProductSizeGroupHasProductSize psghpsPub ON psgPub.id = psghpsPub.productSizeGroupId
                        JOIN ProductSku psk ON psk.productId = p.id AND psk.productVariantId = p.productVariantId
                        JOIN ShopHasProduct shp
                          ON (psk.productId, psk.productVariantId, psk.shopId) = (shp.productId, shp.productVariantId, shp.shopId)
                        JOIN ProductSizeGroup psgPri ON shp.productSizeGroupId = psgPri.id AND psgPub.productSizeMacroGroupId = psgPri.productSizeMacroGroupId
                        JOIN ProductSizeGroupHasProductSize psghpsPri
                          ON psgPri.id = psghpsPri.productSizeGroupId AND psghpsPri.position = psghpsPub.position AND
                             psghpsPri.productSizeId = psk.productSizeId
                      WHERE p.id = ? and p.productVariantId = ?
                      GROUP BY p.id, p.productVariantId, psghpsPub.productSizeId
                      ORDER BY psk.productId, psk.productVariantId, psghpsPri.position
                    ON DUPLICATE KEY
                    UPDATE ProductPublicSku.stockQty = stockQty,
                      ProductPublicSku.price         = if(ProductPublicSku.price = 0, price, ProductPublicSku.price),
                      ProductPublicSku.salePrice     = if(ProductPublicSku.salePrice = 0, salePrice, ProductPublicSku.salePrice)",
                [   $product->id,
                    $product->productVariantId
                ]);

            foreach ($cartLineMemory as $cartLine) $cartLine->insert();
            \Monkey::app()->repoFactory->commit();
            return true;
        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            throw $e;
        }
    }

    /**
     * @param CProduct $product
     * @return CProduct
     */
    public function updatePublicSkus(CProduct $product) {
        foreach ($product->productPublicSku as $productPublicSku) {
            $productPublicSku->unCache();
        }
        unset($product->productPublicSku);
        $sql = "INSERT INTO ProductPublicSku
                SELECT
                  p.id,
                  p.productVariantId,
                  psghpsPub.productSizeId,
                  sum(stockQty)      AS stockQty,
                  max(psk.price)     AS price,
                  min(psk.salePrice) AS salePrice
                FROM
                  Product p
                  JOIN ProductSizeGroup psgPub ON p.productSizeGroupId = psgPub.id
                  JOIN ProductSizeGroupHasProductSize psghpsPub ON psgPub.id = psghpsPub.productSizeGroupId
                  JOIN ProductSku psk ON (p.id,p.productVariantId) = (psk.productId, psk.productVariantId)
                  JOIN ShopHasProduct shp
                    ON (psk.productId, psk.productVariantId, psk.shopId) = (shp.productId, shp.productVariantId, shp.shopId)
                  JOIN ProductSizeGroup psgPri ON shp.productSizeGroupId = psgPri.id AND psgPub.productSizeMacroGroupId = psgPri.productSizeMacroGroupId
                  JOIN ProductSizeGroupHasProductSize psghpsPri
                    ON psgPri.id = psghpsPri.productSizeGroupId AND psghpsPri.position = psghpsPub.position AND
                       psghpsPri.productSizeId = psk.productSizeId
                WHERE (p.id, p.productVariantId) = (:id, :productVariantId)
                GROUP BY p.id, p.productVariantId, psghpsPub.productSizeId
              ON DUPLICATE KEY
              UPDATE ProductPublicSku.stockQty = stockQty,
                ProductPublicSku.price         = if(ProductPublicSku.price = 0, price, ProductPublicSku.price),
                ProductPublicSku.salePrice     = if(ProductPublicSku.salePrice = 0, salePrice, ProductPublicSku.salePrice)";
        \Monkey::app()->dbAdapter->query($sql,$product->getIds(),true);
        return $product;
    }

    /**
     * Cancella un prodotto e tutti i suoi riferimenti
     * @param CProduct $product
     * @param bool $forReal
     * @param bool $shopId
     * @param bool $cartToo
     * @return bool
     * @throws \Throwable
     * @transaction
     */
    public function deleteProductTotalCascade(CProduct $product, $forReal = false, $shopId = false, $cartToo = false)
    {
        if ($forReal) {
            \Monkey::app()->repoFactory->beginTransaction();
            try {


                if ($shopId == false) {
                    foreach ($product->productSheetActual as $productSheetActual) {
                        $productSheetActual->delete();
                    }
                    foreach ($product->productHasProductCategory as $productHasProductCategory) {
                        $productHasProductCategory->delete();
                    }
                    foreach ($product->productHasTag as $productHasTag) {
                        $productHasTag->delete();
                    }
                    foreach ($product->productHasTagExclusive as $productHasTagExclusive) {
                        $productHasTagExclusive->delete();
                    }

                    foreach ($product->marketplaceAccountHasProduct as $marketplaceAccountHasProduct) {
                        foreach ($marketplaceAccountHasProduct->marketplaceAccountHasProductSku as $marketplaceAccountHasProductSku) {
                            $marketplaceAccountHasProductSku->delete();
                        }
                        $marketplaceAccountHasProduct->delete();
                    }

                    foreach ($product->productDescriptionTranslation as $productDescriptionTranslation) {
                        $productDescriptionTranslation->delete();
                    }

                    foreach ($product->productNameTranslation as $productNameTranslation) {
                        $productNameTranslation->delete();
                    }
                }

                foreach ($product->shopHasProduct as $shopHasProduct) {

                    if ($shopId === false || $shopId == $shopHasProduct->shopId) {

                        foreach ($shopHasProduct->productSku as $productSku) {
                            if ($cartToo) {
                                foreach (\Monkey::app()->repoFactory->create('CartOrderLine')->findBy($productSku->getIds()) as $cartOrderLine) {
                                    if($cartOrderLine->order->orderStatus->order == 1) {
                                        \Monkey::app()->repoFactory->create('Cart')->removeSku($cartOrderLine);
                                    }
                                }
                            }
                            $productSku->delete();
                        }
                        $dirtyProducts = \Monkey::app()->repoFactory->create('DirtyProduct')->findBy($shopHasProduct->getIds());
                        foreach ($dirtyProducts as $dirtyProduct) {
                            \Monkey::app()->repoFactory->create('DirtyProduct')->deleteDirtyProductTotalCascade($dirtyProduct, true);
                        }

                        $this->app->dbAdapter->delete('ProductStatistics', $shopHasProduct->getIds());
                        $shopHasProduct->delete();
                    }
                }

                if ($shopId === false) {
                    $product->delete();
                }

                \Monkey::app()->repoFactory->commit();
                return true;
            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                throw $e;
            }

        } else return false;
    }


    /**
     * @param $productCategoryId
     * @param $seasons
     * @param $photo
     * @param $shooting
     * @param $shops
     * @return CObjectCollection
     * @throws \Throwable
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    public function getProductsByCategoryFullTreeFilters($productCategoryId, $seasons, $photo, $shooting, $shops) {
        $productsIds = \Monkey::app()->categoryManager->categories()->getProductsInCategoryFilters($productCategoryId, $seasons, $photo, $shooting, $shops);
        $res = new CObjectCollection();
        foreach ($productsIds as $productIds) {
            try {
                $res->add($this->findOne(array_values($productIds)));
            }catch (\Throwable $e) {
                \Monkey::dump($productIds);
                throw $e;
            }

        }
        return $res;
    }
}