<?php
namespace bamboo\ecommerce\offline\feed;

use bamboo\core\intl\CLang;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\core\jobs\ACronJob;
use bamboo\core\theming\CWidgetHelper;
use bamboo\domain\entities\CProduct;

/**
 * Class CStileoFeedExpertBuilder
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
class CStileoFeedExpertBuilder extends AExpertFeedBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'Stileo';
    }

    public function run($args = null)
    {
        $writer = parent::run($args);
        $writer->startElement('offers');

        //** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = $this->writeMarketplaceProducts($writer);
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        $this->report( 'Run', 'End build, errors: ' . $contoErrori);
    }

    /**
     * @param CProduct $product
     * @param CMarketplaceAccountHasProduct $marketplaceAccountHasProduct
     * @return string
     */
    public function writeProductEntry(CProduct $product = null, CMarketplaceAccountHasProduct $marketplaceAccountHasProduct = null)
    {
        $product = $marketplaceAccountHasProduct->product;
        $baseUrlLang = $this->app->baseUrl($this->lang);
        $avai = 0;
        $sizes = [];
        $onSale = $product->isOnSale();
        foreach ($product->productPublicSku as $sku) {
            if ($sku->stockQty > 0) {
                $sizes[] = $sku->productSize->name;
                $avai++;
            }
        }


        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(!$this->minized);
        $writer->startElement("offer");

        $writer->writeElement('id',$product->printId());

        $writer->startElement("url");
        $writer->writeCdata($product->getProductUrl($baseUrlLang,$marketplaceAccountHasProduct->marketplaceAccount->getCampaignCode()));
        $writer->endElement();

        if ($onSale && $product->sale_price != 0) {
            $writer->writeElement("price",$product->getDisplaySalePrice());
            $writer->writeElement("oldprice",$product->getDisplayPrice());
        } else {
            $writer->writeElement("price",$product->getDisplayPrice());
        }

        $writer->startElement("brand");
        $writer->writeCdata($product->productBrand->name);
        $writer->endElement();

        $writer->writeElement("avail",$avai);

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

        $fullCatName = implode(" ", $product_type);
        $writer->startElement("cat");
        $writer->writeCdata($fullCatName);
        $writer->endElement();

        $writer->startElement("name");
        $name = $product->getName();
        if (count($sizes) < 3) $name .= " (" . implode(',', $sizes) . ")";
        $writer->writeCdata($name);
        $writer->endElement();

        $writer->startElement("imgs");
        for ($i = 0; $i < 8; $i++) {
            $actual = $product->getPhoto($i, 843);
            if ($actual != false && !empty($actual)) {
                $writer->startElement("img");
                $writer->writeCdata($this->helper->image($actual, 'amazon'));
                $writer->endElement(); //img
            }
        }
        $writer->endElement(); //imgs

        $writer->startElement("sizes");
        $writer->writeCdata(implode('; ', $sizes));
        $writer->endElement(); //sizes

        $writer->startElement("desc");
        $writer->writeCdata($product->getDescription());
        $writer->endElement(); //desc


        $writer->startElement('title');
        $name = $product->getName();
        if (count($sizes) < 3) $name .= " (" . implode(',', $sizes) . ")";
        $writer->writeCdata($name);
        $writer->endElement();


        $writer->startElement('description');
        $writer->writeCdata($product->getDescription());
        $writer->endElement(); //desc

        $writer->writeElement('cpc',$marketplaceAccountHasProduct->fee);

        $writer->endElement(); //offer

        return $writer->outputMemory();

    }

}