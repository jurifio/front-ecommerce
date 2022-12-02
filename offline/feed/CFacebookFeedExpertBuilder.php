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
 * Class CDispatchPreorderToFriend
 * @package bamboo\blueseal\jobs
 * @author Iwes  International Web Ecommerce ServicesTeam <juri@iwes.it>, ${DATE}
 * @copyright (c) Iwes International Web Ecommerce Services - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CFacebookFeedExpertBuilder extends AExpertFeedBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'Facebook';
    }

    public function run($args = null)
    {
        $writer = parent::run($args);

        $url = $this->app->baseUrl(false, 'https') . $this->marketplaceAccount->config['feedUrl'];

        $writer->startElement('rss');
        $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $writer->writeAttribute('version', '2.0');

        $writer->startElement("channel");
        $writer->writeElement('title', 'FaceBook Product Feed');
        $shopFind=\Monkey::app()->repoFactory->create('Shop')->findOneBy(['id'=>$this->marketplaceAccount->config['shopId']]);
        $shopUrl=$shopFind->urlSite;
        $writer->writeElement('link', $shopUrl);
        //$writer->writeElement('link', $this->app->baseUrl(false, 'https'));

        $writer->startElement('author');
        $writer->writeElement('name', 'iwes');
        $writer->endElement();
        $writer->writeElement('description', $shopUrl);
        $writer->writeElement('updated', date(DATE_ATOM, time()));

        /** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = $this->writeProductsMinusDeleted($writer);

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        $this->report('Run', 'End build');
        $this->report('Run', 'End build, errors: ' . $contoErrori);
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
        if ($product->qty > 0 ) {
            $writer->startElement("item");
            $writer->writeElement('g:id',$product->printId());

            $avai = 0;
            $sizes = [];
            $onSale = $product->isOnSale();
            foreach ($product->productPublicSku as $sku) {
                $sizes[] = $sku->productSize->name;
                $avai++;
            }

            $writer->startElement('title');
            $variant = ($product->productVariant) ? $product->productVariant->name : '';
            $prodName = $product->getName();
            $productSeason=\Monkey::app()->repoFactory->create('ProductSeason')->findOneBy(['id'=>$product->productSeasonId]);
            if($productSeason) {
                $name = mb_strtoupper($product->productBrand->name) . ' ' . $variant . ' ' . $prodName . ' ' . $product->productSeason->name;
            }else{
                $name = mb_strtoupper($product->productBrand->name) . ' ' . $variant . ' ' . $prodName;
            }

            if (count($sizes) < 3) $name .= " (" . implode('-',$sizes) . ")";
            $writer->writeCdata($name);
            $writer->endElement();


            $writer->startElement('description');
            $dirtyProduct=\Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['productId'=>$product->id,'productVariantId'=>$product->productVariantId]);
            $dirtyProductId=$dirtyProduct->id;
            $audienceFind=\Monkey::app()->repoFactory->create('DirtyProductExtend')->findOneBy(['dirtyProductId'=>$dirtyProductId]);
            if($audienceFind!=null) {
                $audience = $audienceFind->audience;
            }else{
                $audience='';
            }
            $description=$product->getDescription();
            $description.= ' '.$audience;
            $description.=' '.$product->productSeason->name;
            $writer->writeCdata($description);
            $writer->endElement();


            $product_type = [];
            foreach ($product->productCategory as $category) {
                $cats = $this->app->categoryManager->categories()->getPath($category->id);
                $type = [];
                foreach ($cats as $cat) {
                    if ($cat['id'] == 1) continue;


                    $type[] = \Monkey::app()->repoFactory->create('ProductCategoryTranslation')->findOneBy(['langId' => 1,'productCategoryId'=>$cat['id'],'shopId'=>44])->name;
                    //$type[] = \Monkey::app()->repoFactory->create('ProductCategory',$this->lang)->findOne([$cat['id']])->getLocalizedName();
                }
                $product_type[] = implode(', ',$type);
            }

            $writer->writeElement('g:product_type',implode('; ',$product_type));

            $categories = $product->getMarketplaceAccountCategoryIdsFacebook($this->marketplaceAccount);

                $writer->writeElement('g:google_product_category',$categories[0]);

            $writer->writeElement('g:age_group','all ages');


                $writer->writeElement('g:gender','unisex');

            // $baseUrlLang = $this->app->cfg()->fetch("paths","domain") . "/" . $this->lang->getLang();
            $shopFind=\Monkey::app()->repoFactory->create('Shop')->findOneBy(['id'=>$this->marketplaceAccount->config['shopId']]);
            $shopUrl=$shopFind->urlSite;
            $baseUrlLang=$shopUrl. "/" . $this->lang->getLang();
            $writer->writeElement('g:link', $product->getProductUrl($baseUrlLang,$this->marketplaceAccount->getCampaignCode()));
            $writer->writeElement('g:mobile_link',$product->getProductUrl($baseUrlLang,$this->marketplaceAccount->getCampaignCode()));
            $linkImage=$this->helper->image($product->getPhoto(1,1124),'amazon');
            $imageLink=str_replace('https://cdn.iwes.it/','https://d2jto9wsi7nqrj.cloudfront.net/',$linkImage);
            $writer->writeElement('g:image_link',$imageLink);

            for ($i = 2; $i < 8; $i++) {
                $actual = $product->getPhoto($i,1124);
                if ($actual != false && !empty($actual)) {
                    $linkImageAdditional=$this->helper->image($actual,'amazon');
                    $imageLinkAdditional=str_replace('https://cdn.iwes.it/','https://d2jto9wsi7nqrj.cloudfront.net/',$linkImageAdditional);
                    $writer->writeElement('g:additional_image_link',$imageLinkAdditional);
                }
            }
            $writer->writeElement('g:condition','new');
           // $writer->writeElement('url', $product->getProductUrl($baseUrlLang,$this->marketplaceAccount->getCampaignCode()));


            $writer->writeElement('g:availability',$avai > 0 ? 'in stock' : 'out of stock');
            //$writer->writeElement('sizes',implode(';',$sizes));
           foreach ($sizes as $size) {
                  $writer->writeElement('g:size', $size);
            }
            $priceActive = \Monkey::app()->repoFactory->create('ProductSku')->findOneBy(['productId' => $product->id,'productVariantId' => $product->productVariantId]);

            $price = number_format($priceActive->price,2,'.','');

            $writer->writeElement('g:price',$price . ' EUR');
            /*if ($product->isOnSale == 1) {
                $salePrice = number_format($priceActive->salePrice,2,'.','');
                $writer->writeElement('g:sale_price',$salePrice . ' EUR');
            }*/
            $writer->writeElement('g:mpn',$product->itemno . ' ' . $product->productVariant->name);
            $writer->writeElement('g:brand',$product->productBrand->name);
            $creationDate= (new \DateTime($product->creationDate))->format('Y-m-d H:i:s');

            if($creationDate >'2022-10-01 00:00:00'){
                $writer->writeElement('custom_label_0','Nuovi Arrivi');
            }else{
                $writer->writeElement('custom_label_0','Prodotti in magazzino');
            }
            if (!is_null($product->productColorGroup)) {
                $writer->writeElement('g:color',$product->productColorGroup->productColorGroupTranslation->getFirst()->name);
            }
            $productEan =  \Monkey::app()->repoFactory->create('ProductEan')->findOneBy(['productId' => $product->id, 'productVariantId' => $product->productVariantId,'used'=>'1']);
            if ($productEan != null) {
                $ean = $productEan->ean;
                if(strlen($ean)==13) {
                    $writer->writeElement('g:gtin',$ean);
                }
            }
            $writer->startElement('g:shipping');
            $writer->writeElement('g:service','IT_StandardInternational');
            $writer->writeElement('g:price','10.00 EUR');
            $writer->writeElement('g:country','FR');
            $writer->endElement();
            $writer->startElement('g:shipping');
            $writer->writeElement('g:country','IT');
            $writer->writeElement('g:service','Courier');
            $writer->writeElement('g:price','5.00 EUR');
            $writer->endElement();
            $writer->startElement('g:shipping');
            $writer->writeElement('g:country','US');
            $writer->writeElement('g:service','IT_ExpeditedInternational');
            $writer->writeElement('g:price','40.00 EUR');


            $writer->endElement();
            $writer->endElement();
        }
        return $writer->outputMemory();
    }

}