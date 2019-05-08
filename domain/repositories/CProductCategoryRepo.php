<?php


namespace bamboo\domain\repositories;

use Aws\Common\Enum;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\exceptions\BambooException;
use bamboo\core\theming\nestedCategory\CCategoryManager;
use bamboo\domain\entities\CDictionaryCategory;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductCategory;
use bamboo\domain\entities\CProductCategoryHasMarketplaceAccountCategory;
use bamboo\domain\entities\CProductCategoryTranslation;
use bamboo\domain\entities\CProductHasProductCategory;
use bamboo\domain\entities\CProductSheetModelPrototypeHasProductCategory;
use function PHPSTORM_META\type;

/**
 * Class CProductRepo
 * @package bamboo\app\domain\repositories
 */
class CProductCategoryRepo extends ARepo
{

    public function listByCategoryChildren($limit, $orderBy, $params, array $categoryPath)
    {
        $categoryPath = $categoryPath[0];
        /** @var $cm CCategoryManager */
        $cm = $this->app->categoryManager;
        $startingNode = $cm->categories()->pathId($categoryPath);
        $categories = $cm->categories()->children($startingNode);
        $tree = [];

        foreach ($categories as $key => $val) {
            $tree[$key]['parent'][] = $this->em()->findOne([$val['id']]);
        }

        foreach ($tree as $key => $val) {
            $children = new CObjectCollection();
            foreach ($cm->categories()->children($val['parent'][0]->id) as $kk => $vv) {
                $children->add($this->em()->findOne([$vv['id']]));
            }
            $children->reorder('slug');
            $tree[$key]['children'][] = $children;
        }

        return $tree;
    }

