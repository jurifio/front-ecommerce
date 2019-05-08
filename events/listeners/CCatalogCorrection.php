<?php

namespace bamboo\events\listeners;

use bamboo\core\events\AEvent;
use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\RedPandaException;

/**
 * Class COtherTest
 * @package bamboo\app\evtlisteners
 */
class CCatalogCorrection extends AEventListener
{
	/**
	 * @param AEvent $event
	 * @return bool
	 */
    public function work($event)
    {
	    try {
            $this->app = \Monkey::app();
            if(!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');
		    sleep(2);
	        $productIds = $event->getEventData('productIds');
	        $product = \Monkey::app()->repoFactory->create('Product')->findOne($productIds);
			$product->productStatusId = 9;
	        $product->update();

	        $this->app->cacheService->getCache('widgets')->flush();
	        return true;
        } catch (\Throwable $e) {
	        $this->error('Photo problem not Signaled', 'Product with problem', $e);
	        return false;
        }

    }
}