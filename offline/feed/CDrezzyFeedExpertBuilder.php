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
 * Class CDrezzyFeedExpertBuilder
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
class CDrezzyFeedExpertBuilder extends AExpertFeedBuilder
{

    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'Drezzy';
    }


    public function run($args = null)
    {
        $writer = parent::run($args);

        $writer->startElement('Products');

        /** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = $this->writeMarketplaceProducts($writer);
		
	    $writer->endElement();
	    $writer->endDocument();
	    $writer->flush();

	    $this->report('Run','End build, errors: '.$contoErrori);
    }

    /**
     * @param CProduct|null $product
     * @param CMarketplaceAccountHasProduct|null $marketplaceAccountHasProduct
     * @return string
     */
    public function writeProductEntry(CProduct $product = null, CMarketplaceAccountHasProduct $marketplaceAccountHasProduct = null)
	{

		$protocol = 'https://';

		$writer = new \XMLWriter();
		$writer->openMemory();
		$writer->setIndent(!$this->minized);
		$writer->startElement("Offer");
        $product = $marketplaceAccountHasProduct->product;
		$writer->writeElement('Code', $product->printCpf());
		$writer->writeElement('SKU', $product->printId());

		$qty = 0;
		$onSale = $product->isOnSale();
		$sizes = [];
		foreach ($product->productPublicSku as $sku) {
            $sizes[] = $sku->productSize->name;
		}


        $variant = ($product->productVariant) ? $product->productVariant->name : '';
        $prodName = ($product->productNameTranslation) ? $product->productNameTranslation->getFirst()->name : $product->itemno;
		$name = mb_strtoupper($product->productBrand->name) . ' ' . $variant . ' ' . $prodName;

		if(count($sizes)<3) $name .= " (".implode('-',$sizes).")";
		$writer->writeElement('Name', $name);

		$writer->writeElement('Descrition', strip_tags($product->getDescription()));


		$product_type = [];
		
		foreach($product->productCategory as $category){
			$cats = $this->app->categoryManager->categories()->getPath($category->id);
			$type = [];
			foreach ($cats as $cat) {
				if($cat['id'] == 1) continue;
				$type[] = \Monkey::app()->repoFactory->create('ProductCategory')->findOne([$cat['id']])->getLocalizedName();
			}
			$product_type[] = implode(', ',$type);
		}
		$writer->writeElement('Category', implode('; ', $product_type));

		$baseUrlLang = $this->app->cfg()->fetch("paths","domain")."/".$this->lang->getLang();
		$writer->writeElement('Link',  'https://' . $product->getProductUrl($baseUrlLang,$marketplaceAccountHasProduct->marketplaceAccount->getCampaignCode()));
		$writer->writeElement('Image', $this->helper->image($product->getPhoto(1, 843), 'amazon'));
		$addImg = 2;
		for ($i = 2; $i < 8; $i++) {
			if (3 < $addImg) break;
			$actual = $product->getPhoto($i, 843);
			if ($actual!= false && !empty($actual)) {
				$writer->writeElement('Image' . $addImg, $this->helper->image($actual,'amazon'));
				$addImg++;
			}
		}
		$writer->writeElement('Stock', $qty);
		$writer->writeElement('OriginalPrice', number_format($product->getDisplayPrice(), 2, ',', ''));
        $freeShipping = false;
		if($onSale){
			$writer->writeElement('Price', number_format($product->getDisplaySalePrice(), 2, ',', ''));
            if($product->getDisplaySalePrice() > 300 ) {
                $freeShipping = true;
            }
		} else {
            $writer->writeElement('Price', number_format($product->getDisplayPrice(), 2, ',', ''));
            if($product->getDisplaySalePrice() > 300 ) {
                $freeShipping = true;
            }
        }
		$writer->writeElement('EanCode', '');
		$writer->writeElement('Brand', $product->productBrand->name);

        if($freeShipping) {
            $writer->writeElement('ShippingCost', '0,00');
        } else {
            $writer->writeElement('ShippingCost', '5,00');
        }


		$writer->endElement();
		return $writer->outputMemory();
	}
}