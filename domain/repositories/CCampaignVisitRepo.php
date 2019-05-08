<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CCampaignVisit;

/**
 * Class CCampaignVisitRepo
 * @package bamboo\domain\repositories
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
class CCampaignVisitRepo extends ARepo
{
    /**
     * Set cost in campaign Visit
     * @param $campaignVisitStringId
     */
    public function setCampaignVisitCost($campaignVisitStringId)
    {
        $campaignVisit = \Monkey::app()->repoFactory->create('CampaignVisit')->findOneByStringId($campaignVisitStringId);
        $campaignVisit->cost = $this->calculateCampaignVisitCost($campaignVisit);
        $campaignVisit->update();
    }

    /**
     * Cerca il costo della visita,
     * se ha un prodotto-marketplace prende la fee,
     * altrimenti nel costo di default del marketplace o
     * nel costo di default della campagna,
     * altrimenti 0
     * @param CCampaignVisit $campaignVisit
     * @return int
     */
    public function calculateCampaignVisitCost(CCampaignVisit $campaignVisit)
    {
        if (is_null($campaignVisit->campaign->marketplaceAccount) || ($campaignVisit->product->isEmpty())) {
            return $campaignVisit->campaign->defaultCpc;
        } elseif ($campaignVisit->product->isEmpty() &&
            is_null($campaignVisit->campaign->defaultCpc) &&
            isset($campaignVisit->marketplaceAccount->config['defaultCpc']) &&
            is_numeric($campaignVisit->marketplaceAccount->config['defaultCpc'])
        ) {
            return $campaignVisit->marketplaceAccount->config['defaultCpc'];
        } else {
            $ids = ['marketplaceId' => $campaignVisit->campaign->marketplaceId,
                'marketplaceAccountId' => $campaignVisit->campaign->marketplaceAccountId,
                'productId' => $campaignVisit->product->getFirst()->id,
                'productVariantId' => $campaignVisit->product->getFirst()->productVariantId];
            $marketplaceAccountHasProduct = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct')->findOneBy($ids);
            if (!is_null($marketplaceAccountHasProduct) && !is_null($marketplaceAccountHasProduct->fee) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc'] ?? false))
                return $marketplaceAccountHasProduct->fee;
            elseif ($campaignVisit->campaign->defaultCpc && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc']))
                return $campaignVisit->campaign->defaultCpc;
            elseif (isset($campaignVisit->campaign->marketplaceAccount->config['defaultCpc']) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc'] ?? false))
                return $campaignVisit->campaign->marketplaceAccount->config['defaultCpc'];
        }
        return 0;
    }
}