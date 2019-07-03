<?php
namespace bamboo\ecommerce\offline\feed;

use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\intl\CLang;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\core\jobs\ACronJob;
use bamboo\core\theming\CWidgetHelper;
use bamboo\domain\entities\CProduct;

/**
 * Class AExpertFeedBuilder
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
abstract class AExpertFeedBuilder extends ACronJob
{
    use TMySQLTimestamp;
    /**
     * @var CLang
     */
    protected $lang;
    /**
     * @var CWidgetHelper
     */
    protected $helper;

    /**
     * @var CMarketplaceAccount
     */
    protected $marketplaceAccount;

    /**
     * @var bool
     */
    protected $minized;
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public abstract function checkRightMarketplace($marketplaceAccount);

    public function run($args = null)
    {
        $args = json_decode($args);
        $this->report('Run', 'Starting To build');

        $this->minized = $args->minified;

        /** @var CMarketplaceAccount $marketplaceAccount */
        $marketplaceAccount = \Monkey::app()->repoFactory->create('MarketplaceAccount')->findOneByStringId($args->marketplaceAccountId);
        if(!$this->checkRightMarketplace($marketplaceAccount)) throw new BambooOutOfBoundException('Wrong marketplace in configuration: '.$marketplaceAccount->marketplace->name);
        $langId = $marketplaceAccount->config['lang'];
        $lang = \Monkey::app()->repoFactory->create('Lang')->findOneBy(["lang" => $langId]);
        $this->lang = new CLang($lang->id, $lang->lang);
        unset($lang);
        $this->app->setLang($this->lang);

        $this->marketplaceAccount = \Monkey::app()->repoFactory->create('MarketplaceAccount', $this->lang)->findOne($marketplaceAccount->getIds());
        $this->helper = new CWidgetHelper($this->app);
        $uri = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . $marketplaceAccount->config['filePath'];

        return $this->createWriter($uri);
    }

    /**
     * @param $uri
     * @return \XMLWriter
     */
    public function createWriter($uri) {
        /** INIZIO INTESTAZIONE XML */
        $writer = new \XMLWriter();
        $writer->openUri($uri);
        $writer->startDocument('1.0');
        $writer->setIndent(!$this->minized);

        return $writer;
    }

    /**
     * @param CProduct|null $product
     * @param CMarketplaceAccountHasProduct|null $marketplaceAccountHasProduct
     * @return string
     */
    public abstract function writeProductEntry(CProduct $product = null, CMarketplaceAccountHasProduct $marketplaceAccountHasProduct = null);

    /**
     * Returns array of "codes" for products
     */
    protected function fetchProductsCodeMinusDeleted() {
        $idCycle = $this->app->dbAdapter->query("SELECT concat_ws('-',product,variant) AS code
                                                  FROM Product v LEFT JOIN 
                                                        MarketplaceAccountHasProduct p ON v.id = p.productId AND 
                                                                                          v.productVariantId = p.productVariantId AND 
                                                                                          p.marketplaceId = ? AND 
                                                                                          p.marketplaceAccountId = ?
                                                  WHERE ifnull(p.isDeleted, 0) = 0
                                                  GROUP BY product,variant", [
            $this->marketplaceAccount->marketplaceId,
            $this->marketplaceAccount->id])->fetchAll(\PDO::FETCH_COLUMN,0);
        return $idCycle;
    }

    /**
     * returns array of "codes" for MarketplaceAccountHasProduct
     */
    protected function fetchMarketplaceProduct() {
        $idCycle = $this->app->dbAdapter->query("SELECT concat_ws('-',m.productId,
                                                                    m.productVariantId,
                                                                    m.marketplaceId,
                                                                    m.marketplaceAccountId) as code
                                                FROM MarketplaceAccountHasProduct m, Product p
                                                WHERE   m.productId = p.id and 
                                                        m.productVariantId = p.productVariantId and 
                                                        marketplaceId = ? and 
                                                        marketplaceAccountId = ? and 
                                                        m.isDeleted = 0 
                                                GROUP BY m.productId,m.productVariantId,m.marketplaceId,m.marketplaceAccountId",
            [$this->marketplaceAccount->marketplaceId,$this->marketplaceAccount->id])->fetchAll(\PDO::FETCH_COLUMN,0);
        return $idCycle;
    }

    /**
     * @param \XMLWriter $writer
     * @return int
     */
    protected function writeProductsMinusDeleted(\XMLWriter $writer) {
        $idCycle = $this->fetchProductsCodeMinusDeleted();
        $productRepo = \Monkey::app()->repoFactory->create('Product');
        $contoErrori = 0;
        foreach ($idCycle as $productId) {
            $marketplaceAccountHasProduct = null;
            $product = $productRepo->findOneByStringId($productId);
            try {
                set_time_limit(10);
                /** @var CMarketplaceAccountHasProduct $marketplaceAccountHasProduct */
                $marketplaceAccountHasProduct = $product->marketplaceAccountHasProduct->findOneByKeys($this->marketplaceAccount->getIds());
                $writer->writeRaw($this->writeProductEntry($product,$marketplaceAccountHasProduct ? $marketplaceAccountHasProduct : null));

                if ($marketplaceAccountHasProduct) {
                    if(is_null($marketplaceAccountHasProduct->insertionDate)) {
                        $marketplaceAccountHasProduct->insertionDate = $this->time();
                        $marketplaceAccountHasProduct->publishDate = $this->time();
                        $marketplaceAccountHasProduct->lastRevised = $this->time();
                    }
                    $marketplaceAccountHasProduct->errorRequest = null;
                    $marketplaceAccountHasProduct->isToWork = 0;
                    $marketplaceAccountHasProduct->isRevised = 1;
                    $marketplaceAccountHasProduct->hasError = 0;
                    $marketplaceAccountHasProduct->update();
                }
                unset($marketplaceAccountHasProduct);

            } catch (\Throwable $e) {
                $contoErrori++;
                if($marketplaceAccountHasProduct && !is_null($marketplaceAccountHasProduct)){
                    $marketplaceAccountHasProduct->errorRequest = $e->getMessage() . "\n" . $e->getTraceAsString();
                    $marketplaceAccountHasProduct->isRevised = 0;
                    $marketplaceAccountHasProduct->hasError = 1;
                    $marketplaceAccountHasProduct->update();
                    unset($marketplaceAccountHasProduct);
                } else {
                    $this->warning('Feed Product '.$this->marketplaceAccount->printId(),'Error exporting Product: '.$product->printId(),$e);
                }
            }
        }
        return $contoErrori;
    }

    protected function writeMarketplaceProducts(\XMLWriter $writer) {
        $contoErrori = 0;
        $idCycle = $this->fetchMarketplaceProduct();
        $marketplaceAccountHasProductRepo = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct');
        foreach ($idCycle as $marketplaceAccountHasProductId) {
            set_time_limit(5);
            $marketplaceAccountHasProduct = $marketplaceAccountHasProductRepo->findOneByStringId($marketplaceAccountHasProductId);
            if(is_null($marketplaceAccountHasProduct)) {
                $this->error('writeMarketplaceProducts','marketplaceAccountHasProduct not found while it should be there',$marketplaceAccountHasProductId);
                continue;
            }
            try {
                $writer->writeRaw($this->writeProductEntry($marketplaceAccountHasProduct->product,$marketplaceAccountHasProduct));
                if (is_null($marketplaceAccountHasProduct->insertionDate)) {
                    $marketplaceAccountHasProduct->insertionDate = $this->time();
                    $marketplaceAccountHasProduct->publishDate = $this->time();
                    $marketplaceAccountHasProduct->lastRevised = $this->time();
                }
                $marketplaceAccountHasProduct->isToWork = 0;
                $marketplaceAccountHasProduct->isRevised = 1;
                $marketplaceAccountHasProduct->hasError = 0;
                $marketplaceAccountHasProduct->update();
                unset($marketplaceAccountHasProduct);
            } catch (\Throwable $e) {
                $contoErrori++;
                $marketplaceAccountHasProduct->errorRequest = $e->getMessage() . "\n" . $e->getTraceAsString();
                $marketplaceAccountHasProduct->isRevised = 0;
                $marketplaceAccountHasProduct->hasError = 1;
                $marketplaceAccountHasProduct->update();
            }
        }
        return $contoErrori;
    }
}