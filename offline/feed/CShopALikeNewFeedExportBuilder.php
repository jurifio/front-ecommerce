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
 * Class CShopALikeNewFeedExportBuilder
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
class CShopALikeNewFeedExportBuilder extends AExpertFeedBuilder
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

        $writer->startElement('offers');

        //** INIZIO INSERIMENTO PRODOTTI */
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
        $productEanRepo = \Monkey::app()->repoFactory->create('ProductEan');
        $productFind=\Monkey::app()->repoFactory->create('Product');
        $product = $marketplaceAccountHasProduct->product;
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(!$this->minized);
        $writer->startElement("offer");

        $writer->writeElement('itemId', $product->printId());
        $writer->writeElement('deepLink', $product->getProductUrl($marketplaceAccountHasProduct->marketplaceAccount->urlSite,$marketplaceAccountHasProduct->marketplaceAccount->getCampaignCode()));
        $avai = 0;
        $sizes = [];
        $onSale = $product->isOnSale();
        foreach ($product->productPublicSku as $sku) {
            if ($sku->stockQty > 0) {
                $sizes[] = $sku->productSize->name;
                $avai++;
            }
        }
        $name = $product->getName();
        $writer->writeElement('name', $name);
        $n=1;
        foreach ($product->productCategory as $category) {
            if ($category->id == 1) continue;
             if($n==1) {
                 $writer->writeElement("category",$category->getLocalizedPath("/"));
             }
            $n++;

        }
        $categories = $product->getMarketplaceAccountCategoryIds($marketplaceAccountHasProduct->marketplaceAccount);
        $writer->writeElement('category1', $categories[0]);
        $categoriesGender = $product->productCategory->getFirst()->getLocalizedPathArray();
        $writer->writeElement('gender', $categoriesGender[0]);
        $writer->writeElement('color', $product->productColorGroup->productColorGroupTranslation->getFirst()->name);
        $writer->writeElement('brand', $product->productBrand->name);
        $writer->startElement('material');
        $name = $product->getName();
        if(count($sizes)<3) $name.= " (".implode('-',$sizes).")";
        $writer->writeCdata($name);
        $writer->endElement();

        $writer->startElement('description');

        $writer->writeCdata($product->getDescription());
        $writer->endElement();
        if($onSale){
            $writer->writeElement('oldPrice', $product->getDisplayPrice());
            $writer->writeElement('price', $product->getDisplaySalePrice());
        }else{
            $writer->writeElement('price', $product->getDisplayPrice());
        }
        $writer->writeElement('currency', 'EUR');
        $writer->writeElement('deliveryTime', '2-3 giorni lavorativi');
        $writer->writeElement('shippingCosts', '10.00 EUR');
        $writer->writeElement('sizes',implode(',',$sizes));
        $writer->writeElement('image', $this->helper->image($product->getPhoto(1, 843), 'amazon'));

        for ($i = 2; $i < 8; $i++) {
            $actual = $product->getPhoto($i, 843);
            $nametag='image'.$i;
            if ($actual!= false && !empty($actual)) {
                $writer->writeElement($nametag, $this->helper->image($actual,'amazon'));
            }
        }

        $writer->endElement();
        return $writer->outputMemory();
    }
}