<?php

namespace bamboo\ecommerce\offline;

use bamboo\core\jobs\ACronJob;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\repositories\CCampaignRepo;
use bamboo\domain\repositories\CCampaignVisitRepo;

/**
 * Class CUpdateProductStatistics
 * @package bamboo\ecommerce\offline
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
class CUpdateCampaignStatistics extends ACronJob
{
    use TMySQLTimestamp;

    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $this->runCampaignViews();
    }

    /**
     * @throws \Throwable
     */
    public function runCampaignViews()
    {
        $lastUpdate = $this->app->dbAdapter->query("SELECT ifnull(max(timestamp), 0) AS timestamp FROM CampaignVisit", [])->fetchAll(\PDO::FETCH_COLUMN, 0)[0];
        $this->report('run', 'Starting from: ' . $lastUpdate);

        $count = $this->app->dbAdapter->query("select count(*) from (SELECT distinct id
                                                      FROM ActivityLog 
                                                      WHERE routeName IS NOT NULL 
                                                        AND routeName not in (
                                                          'Ajax Controller'
                                                          'Async Widget Controller'
                                                        )  
                                                        AND hasCampaignData = 1 
                                                        AND creationDate > ? 
                                                      GROUP BY sid, creationDate, requestedResource, actionArgs) t1", [$lastUpdate])->fetchAll(\PDO::FETCH_COLUMN, 0)[0];

        $this->report('run', 'Raw data: ' . $count . ' rows');

        $pages = $this->app->dbAdapter->query("SELECT distinct id
                                                      FROM ActivityLog 
                                                      WHERE routeName IS NOT NULL AND routeName != 'Ajax Controller' AND hasCampaignData = 1 AND
                                                            creationDate > ? GROUP BY sid, creationDate, requestedResource, actionArgs ORDER BY creationDate ASC LIMIT 50000", [$lastUpdate])->fetchAll();

        /** @var CCampaignRepo $campaignRepo */
        $campaignRepo = \Monkey::app()->repoFactory->create('Campaign');
        /** @var CCampaignVisitRepo $campaignVisitRepo */
        $campaignVisitRepo = \Monkey::app()->repoFactory->create('CampaignVisit');
        $campaignVisitHasProductRepo = \Monkey::app()->repoFactory->create('CampaignVisitHasProduct');

        $repA = \Monkey::app()->repoFactory->create('ActivityLog');
        $i = 0;
        $k = 0;
        \Monkey::app()->repoFactory->beginTransaction();
        try {
            foreach ($pages as $rawRow) {
                $row = $repA->findOne($rawRow);
                $campaign = $campaignRepo->readCampaignData($row->vars);
                $campaignVisit = $campaignVisitRepo->getEmptyEntity();

                $campaignVisit->campaignId = $campaign->id;
                $campaignVisit->timestamp = $row->creationDate;
                $campaignVisit->id = $campaignVisit->insert();

                if ($row->routeName == 'Pagina Prodotto') {
                    $campaignVisitHasProduct = $campaignVisitHasProductRepo->getEmptyEntity();
                    $campaignVisitHasProduct->campaignId = $campaign->id;
                    $campaignVisitHasProduct->campaignVisitId = $campaignVisit->id;
                    $campaignVisitHasProduct->productId = $row->actionArgs['item'];
                    $campaignVisitHasProduct->productVariantId = $row->actionArgs['variant'];
                    $campaignVisitHasProduct->insert();
                    $k++;
                }

                $campaignVisitRepo->setCampaignVisitCost($campaignVisit->printId());

                $i++;
                if ($i % 100 == 0) {
                    $this->report("Run Cycle", "CampaignVisits written: " . $i . " new Product Page: " . $k);
                    \Monkey::app()->repoFactory->commit();
                    \Monkey::app()->repoFactory->beginTransaction();
                }
            }
            $this->report("Run End", "CampaignVisits written: " . $i . " new Product Page: " . $k);
            \Monkey::app()->repoFactory->commit();
        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            $this->error('Run', "Error while analizing activityLog.", $e);
            throw $e;
        }
    }
}