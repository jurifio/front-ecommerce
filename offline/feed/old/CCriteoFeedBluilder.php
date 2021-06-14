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
class CCriteoFeedBluilder extends ACronJob
{
	protected $marketCat = [];

    public function run($args = null)
    {
		$args = json_decode($args);
	    $this->report('Run','Starting To build');

	    $minized = true;
	    $args->minified == true;
	    /** PREPARAZIONE */
	    /** @var CCategoryManager $cm */
		
		$lang = $args->lang;

		$langRes = \Monkey::app()->repoFactory->create('Lang')->findOneBy(["lang"=>$lang]);
		$id = $langRes->id;
		$lang = new CLang($langRes->id,$langRes->lang);
	    $this->lang = $lang;
		
	    $helper = new CWidgetHelper($this->app);
        $categoriesName = $this->app->dbAdapter->query("SELECT productCategoryId, name FROM ProductCategoryTranslation where langId = ? and shopId=?" ,[$id,44])->fetchAll();
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
													  variant AS productVariantId
													FROM vProductSortingView
													GROUP BY product, variant", [])->fetchAll();

	    $uri = $this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/export/criteoFeedTemp.' . $lang . '.xml';

	    /** INIZIO INTESTAZIONE XML */
	    $writer = new \XMLWriter();

	    $writer->openUri($uri);
	    $writer->startDocument('1.0');
	    $writer->setIndent(!$minized);
	    /*$writer->startElement('rss');
	    $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
	    $writer->writeAttribute('version', '2.0');

	    $writer->startElement("channel");
	    $writer->writeElement('title', 'Google Product Feed');
	    $writer->writeElement('link', $this->app->baseUrl(false));

	    $writer->startElement('author');
	    $writer->writeElement('name', 'bambooshoot');
	    $writer->endElement();
	    $writer->writeElement('description', 'https://www.pickyshop.com/services/feed/'.$this->lang->getLang().'/criteo');
	    $writer->writeElement('updated', date(DATE_ATOM,time()));
		*/
	    $writer->startElement('products');
	    /** INIZIO INSERIMENTO PRODOTTI */
	    $contoErrori = 0;
	    $contoFatti = 0;
	    foreach ($products as $pro) {
		    try {
			    set_time_limit(5);
			    $contoFatti++;
			    if($contoFatti%250 == 0) {
				    $this->report('Run', 'Fatti: '.$contoFatti);
			    }
			    $writer->writeRaw($this->criteoProductToXML($repo->findOne($pro),$categories,$helper,$minized));
		    }catch(\Throwable $e){
			    $contoErrori++;
		    }
	    }
	    $writer->endElement();

	    /*$writer->endElement();
	    $writer->endElement();*/
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
	public function criteoProductToXML(CProduct $product, array $categories, CWidgetHelper $helper,$minized = true)
	{

		$protocol = 'https://';

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(!$minized);
		$writer->startElement("product");
		$writer->writeAttribute('id', $product->printId());

		$writer->startElement('name');
        $variant = ($product->productVariant) ? $product->productVariant->name : '';
        $prodName = ($product->productNameTranslation) ? $product->productNameTranslation->getFirst()->name : $product->itemno;
		$name = mb_strtoupper($product->productBrand->name) . ' ' . $variant . ' ' . $prodName;

		$writer->writeCdata($name);
		$writer->endElement();

		try {
			$writer->writeElement('producturl', $product->getProductUrl($this->app->baseUrl($this->lang->getLang(),'https')));
		} catch (\Throwable $e) {
			$writer->writeElement('producturl', $product->getProductUrl($this->app->baseUrl(),'https'));
		}


		$writer->writeElement('smallimage',  $helper->image($product->getPhoto(1, 562), 'amazon'));
		$writer->writeElement('bigimage',  $helper->image($product->getPhoto(1, 843), 'amazon'));

		$writer->startElement('description');
		$emptyDescription = false;
		if (empty($product->productDescriptionTranslation)) $emptyDescription = true;
		elseif (false !== strpos($product->productDescriptionTranslation->getFirst()->description, '<p><br></p>')) $emptyDescription = true;
		elseif (false !== strpos($product->productDescriptionTranslation->getFirst()->description, '"><br></h1>')) $emptyDescription = true;
		elseif (!($product->productDescriptionTranslation->getFirst()->description)) $emptyDescription = true;
		$desc = ($emptyDescription) ? $name : $product->productDescriptionTranslation->getFirst()->description;

		$writer->writeCdata($desc);
		$writer->endElement();

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

			$avai += $sku->stockQty;
			if ($sku->stockQty > 0) {
				$sizes[] = $sku->productSize->name;
			}
		}

		if($onSale) {
			$writer->writeElement('retailprice',$product->price);
			$writer->writeElement('price',$product->sale_price);
			$discount = ($product->price - $product->sale_price) / $product->price * 100;
			$writer->writeElement('discount', round($discount));
		} else {
			$writer->writeElement('price',$product->price);
		}

		if(count($sizes) > 3 || $product->productSizeGroupId == 49) {
			$writer->writeElement('recommendable', 1);
		} else {
			$writer->writeElement('recommendable', 0);
		}
		$writer->writeElement('instock', $avai > 0);

		$rightCatId = 1;

		foreach($product->productCategory as $category){
			$cats = $this->app->categoryManager->categories()->getPath($category->id);
			$type = [];
			foreach ($cats as $cat) {
				if($cat['id'] == 1) continue;
				$type[] = $categories[$cat['id']];
			}
			$rightCatId = $category->id;
		}

		if(isset($this->marketCat[$rightCatId])){
			$writer->writeElement('categoryid1',$this->marketCat[$rightCatId]);
		}
		$writer->endElement();
		return $writer->outputMemory();
	}

}