<?php

namespace bamboo\events\listeners;

use bamboo\core\events\AEvent;
use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;

/**
 * Class COtherTest
 * @package bamboo\app\evtlisteners
 */
class CTestAsync extends AEventListener
{
    public function work($event)
    {
        $this->app = \Monkey::app();
        if(!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');
		$this->app->dbAdapter->insert('ApplicationLog',["source"=>'TestAsync',"severity"=>'report',"title"=>'testAsync',"message"=>'testAsync',"context"=>serialize($event)]);
    }
}