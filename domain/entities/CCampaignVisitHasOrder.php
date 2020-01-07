<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCampaign
 * @package bamboo\domain\entities
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
class CCampaignVisitHasOrder extends AEntity
{
    protected $entityTable = 'CampaignVisitHasOrder';
    protected $primaryKeys = ['campaignVisitId','campaignId','orderId','remoteShopId'];
}