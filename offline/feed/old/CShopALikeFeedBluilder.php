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

class CShopALikeFeedBluilder extends ACronJob
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

		$rightCat = $this->app->dbAdapter->query("SELECT productCategoryId, marketplaceCategoryId FROM MarketplaceCategoryLookup",[])->fetchAll();

		foreach($rightCat as $mc){
			$this->marketCat[$mc['productCategoryId']] = $mc['marketplaceCategoryId'];
		}

		/** TROVO PRODOTTI */
		$repo = \Monkey::app()->repoFactory->create('Product',$lang);
		$products = $this->app->dbAdapter->query("SELECT
													  product AS id,
													  variant AS productVariantId,
													  if(size in (158, 389, 482, 495, 540),4, totalQty) as maxQty
													FROM vProductSortingView
													GROUP BY product, variant
													HAVING count(DISTINCT size) > 1 OR
													       maxQty > 3", [])->fetchAll();


		$uri = $this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/export/shopALikeFeedTemp.' . $lang . '.xml';

		/** INIZIO INTESTAZIONE XML */
		$writer = new \XMLWriter();

		$writer->openUri($uri);
		$writer->startDocument('1.0', 'UTF-8');
		$writer->setIndent(!$minized);
		$writer->startElement('rss');
		$writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
		$writer->writeAttribute('version', '2.0');

		$writer->startElement("channel");
		$writer->writeElement('title', 'ShopALike Product Feed');
		$writer->writeElement('link', $this->app->baseUrl(false));

		$writer->startElement('author');
		$writer->writeElement('name', 'bambooshoot');
		$writer->endElement();
		$writer->writeElement('description', 'https://www.pickyshop.com/services/feed/'.$this->lang->getLang().'/shopalike');
		$writer->writeElement('updated', date(DATE_ATOM, time()));

		/** INIZIO INSERIMENTO PRODOTTI */
		$contoErrori = 0;
		foreach ($products as $pro) {
			try {
				unset($pro['maxQty']);
				set_time_limit(5);
				$writer->writeRaw($this->shopALikeProductToXML($repo->findOne($pro), $categories, $helper, $minized));
			} catch (\Throwable $e) {
				$contoErrori++;
			}
		}
		$writer->endElement();
		$writer->endElement();
		$writer->endDocument();
		$writer->flush();

		$this->report( 'Run', 'End build, errors: ' . $contoErrori);
	}

	/**
	 * @param CProduct $product
	 * @param array $categories
	 * @param CWidgetHelper $helper
	 * @param bool $minized
	 * @return string
	 * @throws \bamboo\core\exceptions\RedPandaDBALException
	 */
	public function shopALikeProductToXML(CProduct $product, array $categories, CWidgetHelper $helper,$minized = true)
	{
		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(!$minized);
		$writer->startElement("item");
		$writer->writeElement('g:id', $product->id . '-' . $product->productVariantId);

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

		$writer->startElement('title');
		if(empty($product->productNameTranslation)){
			$name = mb_strtoupper($product->productBrand->name).' '.$product->itemno;
		} else {
			$name = mb_strtoupper($product->productBrand->name).' '.$product->productNameTranslation->getFirst()->name;
		}
		if(count($sizes)<3) $name.= " (".implode('-',$sizes).")";
		$writer->writeCdata($name);
		$writer->endElement();

		$writer->startElement('description');
		$desc = empty($product->productDescriptionTranslation) ? " " : $product->productDescriptionTranslation->getFirst()->description;
		$writer->writeCdata($desc);
		$writer->endElement();


		$product_type = [];

		$rightCatId = 1;

		foreach($product->productCategory as $category){
			$cats = $this->app->categoryManager->categories()->getPath($category->id);
			$type = [];
			foreach ($cats as $cat) {
				if($cat['id'] == 1) continue;
				$type[] = $categories[$cat['id']];
			}
			$product_type[] = implode(', ',$type);
			$rightCatId = $category->id;
		}
		$writer->writeElement('g:product_type', implode('; ', $product_type));

		if(isset($this->marketCat[$rightCatId])){
			$writer->writeElement('g:google_product_category',$this->marketCat[$rightCatId]);
		}

		$writer->writeElement('g:link', $product->getProductUrl($this->app->baseUrl($this->lang)));
		$writer->writeElement('g:mobile_link', $product->getProductUrl($this->lang));
		$writer->writeElement('g:image_link', $helper->image($product->getPhoto(1, 843), 'amazon'));
		for ($i = 2; $i < 8; $i++) {
			$actual = $product->getPhoto($i, 843);
			if ($actual!= false && !empty($actual)) {
				$writer->writeElement('g:additional_image_link', $helper->image($actual,'amazon'));
			}
		}
		$writer->writeElement('g:condition', 'new');


		$writer->writeElement('g:availability', $avai > 0 ? 'in stock' : 'out of stock');
		$writer->writeElement('sizes',implode(',',$sizes));

		$writer->writeElement('g:price', $product->price);

		if($onSale){
			$writer->writeElement('g:sale_price', $product->sale_price);
		}

		$writer->writeElement('g:mpn', $product->itemno . ' ' . $product->productVariant->name);
		$writer->writeElement('g:brand', $product->productBrand->name);
		if (isset($product->productColorGroup) && !$product->productColorGroup->isEmpty()) {
			$writer->writeElement('g:color', $product->productColorGroup->getFirst()->name);
		}
		$writer->startElement('g:shipping');
		$writer->writeElement('g:service', 'Courier');
		$writer->writeElement('g:price', '10.00 EUR');
		$writer->endElement();
		$writer->startElement('g:shipping');
		$writer->writeElement('g:country', 'IT');
		$writer->writeElement('g:service', 'Courier');
		if($product->price > 300) {
			$writer->writeElement('g:price', '0.00 EUR');
		} else {
			$writer->writeElement('g:price', '5.00 EUR');
		}



		$writer->writeElement('g:cpc', $this->calcCPC($product));
		$writer->endElement();
		$writer->endElement();
		return $writer->outputMemory();
	}

	protected function calcCPC($ep) {
        $CPC = [
            'default' => 0.08,

            'brand' => [
                'michael kors' => 0.10,
                'moschino' => 0.14,
                'herno' => 0.14,
                'dsquared 2' => 0.12,
                'stella mccartney' => 0.14,
                'jimmy choo' => 0.14,
                'golden goose' => 0.14,
                'neil barret'  => 0.14,
                'lanvin' => 0.14,
                'philipp plein' => 0.14,
                'valentino'  => 0.14
            ],

            'category' => [
                'sneakers' => 0.10
            ]
        ];

        $pbrand = strtolower($ep->productBrand->name);
        $pcategory = [];
        foreach($ep->productCategory as $cat) {
            $pcategory[] = strtolower($cat->getLocalizedName());
        };

        $finalCPC = 0;
        foreach($pcategory as $v) {
            if (array_key_exists($v, $CPC['category'])) {
                if ($finalCPC < $CPC['category'][$v]) $finalCPC = $CPC['category'][$v];
            }
        }

        if (array_key_exists($pbrand, $CPC['brand'])) {
            if ($finalCPC < $CPC['brand'][$pbrand]) $finalCPC = $CPC['brand'][$pbrand];
        }

        if (!$finalCPC) $finalCPC = $CPC['default'];

        return $finalCPC;


    }

}