    public function listByDisposableCategoryChildren($limit, $orderBy, $params, array $categoryPath)
    {
        $sql = "SELECT
					  DISTINCT parent.id AS id,
					  parent.depth AS depth
					FROM 
					 ProductCategory father 
					 JOIN ProductCategory parent ON parent.lft BETWEEN father.lft AND father.rght
					 JOIN ProductCategory node ON node.lft BETWEEN parent.lft AND parent.rght 
                     JOIN Product p JOIN ProductStatus ps ON p.productStatusId = ps.id
					 JOIN ProductHasProductCategory phpc ON (p.id,p.productVariantId) = (phpc.productId,phpc.productVariantId) AND node.id = phpc.productCategoryId 
					WHERE 
					  ps.isVisible = 1 AND
                      father.id = :category
					GROUP BY parent.id
					HAVING parent.depth BETWEEN 1 AND 3
					ORDER BY parent.lft";
        $tree = [];
        $categoryPath = $categoryPath[0];
        /** @var $cm CCategoryManager */
        $cm = $this->app->categoryManager;
        $startingNode = $cm->categories()->pathId($categoryPath);

        $tree = [];
        $tree['parents'] = [];
        $tree['children'] = [];
        $categories = $this->em()->query($sql, ['category' => $startingNode])->fetchAll();
        if (empty($categories)) return $tree;
        $baseCatDepth = $categories[0]['depth'] + 1;
        unset($categories[0]);

        $key = 0;
        foreach ($categories as $category) {
            $temp = $this->em()->findOne([$category['id']]);
            $temp->depth = $category['depth'];
            if ($temp->depth == $baseCatDepth) {
                $key = count($tree['parents']);
                $tree['parents'][$key] = $temp;
            } else {
                $tree['children'][$key][] = $temp;
            }
        }
        return $tree;
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return array
     */
    public function listByCategory(array $limit, array $orderBy, array $params)
    {
        $categories = $this->em()->findAll("", "");
        /** @var $cm CCategoryManager */
        $cm = $this->app->categoryManager;
        $visibleLevel = 3;
        $path = $cm->categories()->getPath($params['category']);
        $idRoot = $path[$visibleLevel - 1]['id'];
        $tree = $cm->categories()->getDescendantsByNodeId($idRoot);

        foreach ($tree as $key => $val) {
            $cat = $categories->findOneByKey('id', $val['id']);
            $cat->depth = $val['depth'];
            $tree[$key] = $cat;
        }

        return $tree;
    }

    /**
     * @param array $limit
     * @param array $orderBy
     * @param array $params
     * @return CObjectCollection
     * TODO differenziare e sistemare le query
     */
    public function listByAppliedFiltersWithParent(array $limit, array $orderBy, array $params)
    {
        if (!isset($params['brand'])) {
            $params['brand'] = null;
        }
        if (!isset($params['color'])) {
            $params['color'] = null;
        }
        if (!isset($params['size'])) {
            $params['size'] = null;
        }
        if (!isset($params['category'])) {
            $params['category'] = 1;
        }
        if (!isset($params['tag'])) {
            $params['tag'] = null;
        }

        $sql = "SELECT
				  parent.id AS id,
				  parent.slug,
				  parent.depth
				FROM ProductCategory AS node,
				  ProductCategory AS parent,
				  vProductSortingView phpc,
				  ProductCategory AS father
				WHERE node.lft BETWEEN parent.lft AND parent.rght AND
				      node.id = phpc.category AND
				      father.id = IFNULL(:category, 1) AND
				      parent.lft BETWEEN father.lft AND father.rght AND
				      phpc.brand = IFNULL(:brand, phpc.brand) AND
				      phpc.color = IFNULL(:color, phpc.color) AND
				      phpc.size = IFNULL(:size, phpc.size) AND
				      phpc.tag = IFNULL(:tag, phpc.tag)
				GROUP BY parent.id
				HAVING depth BETWEEN :depthStart AND :depthEnd
				ORDER BY parent.lft";

        $sql = "SELECT
                  parent.id AS id,
                  parent.slug,
                  parent.depth
                FROM ProductCategory AS node
                  JOIN ProductCategory AS parent ON node.lft BETWEEN parent.lft AND parent.rght
                  JOIN ProductCategory AS father ON parent.lft BETWEEN father.lft AND father.rght
                  JOIN (
                    SELECT
                      DISTINCT phpc.productCategoryId
                    FROM Product p
                      JOIN ProductStatus pst ON p.productStatusId = pst.id
                      JOIN ProductPublicSku psk ON (p.id, p.productVariantId) = (psk.productId, psk.productVariantId)
                      JOIN ProductHasTag pht ON (p.id, p.productVariantId) = (pht.productId, pht.productVariantId)
                      JOIN ProductHasProductCategory phpc
                        ON (p.id, p.productVariantId) = (phpc.productId, phpc.productVariantId)
                    WHERE pst.isVisible = 1 AND
                          p.productBrandId = IFNULL(:brand, p.productBrandId) AND
                          p.productColorGroupId = IFNULL(:color, p.productColorGroupId) AND
                          psk.productSizeId = IFNULL(:size, psk.productSizeId) AND
                          pht.tagId = IFNULL(:tag, pht.tagId)
                    ) innerQ ON innerQ.productCategoryId = node.id
                WHERE
                  father.id = IFNULL(:category, 1) AND
                  parent.lft BETWEEN father.lft AND father.rght
                GROUP BY parent.id
                HAVING depth BETWEEN :depthStart AND :depthEnd
                ORDER BY parent.lft";

        $actual = $params['category'];
        $parent = $this->app->categoryManager->categories()->parentNode($actual)['id'];
        $depth = $this->app->categoryManager->categories()->depth($params['category']);

        $params['depthStart'] = $depth - 1;
        $params['depthEnd'] = $depth;
        $params['category'] = $parent;
        $categoiresBrothers = $this->em()->query($sql, $params)->fetchAll();

        $params['depthStart'] = $depth + 1;
        $params['depthEnd'] = $depth + 1;
        $params['category'] = $actual;
        $categoriesChildren = $this->em()->query($sql, $params)->fetchAll();

        $objc = new CObjectCollection();
        foreach ($categoiresBrothers as $brother) {
            $one = $this->em()->findOne([$brother['id']]);
            $one->depth = $brother['depth'] - $depth;
            $one->children = new CObjectCollection();
            if ($actual == $brother['id']) {
                foreach ($categoriesChildren as $child) {
                    $childEn = $this->em()->findOne([$child['id']]);
                    $childEn->depth = $child['depth'] - $depth;
                    $one->children->add($childEn);
                }
            }
            $objc->add($one);
        }
        return $objc;
    }

    public function fetchParent($nodeId)
    {
        $parent = $this->app->categoryManager->categories()->parentNode($nodeId);
        if ($parent != 1) {
            $parent = $this->em()->findOneBy($parent);
            $parent->depth = 0;

            return $parent;
        }

        return false;
    }

    /**
     * @param $limit
     * @param $orderBy
     * @param $params
     * @return CObjectCollection
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function listByCategoryPath($limit, $orderBy, $params)
    {
        $args = $this->app->router->getMatchedRoute()->getComputedFilters();
        $ids = $this->app->dbAdapter->query("SELECT productCategoryId FROM ProductHasProductCategory WHERE productId = ? AND productVariantId = ?", [$args['item'], $args['variant']])->fetchAll();
        /** @var $cm CCategoryManager */
        $cm = $this->app->categoryManager;
        $objc = new CObjectCollection();
        foreach ($ids as $id) {
            $path = $cm->categories()->getPath($id['productCategoryId']);
            foreach ($path as $key => $val) {
                if ($val['id'] < 4) continue;
                $objc->add($this->em()->findOne([$val['id']]));
            }
        }

        return $objc;
    }

