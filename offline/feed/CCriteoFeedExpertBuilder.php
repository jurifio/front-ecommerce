<?php
namespace bamboo\ecommerce\offline\feed;

use bamboo\core\intl\CLang;
use bamboo\core\traits\TMagicObject;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\core\jobs\ACronJob;
use bamboo\core\theming\CWidgetHelper;
use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\domain\entities\CProduct;

/**
 * Class CCriteoFeedExpertBuilder
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
class CCriteoFeedExpertBuilder extends AExpertFeedBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'Criteo';
    }

    public function run($args = null)
    {
        $writer = parent::run($args);

	    $writer->startElement('products');
        /** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = 0;

        $this->writeMarketplaceProducts($writer);

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

        $childs = [];
        foreach ($product->productPublicSku as $sku) {
            if ($sku->stockQty > 0) {
                $childs[] = $sku->printId();
            }
        }

        $childs = array_unique($childs);
        $this->write($writer,$product->printId(),
                    $product,
                    !$marketplaceAccountHasProduct->isDeleted,
                    '',
                    $childs,
                    $this->marketplaceAccount->getCampaignCode());

        foreach($product->productPublicSku as $productPublicSku) {
            if($productPublicSku->stockQty > 0) $this->write($writer,$productPublicSku->printId(),$product,1,'',null);
        }

        return $writer->outputMemory();
	}

    /**
     * @param \XMLWriter $writer
     * @param $id
     * @param CProduct $product
     * @param $recommendable
     * @param $cpc
     * @param null $childs
     * @param null $campaingName
     */
    public function write(\XMLWriter $writer,$id,CProduct $product,$recommendable,$cpc,$childs = null,$campaingName = null)
    {
        $writer->startElement("product");
        $writer->writeAttribute('id', $id);
        $writer->startElement('name');
        $writer->writeCdata($product->getName());
        $writer->endElement();

        try {
            $writer->writeElement('producturl', $product->getProductUrl($this->app->baseUrl($this->lang->getLang(),'https'),$campaingName));
        } catch (\Throwable $e) {
            $writer->writeElement('producturl', $product->getProductUrl($this->app->baseUrl(true,'https'),$campaingName));
        }


        $writer->writeElement('smallimage',  $this->helper->image($product->getPhoto(1, 562), 'amazon'));
        $writer->writeElement('bigimage',  $this->helper->image($product->getPhoto(1, 843), 'amazon'));

        $writer->startElement('description');
        $writer->writeCdata($product->getDescription());
        $writer->endElement();



        if($product->isOnSale()) {
            $writer->writeElement('retailprice',$product->getDisplayPrice());
            $writer->writeElement('price',$product->getDisplaySalePrice());
            $discount = ($product->getDisplayPrice() - $product->getDisplaySalePrice()) / $product->getDisplayPrice() * 100;
            $writer->writeElement('discount', round($discount));
        } else {
            $writer->writeElement('price',$product->getDisplayPrice());
        }

        $writer->writeElement('recommendable', (int) $recommendable);
        $writer->writeElement('instock', 1);

        $cats = \Monkey::app()->repoFactory->create('ProductCategory')->getStringPathCategory($product->productCategory->getFirst(),'-');
        $cats = explode('-',$cats);

        for($i=0;$i<3 && isset($cats[$i]);$i++) {
            $writer->writeElement('categoryid'.($i+1),$cats[$i]);
        }

        if(!is_null($childs)) {
            $writer->writeElement('child_id',implode(',',$childs));
        }

        $writer->endElement();
    }
}