<?php

namespace bamboo\ecommerce\offline\feed;

use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductSheetActual;
use bamboo\domain\entities\CProductSku;

/**
 * Class ABluesealXmlExpertBuilder
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
abstract class ABluesealXmlExpertBuilder extends AExpertFeedBuilder
{
    public function run($args = null)
    {
        $writer = parent::run($args);

        $writer->startElement('rss');
        $writer->writeAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xmlns', 'http://iwes.it/schemas/productsFeed/2.1/');
        $writer->writeAttribute('xsi:schemaLocation', 'http://iwes.it/schemas/productsFeed/2.1/ http://iwes.it/schemas/productsFeed/2.1/');
        $writer->startElement('feed');
        $writer->writeElement('shop', $this->marketplaceAccount->config['shop']);
        $writer->writeElement('shopId', $this->marketplaceAccount->config['shopId']);
        $writer->writeElement('date', (new \DateTime())->format(DATE_ATOM));
        $writer->writeElement('action', 'set');

        /** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = 0;

        $idCycle = $this->app->dbAdapter->query("SELECT group_concat( DISTINCT concat_ws('-',m.productId,
                                                                    m.productVariantId,
                                                                    m.marketplaceId,
                                                                    m.marketplaceAccountId)) AS code
                                                FROM MarketplaceAccountHasProduct m, vProductSortingView p
                                                WHERE   m.productId = p.product AND 
                                                        m.productVariantId = p.variant AND 
                                                        marketplaceId = ? AND 
                                                        marketplaceAccountId = ? AND 
                                                        m.isDeleted = 0 
                                                GROUP BY m.productId,m.marketplaceId,m.marketplaceAccountId",
            [$this->marketplaceAccount->marketplaceId, $this->marketplaceAccount->id])->fetchAll(\PDO::FETCH_COLUMN, 0);


        $this->report('run', 'working ' . count($idCycle) . ' products');
        $writer->startElement('items');
        foreach ($idCycle as $item) {

            set_time_limit(5);
            $writer->writeRaw($this->writeItem($item));
        }
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        $this->report('Run', 'End build, errors: ' . $contoErrori);
    }

    public function writeProductEntry(CProduct $product = null, CMarketplaceAccountHasProduct $marketplaceAccountHasProduct = null)
    {
        //useless
    }

    public function writeItem($item)
    {
        $contoErrori = 0;

        $productRepo = \Monkey::app()->repoFactory->create('Product');
        $marketplaceAccountHasProductRepo = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct');
        try {
            $writer = new \XMLWriter();
            $writer->openMemory();
            $writer->setIndent(!$this->minized);
            $variants = explode(',', $item);
            $marketplaceAccountHasProduct = $marketplaceAccountHasProductRepo->findOneByStringId($variants[0]);
            /** @var CProduct $product */
            $product = $marketplaceAccountHasProduct->product;
            $writer->startElement('item');
            $writer->writeElement('brand', $product->productBrand->name);
            $writer->writeElement('season', $product->productSeason->name);
            $writer->startElement('categories');
            $categories = $product->productCategory->getFirst()->getLocalizedPathArray();
            $writer->writeElement('audience', $categories[0]);
            for ($k = 1; isset($categories[$k]); $k++) {
                $writer->writeElement('cat' . $k, $categories[$k]);
            }
            $writer->endElement();
            $writer->startElement('variants');
            foreach ($variants as $variant) {
                $marketplaceAccountHasProduct = $marketplaceAccountHasProductRepo->findOneByStringId($variant);
                $writer->startElement('variant');
                /** @var CProduct $product */
                $product = $marketplaceAccountHasProduct->product;
                $writer->writeElement('cpf', $product->itemno);
                $writer->writeElement('extId', $product->printId());
                $writer->writeElement('name', $product->getName());
                $writer->writeElement('brandColor', $product->productVariant->name);
                $writer->writeElement('colorDescription', $product->productVariant->description);
                $writer->writeElement('mainColor', $product->productColorGroup->getLocalizedName());
                $writer->startElement('sizes');
                foreach ($product->productPublicSku as $sku) {
                    /** @var CProductSku $sku */
                    $writer->startElement('size');
                    $writer->writeElement('sku', $sku->printId());
                    $writer->writeElement('barcode', isset($sku->ean) ? $sku->ean: '');
                    $writer->writeElement('size', $sku->productSize->name);
                    $writer->writeElement('quantity', $sku->stockQty);
                    $writer->writeElement('value', $sku->value);
                    $writer->writeElement('price', $sku->getPrice());
                    $writer->writeElement('salePrice', $sku->getSalePrice());
                    $writer->endElement();
                }
                $writer->endElement();
                $writer->startElement('photos');
                for ($i = 1; $i < 8; $i++) {
                    $actual = $product->getPhoto($i, 843);
                    if ($actual != false && !empty($actual)) {
                        $writer->startElement('photo');
                        $writer->writeElement('url', $this->app->cfg()->fetch("general", "product-photo-host") . $actual);
                        $writer->writeElement('position', $i);
                        $writer->endElement();
                    }
                }
                $writer->endElement();
                $writer->startElement('details');
                foreach ($product->productSheetActual as $productSheetActual) {
                    /** @var CProductSheetActual $productSheetActual */
                    $writer->startElement('detail');
                    $writer->writeElement('label', $productSheetActual->productDetailLabel->getLocalizedName());
                    $writer->writeElement('content', $productSheetActual->productDetail->getLocalizedDetail());
                    $writer->endElement();
                }
                $writer->endElement();
                $writer->endElement();

                if ($marketplaceAccountHasProduct) {
                    if (is_null($marketplaceAccountHasProduct->insertionDate)) {
                        $marketplaceAccountHasProduct->insertionDate = $this->time();
                        $marketplaceAccountHasProduct->publishDate = $this->time();
                        $marketplaceAccountHasProduct->lastRevised = $this->time();
                    }
                    $marketplaceAccountHasProduct->isToWork = 0;
                    $marketplaceAccountHasProduct->isRevised = 1;
                    $marketplaceAccountHasProduct->hasError = 0;
                    $marketplaceAccountHasProduct->update();
                }
                unset($marketplaceAccountHasProduct);
            }
            $writer->endElement();
            $writer->endElement();
            return $writer->outputMemory();
        } catch (\Throwable $e) {
            $this->debug('VariantError', $e->getMessage(), $e);
            if ($marketplaceAccountHasProduct && !is_null($marketplaceAccountHasProduct)) {
                $marketplaceAccountHasProduct->errorRequest = $e->getMessage() . "\n" . $e->getTraceAsString();
                $marketplaceAccountHasProduct->isRevised = 0;
                $marketplaceAccountHasProduct->hasError = 1;
                $marketplaceAccountHasProduct->update();
            }
            return "";
        }
    }
}