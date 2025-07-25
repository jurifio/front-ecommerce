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
class CDrezzyFeedBluilder extends ACronJob
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
        $categoriesName = $this->app->dbAdapter->query("SELECT productCategoryId, name FROM ProductCategoryTranslation where langId = ? and shopId=?" ,[$id,44])->fetchAll();
	    $categories=[];
	    foreach($categoriesName as $alls){
		    $categories[$alls['productCategoryId']] = $alls['name'];
	    }
	    unset($categoriesName);

	    $rightCat = $this->app->dbAdapter->query("SELECT productCategoryId, marketPlaceCategoryId FROM ProductCategoryMarketPlaceLookup",[])->fetchAll();

	    foreach($rightCat as $mc){
		    $this->marketCat[$mc['productCategoryId']] = $mc['marketPlaceCategoryId'];
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

	    $uri = $this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/export/DrezzyFeedTemp.' . $lang . '.xml';

	    /** INIZIO INTESTAZIONE XML */
	    $writer = new \XMLWriter();

	    $writer->openUri($uri);

		$writer->startElement('Products');

	    /** INIZIO INSERIMENTO PRODOTTI */
	    $contoErrori = 0;
	    foreach ($products as $pro) {
		    try {
			    unset($pro['maxQty']);
			    set_time_limit(5);
			    $writer->writeRaw($this->drezzyProductToXML($repo->findOne($pro),$categories,$helper,$minized));
		    }catch(\Throwable $e){
			    $contoErrori++;
		    }
	    }
		
	    $writer->endElement();
	    //$writer->endElement();
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
	public function drezzyProductToXML(CProduct $product, array $categories, CWidgetHelper $helper,$minized = true)
	{

		$protocol = 'https://';

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(!$minized);
		$writer->startElement("Offer");
		$writer->writeElement('Code', $product->id . '-' . $product->productVariantId);

		$qty = 0;
		$product->price = 0;
		$product->sale_price = 0;
		$onSale = false;
		$sizes = [];
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
				$qty += $sku->stockQty;
			}
		}


        $variant = ($product->productVariant) ? $product->productVariant->name : '';
        $prodName = ($product->productNameTranslation) ? $product->productNameTranslation->getFirst()->name : $product->itemno;
		$name = mb_strtoupper($product->productBrand->name) . ' ' . $variant . ' ' . $prodName;

		if(count($sizes)<3) $name .= " (".implode('-',$sizes).")";
		$writer->writeElement('Name', $name);


        $emptyDescription = false;
        if (empty($product->productDescriptionTranslation)) $emptyDescription = true;
        elseif (false !== strpos($product->productDescriptionTranslation->getFirst()->description, '<p><br></p>')) $emptyDescription = true;
        elseif (false !== strpos($product->productDescriptionTranslation->getFirst()->description, '"><br></h1>')) $emptyDescription = true;
        elseif (!($product->productDescriptionTranslation->getFirst()->description)) $emptyDescription = true;
		$desc = ($emptyDescription) ? $name : $product->productDescriptionTranslation->getFirst()->description;
		$writer->writeElement('Descrition', strip_tags($desc));


		$product_type = [];
		
		foreach($product->productCategory as $category){
			$cats = $this->app->categoryManager->categories()->getPath($category->id);
			$type = [];
			foreach ($cats as $cat) {
				if($cat['id'] == 1) continue;
				$type[] = $categories[$cat['id']];
			}
			$product_type[] = implode(', ',$type);
		}
		$writer->writeElement('Category', implode('; ', $product_type));

		$baseUrlLang = $this->app->cfg()->fetch("paths","domain")."/".$this->lang->getLang();
		$writer->writeElement('Link',  'https://' . $product->getProductUrl($baseUrlLang));
		$writer->writeElement('Image', $helper->image($product->getPhoto(1, 843), 'amazon'));
		$addImg = 2;
		for ($i = 2; $i < 8; $i++) {
			if (3 < $addImg) break;
			$actual = $product->getPhoto($i, 843);
			if ($actual!= false && !empty($actual)) {
				$writer->writeElement('Image' . $addImg, $helper->image($actual,'amazon'));
				$addImg++;
			}
		}
		$writer->writeElement('Stock', $qty);
		$writer->writeElement('OriginalPrice', number_format($product->price, 2, ',', ''));
		if($onSale){
			$writer->writeElement('Price', number_format($product->sale_price, 2, ',', ''));
		}
		$writer->writeElement('EanCode', '');
		//$writer->writeElement('g:mpn', $product->itemno . ' ' . $product->productVariant->name);
		$writer->writeElement('Brand', $product->productBrand->name);
		//if (isset($product->productColorGroup) && !$product->productColorGroup->isEmpty()) {
		//	$writer->writeElement('g:color', $product->productColorGroup->getFirst()->name);
		//}

		$writer->writeElement('ShippingCost', '5,00');
		$writer->endElement();
		return $writer->outputMemory();
	}
}