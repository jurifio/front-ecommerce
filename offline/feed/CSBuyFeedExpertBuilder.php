<?php
namespace bamboo\ecommerce\offline\feed;

/**
 * Class CSBuyFeedExpertBuilder
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
class CSBuyFeedExpertBuilder extends ABluesealXmlExpertBuilder
{
    /**
     * @param $marketplaceAccount
     * @return bool
     */
    public function checkRightMarketplace($marketplaceAccount)
    {
        return $marketplaceAccount->marketplace->name == 'SBuy';
    }
}