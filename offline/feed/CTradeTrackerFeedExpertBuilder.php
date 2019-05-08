<?php
namespace bamboo\ecommerce\offline\feed;

use bamboo\core\intl\CLang;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\core\jobs\ACronJob;
use bamboo\core\theming\CWidgetHelper;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductPhoto;

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
class CTradeTrackerFeedExpertBuilder extends AExpertFeedBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'TradeTracker';
    }

    public function run($args = null)
    {
        $writer = parent::run($args);


        $url = $this->app->baseUrl(false, 'https') . $this->marketplaceAccount->config['feedUrl'];

        $writer->startElement('productFeed');
        /** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = $this->writeProductsMinusDeleted($writer);
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
    public function writeProductEntry(CProduct $product = null,CMarketplaceAccountHasProduct $marketplaceAccountHasProduct = null)
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(!$this->minized);
        $writer->startElement("product");
        /** @var CProduct $product */

        $writer->writeElement('ID',$product->printId());
        $writer->writeElement('lowestPrice',$product->getDisplayActivePrice());
        $writer->writeElement('originalPrice',$product->getDisplayPrice());
        $writer->writeElement('productURL',$product->getProductUrl(null,$this->marketplaceAccount->getCampaignCode()));
        $writer->writeElement('stock',$product->qty);
        $writer->writeElement('deliveryCost',$product->getDisplayPrice() > 300 ? "0.00" : "5.00");
        $writer->writeElement('UPC',$product->printCpf());
        $writer->writeElement('name',$product->getName());
        $writer->startElement('field');
        $writer->writeAttribute('name',"brand");
        $writer->writeAttribute('value',$product->productBrand->name);
        $writer->endElement();
        $writer->startElement('images');
        for ($i = 1;$i< 10; $i++) {
            if(!empty($product->getPhoto($i,CProductPhoto::SIZE_MEDIUM))) {
                $writer->writeElement('image',$this->helper->image($product->getPhoto($i, CProductPhoto::SIZE_MEDIUM), 'amazon'));
            }
        }
        $writer->endElement();

        $writer->startElement('variations');

        foreach ($product->productPublicSku as $productPublicSku) {
            if($productPublicSku->stockQty > 0) {
                $writer->startElement('variation');
                $writer->writeElement('size', $productPublicSku->productSize->name);
                $writer->writeElement('color', $productPublicSku->product->productColorGroup->productColorGroupTranslation->getFirst()->name);
                $writer->writeElement('price', $productPublicSku->price);
                $writer->endElement();
            }
        }
        $writer->endElement();

        $writer->startElement('categories');
        foreach ($product->productCategory as $category) {
            $categoryPath = $category->getLocalizedPathArray();
            $writer->writeElement('category',$categoryPath[0]);
            unset($categoryPath[0]);
            foreach ($categoryPath as $piece) {
                $writer->writeElement('subcategory',$piece);
            }
        }
        $writer->endElement();

        $writer->endElement();

        return $writer->outputMemory();
    }
}