<?php

namespace bamboo\ecommerce\offline;

use bamboo\core\jobs\ACronJob;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\repositories\CCampaignRepo;
use bamboo\utils\time\STimeToolbox;

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
class CRecreateCampaignOrderStatistics extends ACronJob
{
    use TMySQLTimestamp;

    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $args = json_decode($args,true);
        if(isset($args['views']) && $args['views']) {
            $this->report('run', 'running Campaing Views Statistics');
            $this->runCampaignViews();
        }
        if(isset($args['conversions']) && $args['conversions']) {
            $this->report('run', 'running Conversions Statistics');
            $this->runConversions();
        }
    }

    /**
     * @throws \Throwable
     */
    public function runCampaignViews()
    {
        $lastUpdate = $this->app->dbAdapter->query("SELECT ifnull(max(timestamp), 0) AS timestamp FROM CampaignVisit", [])->fetchAll(\PDO::FETCH_COLUMN, 0)[0];
        $this->report('run', 'Starting from: ' . $lastUpdate);

        $count = $this->app->dbAdapter->query("SELECT count(*)
                                                      FROM ActivityLog 
                                                      WHERE routeName IS NOT NULL AND routeName != 'Ajax Controller' AND hasCampaignData = 1 AND
                                                            creationDate > ?", [$lastUpdate])->fetchAll(\PDO::FETCH_COLUMN, 0)[0];

        $this->report('run', 'Raw data: ' . $count . ' rows');

        $pages = $this->app->dbAdapter->query("SELECT id
                                                      FROM ActivityLog 
                                                      WHERE routeName IS NOT NULL AND routeName != 'Ajax Controller' AND hasCampaignData = 1 AND
                                                            creationDate > ? ORDER BY creationDate ASC LIMIT 50000", [$lastUpdate])->fetchAll();

        /** @var CCampaignRepo $campaignRepo */
        $campaignRepo = \Monkey::app()->repoFactory->create('Campaign');
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

    /**
     * @throws \Throwable
     */
    public function runConversions()
    {
        $orders = \Monkey::app()->repoFactory->create('Order')->findBySql("
          SELECT id FROM 
              `Order` o LEFT JOIN CampaignVisitHasOrder cvho on o.id = cvho.orderId WHERE o.status LIKE 'ORD_%' and orderId is null ORDER BY id DESC limit 10", []);
        $this->report('runConversions', 'Orders to work: ' . count($orders), $orders);
        foreach ($orders as $order) {
            $this->report('runConversions', 'working Order: ' . $order->id);
            $sids = [];
            foreach ($order->cart->userSessionHasCart as $userSessionHasCart) {
                $sids[] = $userSessionHasCart->userSession->sid;
            }
            foreach ($order->user->userSession as $userSession) {
                $sids[] = $userSession->sid;
            }
            if (empty($sids)) {
                $this->report('runConversions', 'sids not found, running query');
                $sids = $this->app->dbAdapter->query("SELECT DISTINCT a1.sid FROM ActivityLog a1 WHERE a1.userId = ?", [$order->userId])->fetchAll(\PDO::FETCH_COLUMN, 0);
                if (empty($sids)) continue;
                else $sids = array_values($sids);
            }
            $this->report('runConversions', 'Sids found: ' . count($sids), $sids);
            $params = array_unique($sids);
            $sids = [];
            foreach ($params as $sid) $sids[] = '?';

            $c = new \DateTime($order->orderDate);
            $c->sub(new \DateInterval('P31D'));

            $sql = "SELECT DISTINCT id FROM ActivityLog WHERE hasCampaignData = 1 AND (sid IN ( ".implode(',', $sids).") OR userId = ?) and creationDate between ? and ? ORDER BY creationDate DESC";
            $params[] = $order->userId;
            $params[] = STimeToolbox::DbFormattedDateTime($c);
            $params[] = STimeToolbox::DbFormattedDateTime($order->orderDate);
            $this->report('Ricerca Dati Campagna',"q:" .$sql, array_values($params));
            $sources = \Monkey::app()->repoFactory->create('ActivityLog')->findBySql($sql, array_values($params));
            try {
                $this->report('runConversions', 'sources: ' . count($sources));
                \Monkey::app()->repoFactory->beginTransaction();
                foreach ($sources as $source) {
                    $campaign = \Monkey::app()->repoFactory->create('Campaign')->readCampaignData($source->vars);
                    $campaignVisit = \Monkey::app()->repoFactory->create('CampaignVisit')->findOneBy([
                        "campaignId" => $campaign->id,
                        "timestamp" => $source->creationDate
                    ]);

                    $campaignVisitHasOrder = \Monkey::app()->repoFactory->create('CampaignVisitHasOrder')->getEmptyEntity();
                    $campaignVisitHasOrder->campaignVisitId = $campaignVisit->id;
                    $campaignVisitHasOrder->campaignId = $campaign->id;
                    $campaignVisitHasOrder->orderId = $order->id;
                    $campaignVisitHasOrder->insert();
                }
                \Monkey::app()->repoFactory->commit();
            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                throw $e;
            }
        }
    }
}