    /**
     * @param $limit
     * @param $orderBy
     * @param $params
     * @param $args
     * @return CObjectCollection
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function listByMainCategories($limit, $orderBy, $params, $args)
    {
        $cats = new CObjectCollection();
        foreach ($this->app->categoryManager->categories()->childrenIds(1) as $catId) {
            $cat = $this->findOne([$catId]);
            $cats->add($cat);
        };

        return $cats;
    }

    /**
     * @param int|CProduct $productVariant
     * @param string $namesOrPaths ('paths' to get full paths, 'names'  to get only the names
     * @param string $outputFormat 'JSON'
     * @return array|string
     */
    public function listAllCategories($productVariant = 0, $namesOrPaths = 'paths', $outputFormat = '')
    {
        $cats = [];
        if ($productVariant) {
            if (is_int($productVariant)) {
                $catEm = \Monkey::app()->repoFactory->create('Product')->findOneBy(['productVariantId' => $productVariant])->productCategory;
            } elseif (is_object($productVariant)) {
                $catEm = $productVariant->productCategory;
            }
        }
        foreach ($catEm as $v) {
            if ('paths' == $namesOrPaths) {
                $path = $this->app->categoryManager->categories()->getPath($v->id);
                unset($path[0]);
                $cats[] = implode('/', array_column($path, 'slug'));
            } else {
                $cats[] = end($path);
            }
        }
        if ('JSON' === $outputFormat) json_encode($cats);
        elseif (0 === count($cats)) $cats = null;
        elseif (1 === count($cats)) $cats = $cats[0];
        return $cats;
    }

    /**
     * @param CProductCategory $category
     * @param string $separator
     * @return string
     */
    public function getStringPathCategory(CProductCategory $category, $separator = ', ')
    {
        $cats = $this->app->categoryManager->categories()->getPath($category->id);
        $type = [];
        foreach ($cats as $cat) {
            if ($cat['id'] == 1) continue;
            $type[] = $this->findOne([$cat['id']])->getLocalizedName();
        }
        return implode($separator, $type);
    }

