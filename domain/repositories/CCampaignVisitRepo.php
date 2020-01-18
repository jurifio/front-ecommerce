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
     * @param $typeVisit
     */
    public function setCampaignVisitCost($campaignVisitStringId,$typeVisit)
    {
        $campaignVisit = \Monkey::app()->repoFactory->create('CampaignVisit')->findOneByStringId($campaignVisitStringId);
        $costVisit=$this->calculateCampaignVisitCost($campaignVisit,$typeVisit);
        $costArray=explode('-',$costVisit);
        $cost=$costArray[0];
        $costCustomer=$costArray[1];
        $campaignVisit->cost = $cost;
        $campaignVisit->costCustomer=$costCustomer;
        $campaignVisit->update();
    }

    /**
     * Cerca il costo della visita,
     * se ha un prodotto-marketplace prende la fee,
     * altrimenti nel costo di default del marketplace o
     * nel costo di default della campagna,
     * altrimenti 0
     * @param CCampaignVisit $campaignVisit
     * @return string
     */
    public function calculateCampaignVisitCost(CCampaignVisit $campaignVisit,$typeVisit): string
    {

        //se marketplaceAccount è null e prodotto nella campagna è vuoto inserisco il defaultCpc  della campagna
        if (is_null($campaignVisit->campaign->marketplaceAccount) || ($campaignVisit->product->isEmpty())) {
            if($typeVisit=='Desktop') {
                $cost = $campaignVisit->campaign->defaultCpcF . '-' . $campaignVisit->campaign->defaultCpc;
            }else{
                $cost = $campaignVisit->campaign->defaultCpcFM . '-' . $campaignVisit->campaign->defaultCpcM;
            }
            return  $cost;
            // se non esiste prodotto nella campagna e è nullo il il default cpc della campagna ed è settato il defaultcpc del marketplace account inserisco il defaultCpc del marketplace
        } elseif ($campaignVisit->product->isEmpty() && is_null($campaignVisit->campaign->defaultCpc)
            &&  isset($campaignVisit->marketplaceAccount->config['defaultCpcF']) && is_numeric($campaignVisit->marketplaceAccount->config['defaultCpcF'])
            &&  isset($campaignVisit->marketplaceAccount->config['defaultCpc']) && is_numeric($campaignVisit->marketplaceAccount->config['defaultCpc'])) {
            if($typeVisit=='Desktop') {
                $cost = $campaignVisit->marketplaceAccount->config['defaultCpcF'] . '-' .$campaignVisit->marketplaceAccount->config['defaultCpc'];
            }else{
                $cost= $campaignVisit->marketplaceAccount->config['defaultCpcFM'] . '-' .$campaignVisit->marketplaceAccount->config['defaultCpcM'];
            }
            return $cost;
        } else {
            // cerco nella tabella marketplaceId il relativo costo per la fee
            $ids = ['marketplaceId' => $campaignVisit->campaign->marketplaceId,
                'marketplaceAccountId' => $campaignVisit->campaign->marketplaceAccountId,
                'productId' => $campaignVisit->product->getFirst()->id,
                'productVariantId' => $campaignVisit->product->getFirst()->productVariantId];
            $marketplaceAccountHasProduct = \Monkey::app()->repoFactory->create('MarketplaceAccountHasProduct')->findOneBy($ids);
            if($typeVisit=='Desktop') {
                if (!is_null($marketplaceAccountHasProduct) && !is_null($marketplaceAccountHasProduct->fee) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc']  ?? false) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcF']  ?? false))

                    return $marketplaceAccountHasProduct->fee.'-'.$marketplaceAccountHasProduct->feeCustomer;
                elseif ($campaignVisit->campaign->defaultCpc && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc'])&& $campaignVisit->campaign->defaultCpcF && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcF']))
                    return $campaignVisit->campaign->defaultCpcF.'-'.$campaignVisit->campaign->defaultCpc;
                elseif (isset($campaignVisit->campaign->marketplaceAccount->config['defaultCpc']) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpc'] ?? false) && isset($campaignVisit->campaign->marketplaceAccount->config['defaultCpcF']) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcF'] ?? false) )
                    return $campaignVisit->campaign->marketplaceAccount->config['defaultCpcF'].'-'.$campaignVisit->campaign->marketplaceAccount->config['defaultCpc'];
            }else{
                if (!is_null($marketplaceAccountHasProduct) && !is_null($marketplaceAccountHasProduct->feeMobile) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcM']  ?? false) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcFM']  ?? false))

                    return $marketplaceAccountHasProduct->feeMobile.'-'.$marketplaceAccountHasProduct->feeCustomerMobile;
                elseif ($campaignVisit->campaign->defaultCpcM && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcM'])&& $campaignVisit->campaign->defaultCpcFM && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcFM']))
                    return $campaignVisit->campaign->defaultCpcFM.'-'.$campaignVisit->campaign->defaultCpcM;
                elseif (isset($campaignVisit->campaign->marketplaceAccount->config['defaultCpcM']) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcM'] ?? false) && isset($campaignVisit->campaign->marketplaceAccount->config['defaultCpcFM']) && is_numeric($campaignVisit->campaign->marketplaceAccount->config['defaultCpcFM'] ?? false) )
                    return $campaignVisit->campaign->marketplaceAccount->config['defaultCpcFM'].'-'.$campaignVisit->campaign->marketplaceAccount->config['defaultCpcM'];

            }

        }
        return "";
    }
}