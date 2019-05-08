<?php


namespace bamboo\events\listeners;

use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\COrderLineStatus;
use bamboo\domain\entities\COrderLine;
use bamboo\core\ecommerce\IBillingLogic;
use bamboo\core\exceptions\RedPandaException;
use bamboo\domain\repositories\COrderLineRepo;

/**
 * Class COrderRowChange
 * @package app\events\listeners
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class COrderLineStatusHandler extends AEventListener
{
    public function work($event)
    {
        $this->app = \Monkey::app();
        if(!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');
        /** @var COrderLineStatus $newStatus */
        $newStatus = $event->getEventData('newStatus');
        /** @var COrderLine $newStatus */
        $orderLine = $event->getEventData('orderLine');
        if(!$newStatus instanceof COrderLineStatus || !$orderLine instanceof COrderLine){
            throw new RedPandaException('Wrong Event Setup');
        }

        /** @var COrderLineRepo $orderLineRepo */
        $orderLineRepo = \Monkey::app()->repoFactory->create('OrderLine');

        if(!isset($orderLine->friendRevenue) || empty($orderLine->friendRevenue)) {
            $name = $orderLine->shop->billingLogic;
            /** @var IBillingLogic $billingLogic */
            $billingLogic = new $name($this->app);
            $orderLineRepo->changeFriendRevenue($orderLine,$billingLogic->calculateFriendReturn($orderLine));
        }
    }
}