    /**
     * @param $productCategory
     * @param bool $deleteExternalAssociation
     * @param bool $deleteProductAssociation
     * @return bool|string
     */
    public function deleteCategoryAndDescendant($productCategory, $deleteExternalAssociation = false, $deleteProductAssociation = false)
    {
        if (!$productCategory instanceof CProductCategory) {
            $productCategory = $this->findOneByStringId($productCategory);
        }
        \Monkey::app()->repoFactory->beginTransaction();
        $products = \Monkey::app()->repoFactory->create('Product')->getProductsByCategoryFullTree($productCategory->id);
        if ($deleteProductAssociation) {
            foreach ($products as $product) {
                /** @var CProduct $product */
                \Monkey::app()->dbAdapter->delete('ProductHasProductCategory',
                    ['productCategoryId' => $productCategory->id,
                        'productId' => $product->id,
                        'productVariantId' => $product->productVariantId
                    ]);
            }
        } else if (count($products) > 0) return false;

        if ($deleteExternalAssociation) {
            $categoriesId = \Monkey::app()->categoryManager->categories()->nestedSet()->getDescendantsByNodeId($productCategory->id);
            foreach ($categoriesId as $category) {
                $dictionaryCategories = \Monkey::app()->repoFactory->create('DictionaryCategory')->findBy(['productCategoryId' => $category['id']]);
                foreach ($dictionaryCategories as $dictionaryCategory) {
                    $dictionaryCategory->productCategoryId = null;
                    $dictionaryCategory->update();
                }
            }

            foreach ($categoriesId as $category) {
                \Monkey::app()->dbAdapter->delete('ProductCategoryHasMarketplaceAccountCategory', ['productCategoryId' => $category['id']]);
            }
        }

        if (\Monkey::app()->categoryManager->categories()->removeNodeAndDescendants($productCategory->id)) {
            //ProductCategoryTranslation
            \Monkey::app()->cacheService->getCache("misc")->delete("FullCategoryTreeAsJSON");
            \Monkey::app()->repoFactory->commit();
            return true;
        } else {
            \Monkey::app()->router->response()->raiseProcessingError();
            return false;
        }
    }



    /** INIZIO ----- NON TESTATO -> IN TEORIA RIORDINA ATTACCA TUTTI GLI ID E RIORDINA PADRE E FIGLI (LAVORA SU OGNI TABELLA DOVE C'è RIFERIMENTO ALL'ID CATEGORIA */

    /**
     * @return bool
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    public function indexCategoryFix()
    {

        $cats = \Monkey::app()->dbAdapter->query('SELECT id FROM ProductCategory WHERE id > 50 ORDER BY id', [])->fetchAll();

        for ($i = 0; $i < count($cats); $i++) {

            if ($i == 0 || $cats[$i - 1]['id'] + 1 == $cats[$i]['id']) {
                continue;
            }

            try {
                \Monkey::app()->repoFactory->beginTransaction();

                \Monkey::app()->dbAdapter->query('SET FOREIGN_KEY_CHECKS = 0', []);

                $rightCatId = $cats[$i - 1]['id'] + 1;

                $this->fixIds($rightCatId, $cats[$i]['id']);

                $cats[$i]['id'] = $rightCatId;

                \Monkey::app()->dbAdapter->query('SET FOREIGN_KEY_CHECKS = 1', []);
                \Monkey::app()->dbAdapter->commit();


            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                \Monkey::app()->dbAdapter->query('SET FOREIGN_KEY_CHECKS = 1', []);

                \Monkey::app()->applicationLog('ProductCategoryRepo', 'Error', 'Error while fixing ids', $e->getMessage());
                return false;
            }
        }

        $this->fixCategoryOrders();

        return true;
    }

    /**
     * @return bool
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    private function fixCategoryOrders()
    {

        /** @var CCategoryManager $catManager */
        $catManager = \Monkey::app()->categoryManager;

        $cats = $this->findAll();


        /** @var CProductCategoryRepo $pcR */
        $pcR = \Monkey::app()->repoFactory->create('ProductCategory');

