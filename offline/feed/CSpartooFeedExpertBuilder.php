<?php
namespace bamboo\ecommerce\offline\feed;

use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductPhoto;
use bamboo\domain\entities\CProductPublicSku;

/**
 * Class CSpartooFeedExpertBuilder
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
class CSpartooFeedExpertBuilder extends AExpertFeedBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'Spartoo';
    }

    public function run($args = null)
    {
        $writer = parent::run($args);
        $writer->startElement('root');
        $writer->startElement('products');

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

        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(!$this->minized);
        $writer->startElement("product");

        $writer->writeElement('reference_partenaire',$product->printId());

        $writer->startElement("product_name");
        $writer->writeCdata($product->getName());
        $writer->endElement();

        $writer->startElement("manufacturers_name");
        $writer->writeCdata($product->productBrand->name);
        $writer->endElement();

        $genders = $product->getGendersId();
        if(count($genders) != 1) $gender = 'M';
        elseif($genders[0] == 2) $gender = 'F';
        elseif($genders[0] == 3) $gender = 'H';
        else $gender = 'B';

        $writer->writeElement('product_sex', $gender);

        $writer->writeElement('product_price',$product->getDisplayPrice());

        //COLOR_ID

        //PRODUCT_STYLE

        $writer->writeElement('product_description', $product->getDescription());

        $writer->writeElement('product_color', empty($product->productVariant->description) ? $product->productVariant->name : $product->productVariant->description);

        $writer->startElement('size_list');

        foreach ($product->productPublicSku as $productPublicSku) {
            /** @var CProductPublicSku $productPublicSku */
            $writer->startElement('size');
            $writer->writeElement('size_quantity',$productPublicSku->stockQty);
            $writer->writeElement('size_reference',$productPublicSku->printId());
            //fixme anagrafica taglie
            $writer->writeElement('size_name',$productPublicSku->productSize->name);
        }


        for($i=1;$i < 9;$i++) {
            $writer->writeElement('url'.$i,$product->getPhoto($i,CProductPhoto::SIZE_MEDIUM));
        }


        if($product->isOnSale()) {
            $writer->startElement('discount');
            $writer->writeElement('stopdate', (new \DateTime())->add(new \DateInterval('P1D'))->getTimestamp());
            $writer->writeElement('price_discount',$product->getDisplaySalePrice());
        }


        $writer->endElement(); //product

        return $writer->outputMemory();

    }

}