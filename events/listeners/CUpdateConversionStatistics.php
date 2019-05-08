<?php

namespace bamboo\events\listeners;

use bamboo\core\events\AEvent;
use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\utils\time\STimeToolbox;

/**
 * Class COtherTest
 * @package bamboo\app\evtlisteners
 */
class CUpdateConversionStatistics extends AEventListener
{
    use TMySQLTimestamp;

    public function work($event)
    {
        try {
            $this->report('runConversions', 'Looking for Conversions Statistics');
            $this->app = \Monkey::app();
            if (!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');

            $order = \Monkey::app()->repoFactory->create('Order')->findOne([$event->getEventData('orderId')]);
            $sids = [];
            foreach ($order->cart->userSessionHasCart as $userSessionHasCart) {
                $sids[] = $userSessionHasCart->userSession->sid;
            }
            foreach ($order->user->userSession as $userSession) {
                $sids[] = $userSession->sid;
            }
            if (empty($sids)) {
                $this->report('Ricerca Sid', 'Nessun Sid associato a questo ordine', $order);
            }
            $params = array_unique($sids);
            $sids = [];
            foreach ($params as $sid) $sids[] = '?';

            $c = new \DateTime($order->orderDate);
            $c->sub(new \DateInterval('P31D'));

            $sql = "SELECT DISTINCT id 
                    FROM ActivityLog 
                    WHERE hasCampaignData = 1 
                      AND routeName not in ('Async Widget Controller', 'Ajax Controller') 
                      AND (sid IN ( " . implode(',', $sids) . ") OR userId = ?) 
                      AND creationDate BETWEEN ? AND ? ORDER BY creationDate DESC LIMIT 15";
            $params[] = $order->userId;
            $params[] = STimeToolbox::DbFormattedDateTime($c);
            $params[] = STimeToolbox::DbFormattedDateTime($order->orderDate);
            $this->report('Ricerca Dati Campagna', "q:" . $sql, $params);
            $sources = \Monkey::app()->repoFactory->create('ActivityLog')->findBySql($sql, array_values($params));
            try {
                $this->report('runConversions', 'sources: ' . count($sources));
                \Monkey::app()->repoFactory->beginTransaction();
                $seenSources = [];
                foreach ($sources as $source) {
                    $campaign = \Monkey::app()->repoFactory->create('Campaign')->readCampaignData($source->vars);
                    if (array_search($campaign->id, $seenSources) === false) {
                        $seenSources[] = $campaign->id;
                    } else continue;
                    $campaignVisit = \Monkey::app()->repoFactory->create('CampaignVisit')->findOneBy([
                        "campaignId" => $campaign->id,
                        "timestamp" => $source->creationDate
                    ]);
                    if (is_null($campaignVisit)) {
                        $campaignVisit = \Monkey::app()->repoFactory->create('CampaignVisit')->getEmptyEntity();
                        $campaignVisit->campaignId = $campaign->id;
                        $campaignVisit->timestamp = $source->creationDate;
                        $campaignVisit->id = $campaignVisit->insert();
                    }

                    $campaignVisitHasOrder = \Monkey::app()->repoFactory->create('CampaignVisitHasOrder')->getEmptyEntity();
                    $campaignVisitHasOrder->campaignVisitId = $campaignVisit->id;
                    $campaignVisitHasOrder->campaignId = $campaign->id;

                    $campaignVisitHasOrder->orderId = $order->id;
                    $campaignVisitHasOrder->insert();
                }
                $this->report('runConversions', 'conversion registered');
                \Monkey::app()->repoFactory->commit();
            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                throw $e;
            }
        } catch (\Throwable $e) {
            $this->error('Conversion Statistics', 'Unknowe Error', $e);
        }
    }
}