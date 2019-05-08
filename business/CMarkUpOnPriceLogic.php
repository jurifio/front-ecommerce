<?php

namespace bamboo\ecommerce\business;
use bamboo\domain\entities\COrderLine;
use bamboo\core\application\AApplication;
use bamboo\core\db\pandaorm\repositories\CRepo;
use bamboo\core\ecommerce\IBillingLogic;

/**
 * Class CMarkUpOnPriceLogic
 * @package bamboo\app
 */
class CMarkUpOnPriceLogic implements IBillingLogic {


    protected $app;

    public function __construct(AApplication $app){
        $this->app = $app;
    }

    /**
     * @param COrderLine $orderLine
     * @return bool
     */
    public function calculateFriendReturn(COrderLine $orderLine)
    {
        return $this->friendPriceCalculation($orderLine->netPrice,$orderLine->shop->saleMultiplier);
    }

	/**
	 * @param $activePrice
	 * @param $multiplier
	 * @return mixed
	 */
    private function friendPriceCalculation($activePrice, $multiplier)
    {
        return $activePrice - ( $activePrice * ($multiplier / 100) );
    }

} 