<?php
namespace bamboo\ecommerce\offline\feed;

use bamboo\core\intl\CLang;
use bamboo\domain\entities\CProduct;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\jobs\ACronJob;
use bamboo\core\theming\CWidgetHelper;

/**
 * Class CDispatchPreorderToFriend
 * @package bamboo\blueseal\jobs
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CStileoFeedBluilder extends ACronJob
{
    protected $marketCat = [];

    public function run($args = null)
    {
        $args = json_decode($args);
        $this->report('Run','Starting To build');

        $minized = true; $args->minified == true;
        /** PREPARAZIONE */
        /** @var CCategoryManager $cm */

        $lang = $args->lang;

        $langRes = \Monkey::app()->repoFactory->create('Lang')->findOneBy(["lang"=>$lang]);
        $id = $langRes->id;
        $lang = new CLang($langRes->id,$langRes->lang);
		$this->lang = $lang;
        $cm = $this->app->categoryManager;
        $helper = new CWidgetHelper($this->app);
        $categoriesName = $this->app->dbAdapter->query("SELECT productCategoryId, name FROM ProductCategoryTranslation where langId = ?",[$id])->fetchAll();
        $categories=[];
        foreach($categoriesName as $alls){
            $categories[$alls['productCategoryId']] = $alls['name'];
        }
        unset($categoriesName);

        $rightCat = $this->app->dbAdapter->query("SELECT productCategoryId, marketplaceCategoryId FROM MarketplaceCategoryLookup",[])->fetchAll();

        foreach($rightCat as $mc){
            $this->marketCat[$mc['productCategoryId']] = $mc['marketplaceCategoryId'];
        }

        /** TROVO PRODOTTI */
        $repo = \Monkey::app()->repoFactory->create('Product', $lang);
        $products = $this->app->dbAdapter->query("SELECT
													  product AS id,
													  variant AS productVariantId,
													  if(size in (158, 389, 482, 495, 540),4, totalQty) as maxQty
													FROM vProductSortingView
													GROUP BY product, variant
													HAVING count(DISTINCT size) > 1 OR
													       maxQty > 3", [])->fetchAll();

	    $uri = $this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/export/stileoFeedTemp.' . $lang . '.xml';

        /** INIZIO INTESTAZIONE XML */
        $writer = new \XMLWriter();

        $writer->openUri($uri);
        $writer->startDocument('1.0');
        $writer->setIndent(!$minized);
        $writer->startElement('offers');

        /** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = 0;
        foreach ($products as $pro) {
            try {
	            unset($pro['maxQty']);
                set_time_limit(5);
                $writer->writeRaw($this->stileoProductToXML($repo->findOne($pro),$categories,$helper,$minized));
            }catch(\Throwable $e){
                $contoErrori++;
            }
        }
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        $this->report('Run','End build, errors: '.$contoErrori);
    }

    /**
     * @param CProduct $product
     * @param array $categories
     * @param CWidgetHelper $helper
     * @param bool $minized
     * @return string
     * @throws \bamboo\core\exceptions\RedPandaDBALException
     */
    public function stileoProductToXML(CProduct $product, array $categories, CWidgetHelper $helper,$minized = true)
    {
        $baseUrlLang = $this->app->baseUrl($this->lang);
        $avai = 0;
        $product->price = 0;
        $product->sale_price = 0;
        $sizes = [];
        $onSale = false;
        foreach ($product->productSku as $sku) {
            if(!$onSale && $sku->isOnSale == 1) $onSale = true;
            if ($sku->price > $product->price) {
                $product->price = $sku->price;
            }
            if ($sku->salePrice > $product->sale_price) {
                $product->sale_price = $sku->salePrice;
            }
            if ($sku->stockQty > 0) {
                $sizes[] = $sku->productSize->name;
                $avai++;
            }
        }
        if ($product->price != 0) {

            $writer = new \XMLWriter();
            $writer->openMemory();
            $writer->setIndent(!$minized);
            $writer->startElement("offer");

            $writer->startElement('id');
            $writer->writeCdata($product->id . '-' . $product->productVariantId);
            $writer->endElement();


            $writer->startElement("url");
            $writer->writeCdata($product->getProductUrl($baseUrlLang));
            $writer->endElement();

            $writer->startElement("price");

            if ($onSale && $product->sale_price != 0) {
                $writer->writeCdata($product->sale_price);
                $writer->endElement();
                $writer->startElement("oldprice");
                $writer->writeCdata($product->price);
            } else {
                $writer->writeCdata($product->price);
            }
            $writer->endElement();

            $writer->startElement("brand");
            $writer->writeCdata( $product->productBrand->name);
            $writer->endElement();
            
            $writer->startElement("avail");
            $writer->writeCdata($avai);
            $writer->endElement();


            $rightCatId = 1;

            foreach($product->productCategory as $category){
                $cats = $this->app->categoryManager->categories()->getPath($category->id);
                $type = [];
                foreach ($cats as $cat) {
                    if($cat['id'] == 1) continue;
                    $type[] = $categories[$cat['id']];
                }
               $product_type[] = implode(', ',$type);
               //$rightCatId = $category->id;
            }
                $fullCatName = implode(" ", $product_type);
            $writer->startElement("cat");
            $writer->writeCdata($fullCatName);
            $writer->endElement();
            
            $writer->startElement("name");
            if (empty($product->productNameTranslation)) {
                $name = mb_strtoupper($product->productBrand->name) . ' ' . $product->itemno;
            } else {
                $name = mb_strtoupper($product->productBrand->name) . ' ' . $product->productNameTranslation->getFirst()->name;
            }
            if (count($sizes) < 3) $name .= " (" . implode(',', $sizes) . ")";
            $writer->writeCdata($name);
            $writer->endElement();

            $writer->startElement("imgs");
            for ($i = 0; $i < 8; $i++){
                $actual = $product->getPhoto($i, 843);
                if ($actual != false && !empty($actual)) {
                    $writer->startElement("img");
                    $writer->writeCdata($helper->image($actual, 'amazon'));
                    $writer->endElement(); //img
                }
            }
            $writer->endElement(); //imgs

            $writer->startElement("sizes");
            $writer->writeCdata(implode('; ', $sizes));
            $writer->endElement();//sizes

            $writer->startElement("desc");
            $desc = empty($product->productDescriptionTranslation) ? " " : $product->productDescriptionTranslation->getFirst()->description;
            $writer->writeCdata($desc);
            $writer->endElement(); //desc



            $writer->startElement('title');
            if (empty($product->productNameTranslation)) {
                $name = mb_strtoupper($product->productBrand->name) . ' ' . $product->itemno;
            } else {
                $name = mb_strtoupper($product->productBrand->name) . ' ' . $product->productNameTranslation->getFirst()->name;
            }
            if (count($sizes) < 3) $name .= " (" . implode(',', $sizes) . ")";
            $writer->writeCdata($name);
            $writer->endElement();


            $writer->startElement('description');
            $desc = empty($product->productDescriptionTranslation) ? " " : $product->productDescriptionTranslation->getFirst()->description;

            $writer->writeCdata($desc);
            $writer->endElement(); //desc

            $writer->endElement(); //offer

            return $writer->outputMemory();
        }
    }

}