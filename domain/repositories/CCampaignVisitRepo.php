<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CCampaignVisit;
use Monkey;

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
     * @param $typeVisit
     */
    public function setCampaignVisitCost($campaignVisitStringId,$typeVisit)
    {
        $campaignVisit = \Monkey::app()->repoFactory->create('CampaignVisit')->findOneByStringId($campaignVisitStringId);
        $campaignVisit->cost = $this->calculateCampaignVisitCost($campaignVisit, $typeVisit);
        $campaignVisit->costCustomer = $this->calculateCampaignVisitCostCustomer($campaignVisit,$typeVisit);
        $campaignVisit->update();
    }


    /**
     * Cerca il costo della visita,
     * se ha un prodotto-marketplace prende la fee,
     * altrimenti nel costo di default del marketplace o
     * nel costo di default della campagna,
     * altrimenti 0
     * @param CCampaignVisit $campaignVisit
     * @param $typeVisit
     * @return int
     */
    public function calculateCampaignVisitCost(CCampaignVisit $campaignVisit, $typeVisit)
    {

        if (is_null($campaignVisit->campaign->marketplaceAccount) || ($campaignVisit->product->isEmpty())) {
            if($typeVisit=='Desktop') {
                return $campaignVisit -> campaign -> defaultCpcF;
            }else{
                return $campaignVisit -> campaign -> defaultCpcFM;
            }
        } elseif ($campaignVisit->product->isEmpty() &&
            is_null($campaignVisit->campaign->defaultCpcF) &&
            isset($campaignVisit->marketplaceAccount->config['defaultCpcF']) &&
            is_numeric($campaignVisit->marketplaceAccount->config['defaultCpcF'])
        ) {
            if($typeVisit=='Desktop') {
                return $campaignVisit -> marketplaceAccount -> config['defaultCpcF'];
            }else{
                return $campaignVisit -> marketplaceAccount -> config['defaultCpcFM'];
            }
        } else {
            $ids = ['marketplaceId' => $campaignVisit->campaign->marketplaceId,
                'marketplaceAccountId' => $campaignVisit->campaign->marketplaceAccountId,
                'productId' => $campaignVisit->product->getFirst()->id,
                'productVariantId' => $campaignVisit->product->getFirst()->productVariantId];
            $marketplaceAccountHasProduct = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct')->findOneBy($ids);
            if (!is_null($marketplaceAccountHasProduct) && !is_null($marketplaceAccountHasProduct->fee) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcF'] ?? false))
                if($typeVisit=='Desktop') {
                    return $marketplaceAccountHasProduct -> fee;
                }else{
                    return $marketplaceAccountHasProduct -> feeMobile;
                }
            elseif ($campaignVisit->campaign->defaultCpcF && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcF']))
                if($typeVisit=='Desktop') {
                    return $campaignVisit -> campaign -> defaultCpcF;
                }else{
                    return $campaignVisit -> campaign -> defaultCpcFM;
                }
            elseif (isset($campaignVisit->campaign->marketplaceAccount->config['defaultCpcF']) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcF'] ?? false))
                if($typeVisit=='Desktop') {
                    return $campaignVisit -> campaign -> marketplaceAccount -> config['defaultCpcF'];
                }else{
                    return $campaignVisit -> campaign -> marketplaceAccount -> config['defaultCpcFM'];
                }
        }
        return 0;
    }

    /**
     * Cerca il costo della visita,
     * se ha un prodotto-marketplace prende la fee,
     * altrimenti nel costo di default del marketplace o
     * nel costo di default della campagna,
     * altrimenti 0
     * @param CCampaignVisit $campaignVisit
     * @param $typeVisit
     * @return int
     */

    public function calculateCampaignVisitCostCustomer(CCampaignVisit $campaignVisit,$typeVisit)
    {
        if (is_null($campaignVisit->campaign->marketplaceAccount) || ($campaignVisit->product->isEmpty())) {
            if($typeVisit=='Desktop') {
                return $campaignVisit -> campaign -> defaultCpc;
            }else{
                return $campaignVisit -> campaign -> defaultCpcM;
            }
        } elseif ($campaignVisit->product->isEmpty() &&
            is_null($campaignVisit->campaign->defaultCpc) &&
            isset($campaignVisit->marketplaceAccount->config['defaultCpc']) &&
            is_numeric($campaignVisit->marketplaceAccount->config['defaultCpc'])
        ) {
            if($typeVisit=='Desktop') {
                return $campaignVisit -> marketplaceAccount -> config['defaultCpc'];
            }else{
                return $campaignVisit -> marketplaceAccount -> config['defaultCpcM'];
            }
        } else {
            $ids = ['marketplaceId' => $campaignVisit->campaign->marketplaceId,
                'marketplaceAccountId' => $campaignVisit->campaign->marketplaceAccountId,
                'productId' => $campaignVisit->product->getFirst()->id,
                'productVariantId' => $campaignVisit->product->getFirst()->productVariantId];
            $marketplaceAccountHasProduct = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct')->findOneBy($ids);
            if (!is_null($marketplaceAccountHasProduct) && !is_null($marketplaceAccountHasProduct->feeCustomer) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc'] ?? false))
                if($typeVisit=='Desktop') {
                    return $marketplaceAccountHasProduct -> feeCustomer;
                }else{
                    return $marketplaceAccountHasProduct -> feeCustomerMobile;
                }
            elseif ($campaignVisit->campaign->defaultCpc && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc']))
                if($typeVisit=='Desktop') {
                    return $campaignVisit -> campaign -> defaultCpc;
                }else{
                    return $campaignVisit -> campaign -> defaultCpcM;
                }
            elseif (isset($campaignVisit->campaign->marketplaceAccount->config['defaultCpc']) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc'] ?? false))
                if($typeVisit=='Desktop') {
                    return $campaignVisit -> campaign -> marketplaceAccount -> config['defaultCpc'];
                }else{
                    return $campaignVisit -> campaign -> marketplaceAccount -> config['defaultCpcM'];
                }
        }
        return 0;

    }


}