        try {
            \Monkey::app()->repoFactory->beginTransaction();
            /** @var CProductCategory $cat */

            $updatedCats = [];
            foreach ($cats as $cat) {

                $catFatId = $catManager->getCategoryParent($cat->id)['id'];

                if ($catFatId > $cat->id) {

                    $updatedCats[] = $catFatId;

                    if(in_array($cat->id, $updatedCats)) continue;

                    /** @var CProductCategory $pc */
                    //Find the father category (with the same id)
                    $pc = $pcR->findOneBy(['id'=>$catFatId]);
                    $fatSlug = $pc->slug;
                    $fatLft = $pc->lft;
                    $fatRght = $pc->rght;
                    $fatDepth = $pc->depth;


                    $this->managePrimaryKeys('drop');
                    \Monkey::app()->dbAdapter->query('SET FOREIGN_KEY_CHECKS = 0', []);

                    $this->fixIds($cat->id, $catFatId);

                    \Monkey::app()->dbAdapter->query('
                        UPDATE ProductCategory pc
                          LEFT JOIN DictionaryCategory dc ON pc.id = dc.productCategoryId
                            LEFT JOIN ProductCategoryHasMarketplaceAccountCategory pcm ON pc.id = pcm.productCategoryId
                            LEFT JOIN ProductCategoryTranslation pct ON pc.id = pct.productCategoryId
                            LEFT JOIN ProductHasProductCategory ppc ON pc.id = ppc.productCategoryId
                            LEFT JOIN ProductSheetModelPrototypeHasProductCategory psmp ON pc.id = psmp.productCategoryId
                          SET
                            pc.id = pc.id + 1,
                            dc.productCategoryId = dc.productCategoryId + 1,
                            pcm.productCategoryId = pcm.productCategoryId + 1,
                            pct.productCategoryId = pct.productCategoryId + 1,
                            ppc.productCategoryId = ppc.productCategoryId + 1,
                            psmp.productCategoryId = psmp.productCategoryId + 1
                        WHERE pc.id >= ? AND pc.id < ? AND (pc.slug <> ? AND pc.lft <> ? AND pc.rght <> ? AND pc.depth <> ?)
                        ORDER BY pc.id', [$cat->id, $catFatId, $fatSlug, $fatLft, $fatRght, $fatDepth]);

                    \Monkey::app()->dbAdapter->query('SET FOREIGN_KEY_CHECKS = 1', []);
                    $this->managePrimaryKeys('set');

                }
            }

            \Monkey::app()->repoFactory->commit();
            return true;

        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            \Monkey::app()->dbAdapter->query('SET FOREIGN_KEY_CHECKS = 1', []);

            \Monkey::app()->applicationLog('ProductCategoryRepo', 'error', 'error while fixing order', $e->getMessage());
            return false;
        }
    }

    /**
     * @param $rightCatId
     * @param $actualCat
     * @return bool
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    private function fixIds($rightCatId, $actualCat)
    {

        $tables = ['DictionaryCategory','ProductCategoryHasMarketplaceAccountCategory','ProductCategoryTranslation','ProductHasProductCategory','ProductSheetModelPrototypeHasProductCategory','ProductCategory'];

        foreach($tables as $table){
            \Monkey::app()->dbAdapter->query('UPDATE ' . $table . ' SET productcategoryId = ? WHERE `productCategoryId` = ?', [$rightCatId, $actualCat]);
        }

        return true;
    }

    /**
     * @param $type
     * @throws BambooException
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    private function managePrimaryKeys($type){

        $tables = [
            'ProductCategoryHasMarketplaceAccountCategory' => [
                'productCategoryId',
                'marketplaceId',
                'marketplaceAccountId',
                'marketplaceAccountCategoryId',
            ],
            'ProductCategoryTranslation' => [
                'productCategoryId',
                'langId'
            ],
            'ProductHasProductCategory' => [
                'productId',
                'productVariantId',
                'productCategoryId'
            ],
            'ProductSheetModelPrototypeHasProductCategory' => [
                'productSheetModelPrototypeId',
                'productCategoryId'
            ],
            'ProductCategory' => [
                'id'
            ]];

        switch ($type){
            case 'drop':
                foreach ($tables as $tableName => $primaryKey){
                    \Monkey::app()->dbAdapter->query(
                        'ALTER TABLE `' . $tableName . '` DROP PRIMARY KEY;',
                               []);
                }
                break;
            case 'set':
                foreach ($tables as $tableName => $primaryKey){

                    $primaryKeyString = implode(',', $primaryKey);

                    \Monkey::app()->dbAdapter->query(
                                'ALTER TABLE `' . $tableName . '`
                                          ADD CONSTRAINT ' . $tableName . '_pk
                                        PRIMARY KEY (?);'
                    ,[$primaryKeyString]);
                }
                break;
            default:
                throw new BambooException('Primary keys typed wrong');
                break;
        }

    }

    /** FINE ----- NON TESTATO -> IN TEORIA RIORDINA ATTACCA TUTTI GLI ID E RIORDINA PADRE E FIGLI (LAVORA SU OGNI TABELLA DOVE C'è RIFERIMENTO ALL'ID CATEGORIA */

}