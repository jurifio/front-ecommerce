<?php
namespace bamboo\ecommerce\offline\feed;

use bamboo\core\intl\CLang;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\domain\entities\CProduct;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\jobs\ACronJob;
use bamboo\core\theming\CWidgetHelper;

/**
 * Class CShopALikeFeedExportBuilder
 * @package bamboo\ecommerce\offline\feed
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CShopALikeFeedExportBuilder extends AExpertFeedBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'ShopALike';
    }

	public function run($args = null)
	{
	    $writer = parent::run($args);

        $url = $this->app->baseUrl(false, 'https') . $this->marketplaceAccount->config[' mk'];

		$writer->startElement('rss');
		$writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
		$writer->writeAttribute('version', '2.0');

		$writer->startElement("channel");
		$writer->writeElement('title', 'ShopALike Product Feed');
		$writer->writeElement('link', $this->app->baseUrl(false));

		$writer->startElement('author');
		$writer->writeElement('name', 'iwes');
		$writer->endElement();
		$writer->writeElement('description', $url);
		$writer->writeElement('updated', date(DATE_ATOM, time()));

        $contoErrori = $this->writeMarketplaceProducts($writer);

		$writer->endElement();
		$writer->endElement();
		$writer->endDocument();
		$writer->flush();

        $this->report( 'Run', 'End build');
		$this->report( 'Run', 'End build, errors: ' . $contoErrori);
	}

    /**
     * @param CProduct|null $product
     * @param CMarketplaceAccountHasProduct|null $marketplaceAccountHasProduct
     * @return string
     */
	public function writeProductEntry(CProduct $product = null, CMarketplaceAccountHasProduct $marketplaceAccountHasProduct = null)
	{
		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(!$this->minized);
		$writer->startElement("item");
        $product = $marketplaceAccountHasProduct->product;
		$writer->writeElement('g:id', $product->printId());

		$avai = 0;
		$sizes = [];
		$onSale = $product->isOnSale();
		foreach ($product->productPublicSku as $sku) {
			if ($sku->stockQty > 0) {
				$sizes[] = $sku->productSize->name;
				$avai++;
			}
		}

		$writer->startElement('title');
        $name = $product->getName();
        if(count($sizes)<3) $name.= " (".implode('-',$sizes).")";
		$writer->writeCdata($name);
		$writer->endElement();

		$writer->startElement('description');

		$writer->writeCdata($product->getDescription());
		$writer->endElement();

		$product_type = [];
        foreach ($product->productCategory as $category) {
            $cats = $this->app->categoryManager->categories()->getPath($category->id);
            $type = [];
            foreach ($cats as $cat) {
                if ($cat['id'] == 1) continue;
                $type[] = \Monkey::app()->repoFactory->create('ProductCategory', $this->lang)->findOne([$cat['id']])->getLocalizedName();
            }
            $product_type[] = implode(', ', $type);
        }

        $writer->writeElement('g:product_type', implode('; ', $product_type));

        $categories = $product->getMarketplaceAccountCategoryIds($marketplaceAccountHasProduct->marketplaceAccount);
        $writer->writeElement('g:google_product_category', $categories[0]);

		$writer->writeElement('g:link', $product->getProductUrl($marketplaceAccountHasProduct->marketplaceAccount->urlSite,$marketplaceAccountHasProduct->marketplaceAccount->getCampaignCode()));
		$writer->writeElement('g:mobile_link', $product->getProductUrl($marketplaceAccountHasProduct->marketplaceAccount->urlSite,$marketplaceAccountHasProduct->marketplaceAccount->getCampaignCode()));
		$writer->writeElement('g:image_link', $this->helper->image($product->getPhoto(1, 843), 'amazon'));
		for ($i = 2; $i < 8; $i++) {
			$actual = $product->getPhoto($i, 843);
			if ($actual!= false && !empty($actual)) {
				$writer->writeElement('g:additional_image_link', $this->helper->image($actual,'amazon'));
			}
		}
		$writer->writeElement('g:condition', 'new');


		$writer->writeElement('g:availability', $avai > 0 ? 'in stock' : 'out of stock');
		$writer->writeElement('sizes',implode(',',$sizes));

		$writer->writeElement('g:price', $product->getDisplayActivePrice());

		if($onSale){
			$writer->writeElement('g:sale_price', $product->getDisplaySalePrice());
		}

		$writer->writeElement('g:mpn', $product->itemno . ' ' . $product->productVariant->name);
		$writer->writeElement('g:brand', $product->productBrand->name);

        if (!is_null($product->productColorGroup)) {
            $writer->writeElement('g:color', $product->productColorGroup->productColorGroupTranslation->getFirst()->name);
        }

		$writer->startElement('g:shipping');
		$writer->writeElement('g:service', 'Courier');
		$writer->writeElement('g:price', '10.00 EUR');
		$writer->endElement();
		$writer->startElement('g:shipping');
		$writer->writeElement('g:country', 'IT');
		$writer->writeElement('g:service', 'Courier');

        if($product->getDisplaySalePrice() > 300) {

			$writer->writeElement('g:price', '0.00 EUR');
		} else {
			$writer->writeElement('g:price', '5.00 EUR');
		}

		$writer->writeElement('g:cpc', $marketplaceAccountHasProduct->fee);

		$writer->endElement();
		$writer->endElement();
		return $writer->outputMemory();
	}
}