<?php

namespace bamboo\ecommerce\offline\feed;

use bamboo\core\intl\CLang;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\core\jobs\ACronJob;
use bamboo\core\theming\CWidgetHelper;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductEan;
use bamboo\domain\entities\CProductPhoto;

/**
 * Class CStylightFeedExpertBuilder
 * @package bamboo\ecommerce\offline\feed
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CStylightFeedExpertBuilder extends AExpertFeedBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return strcasecmp($marketplaceAccount->marketplace->name, "Stylight") == 0;
    }

    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $writer = parent::run($args);

        $writer->startElement('Products');

        //** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = $this->writeMarketplaceProducts($writer);

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        $this->report('Run', 'End build, errors: ' . $contoErrori);
    }

    /**
     * @param CProduct|null $product
     * @param CMarketplaceAccountHasProduct|null $marketplaceAccountHasProduct
     * @return string
     */
    public function writeProductEntry(CProduct $product = null, CMarketplaceAccountHasProduct $marketplaceAccountHasProduct = null)
    {
        $product = $marketplaceAccountHasProduct->product;
        $productEanRepo = \Monkey::app()->repoFactory->create('ProductEan');

        $baseUrlLang = $this->app->baseUrl($this->lang);
        // $baseUrlLang = $urlSite;
        $avai = 0;
        $product->price = 0;
        $product->sale_price = 0;
        $sizes = [];
        $onSale = $product->isOnSale();
        foreach ($product->productPublicSku as $productPublicSku) {
            $productEan = $productEanRepo->findOneBy(['productId' => $productPublicSku->productId, 'productVariantId' => $productPublicSku->productVariantId,'productSizeId'=>$productPublicSku->productSizeId]);
            if ($productEan != null) {
                $ean = $productEan->ean;
            }else{
                $ean='';
            }


            if ($productPublicSku->stockQty > 0) {
                switch ($productPublicSku->productSize->name) {
                    case 'Taglia Unica':
                    case 'U':
                    case 'D':
                    case 'UNI':
                    case 'T.U.':
                    case 'UNICA':
                    case 'QT':
                        $sizes[] = 'unisize';
                        break;
                    default:
                        $sizes[] = $productPublicSku->productSize->name;
                        break;
                }

                $avai++;
            }


            $writer = new \XMLWriter();
            $writer->openMemory();
            $writer->setIndent(!$this->minized);
            $writer->startElement("Product");

            $writer->writeElement("producturl", $product->getProductUrl($marketplaceAccountHasProduct->marketplaceAccount->urlSite, $marketplaceAccountHasProduct->marketplaceAccount->getCampaignCode()));
            foreach ($product->productCategory as $category) {
                if ($category->id == 1) continue;
                $writer->writeElement("category", $category->getLocalizedPath("/"));
            }

            $writer->writeElement('Color', $product->productColorGroup->productColorGroupTranslation->getFirst()->name);
            $writer->writeElement('Sizes', (implode(',', $sizes)));
            $writer->writeElement('name', $product->getName());
            $writer->writeElement("img", $this->helper->image($product->getPhoto(1, CProductPhoto::SIZE_MEDIUM), 'amazon'));

            $writer->writeElement("price", $product->getDisplayActivePrice());

            $writer->writeElement('internalid', $product->printId());
            $writer->writeElement('upc', $product->itemno);
            $writer->writeElement('ean13', $ean);

            if ($onSale) {
                $writer->writeElement("msrp", $product->getDisplayPrice());
            }

            $writer->startElement('desc');
            $writer->writeCdata($product->getDescription());
            $writer->endElement();

            $writer->writeElement('Gender', $product->getGender());

            $writer->startElement("brand");
            $writer->writeCdata($product->productBrand->name);
            $writer->endElement();

            $writer->endElement();

            return $writer->outputMemory();
        }

    }

}

