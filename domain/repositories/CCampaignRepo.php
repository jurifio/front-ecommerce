<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CCampaignVisit;

/**
 * Class CCampaignRepo
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
class CCampaignRepo extends ARepo
{
    private $campaingsCache = [];

    /**
     * cerca i dati della campagna all'interno dei parametri sporchi della visita
     * @param $rawData
     * @return \bamboo\core\db\pandaorm\entities\IEntity
     */
    public function readCampaignData($rawData)
    {
        $rawData = $rawData['utm_marketing_data'];
        if(is_array($rawData)) $rawData = array_pop($rawData);

        if (isset($this->campaingsCache[md5($rawData)])) {
            return $this->campaingsCache[md5($rawData)];
        }

        return $this->readCampaignCode($rawData);
    }

    /**
     * legge il codice della campagna cercando la campagna associata, in caso contrario crea una nuova campagna e la restituisce
     * @param $rawData
     * @return \bamboo\core\db\pandaorm\entities\AEntity|IEntity|null
     */
    public function readCampaignCode($rawData)
    {
        $marketplaceAccountId = null;
        if (is_null($rawData)) {
            $campaign = $this->findOneBy(["code" => '']);
            $this->campaingsCache[md5($rawData)] = $campaign;
            return $campaign;
        } else if (($campaign = $this->findOneBy(["code" => $rawData])) instanceof IEntity) {
            $this->campaingsCache[md5($rawData)] = $campaign;
            return $campaign;
        } else if (preg_match("#MarketplaceAccount([0-9]+-[0-9]+)#u", $rawData, $marketplaceAccountId)) {
            $marketplaceAccount = \Monkey::app()->repoFactory->create('MarketplaceAccount')->findOneByStringId($marketplaceAccountId[1]);
            $campaign = $this->findOneBy(["name" => $marketplaceAccount->name]);
            if (is_null($campaign)) {
                $campaign = $this->createCampaign($marketplaceAccount->name, $marketplaceAccount->getCampaignCode());
            }
            $this->campaingsCache[md5($rawData)] = $campaign;
            return $campaign;
        } else {
            $campaign = $this->createCampaign($rawData);
            $this->campaingsCache[md5($rawData)] = $campaign;
            return $campaign;
        }
    }

    /**
     * Crea una campagna con un nome ed un codice
     * @param $name
     * @return \bamboo\core\db\pandaorm\entities\AEntity
     */
    public function createCampaign($name, $code = null)
    {
        $campaign = $this->getEmptyEntity();
        $campaign->name = $name;
        $campaign->code = is_null($code) ? $name : $code;
        $campaign->id = $campaign->insert();
        return $this->findOne([$campaign->id]);
    }
}