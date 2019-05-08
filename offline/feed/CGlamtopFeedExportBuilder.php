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
 * Class CGlamtopFeedExportBuilder
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
class CGlamtopFeedExportBuilder extends AExpertFeedBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'Glamtop';
    }

    public function createWriter($uri) {
        return fopen($uri,'w');
    }

	public function run($args = null)
	{
        $file = parent::run($args);

        /** INIZIO INSERIMENTO PRODOTTI */
        $contoErrori = 0;
        $idCycle = $this->app->dbAdapter->query("SELECT concat_ws('-',m.productId,
                                                                    m.productVariantId,
                                                                    m.marketplaceId,
                                                                    m.marketplaceAccountId) as code
                                                FROM MarketplaceAccountHasProduct m, vProductSortingView p
                                                WHERE m.productId = p.product and 
                                                m.productVariantId = p.variant and 
                                                marketplaceId = ? and 
                                                marketplaceAccountId = ? and m.isDeleted = 0 GROUP BY m.productId,
                                                                                  m.productVariantId,
                                                                                  m.marketplaceId,
                                                                                  m.marketplaceAccountId",[$this->marketplaceAccount->marketplaceId,
            $this->marketplaceAccount->id])->fetchAll(\PDO::FETCH_COLUMN,0);
        $this->report('Run','Count: '.count($idCycle));
        $marketplaceAccountHasProductRepo = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct');
        foreach ($idCycle as $marketplaceAccountHasProductId) {
            try {
                set_time_limit(5);
                $marketplaceAccountHasProduct = $marketplaceAccountHasProductRepo->findOneByStringId($marketplaceAccountHasProductId);
                $row = $this->writeProductEntry(null,$marketplaceAccountHasProduct);
                fputcsv ($file, $row,",");
                if (is_null($marketplaceAccountHasProduct->insertionDate)) {
                    $marketplaceAccountHasProduct->insertionDate = $this->time();
                    $marketplaceAccountHasProduct->publishDate = $this->time();
                    $marketplaceAccountHasProduct->lastRevised = $this->time();
                }
                $marketplaceAccountHasProduct->isToWork = 0;
                $marketplaceAccountHasProduct->isRevised = 1;
                $marketplaceAccountHasProduct->hasError = 0;
                $marketplaceAccountHasProduct->errorRequest = null;
                $marketplaceAccountHasProduct->update();

                unset($marketplaceAccountHasProduct);
            } catch (\Throwable $e) {
                //die();
                $contoErrori++;
                $marketplaceAccountHasProduct->errorRequest = $e->getMessage() . "\n" . $e->getTraceAsString();
                $marketplaceAccountHasProduct->isRevised = 0;
                $marketplaceAccountHasProduct->hasError = 1;
                $marketplaceAccountHasProduct->update();
            }
        }
        $this->report('end','Finished, yee');
        fclose($file);
	}

    /**
     * @param CProduct $product
     * @param CMarketplaceAccountHasProduct $marketplaceAccountHasProduct
     * @return array
     */
	public function writeProductEntry(CProduct $product = null, CMarketplaceAccountHasProduct $marketplaceAccountHasProduct = null)
	{
        $row = [];
        $product = $marketplaceAccountHasProduct->product;

        $row['code'] = $product->printId();
        $row['name'] = $product->printCpf();
        $row['lang'] = $this->app->getLang()->getNation();
        $row['season'] = $product->productSeason->name;
        $row['brand'] = $product->productBrand->name;
        $row['category'] = $product->getLocalizedProductCategories(" - ","/");
        $row['title'] = $product->getName();
        $row['image1'] = $this->helper->image($product->getPhoto(1, 843), 'amazon');

        for ($i = 2; $i < 8; $i++) {
            $actual = $product->getPhoto($i, 843);
            if ($actual!= false && !empty($actual)) {
                $row['image'.$i] = $this->helper->image($actual,'amazon');
            } else {
                $row['image'.$i] = "";
            }
        }
        $row['link'] = $product->getProductUrl($this->app->baseUrl($this->lang),$marketplaceAccountHasProduct->marketplaceAccount->getCampaignCode());

        if (!is_null($product->productColorGroup)) {
            $row['color'] = $product->productColorGroup->productColorGroupTranslation->getFirst()->name;
        } else {
            $row['color'] = "";
        }

        $row['descritpion'] = $product->getDescription();

		$avai = 0;
		$sizes = [];
		$onSale = $product->isOnSale();
		foreach ($product->productPublicSku as $sku) {
			if ($sku->stockQty > 0) {
				$sizes[] = $sku->productSize->name;
				$avai++;
			}
		}

        $row['price'] = $product->getDisplayPrice();

        if($onSale){
            $row['salePrice'] = $product->getDisplaySalePrice();
        }

        $row['sizes'] = implode(',',$sizes);

		return $row;
	}
}