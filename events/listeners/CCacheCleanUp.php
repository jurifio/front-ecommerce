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
class CCacheCleanUp extends AEventListener
{
    public function work($event)
    {
        $this->app = \Monkey::app();
        if(!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');
        try {
	        $table = $event->getEventData('table');
	        $parameters = $event->getEventData('parameters');
	        if(empty($table)) throw new RedPandaException('Empty Table');
	        try{
		        $repo = \Monkey::app()->repoFactory->create($table);
		        $entity = $repo->getEmptyEntity();
	        } catch(\Throwable $e){
		        return;
	        }

	        if($entity->isCacheable() == false) return;
	        $go = false;
	        $validID = [];
	        foreach ($entity->getPrimaryKeys() as $key) {
		        if (isset($parameters[$key])) {
			        $go = true;
			        $validID[$key] = $parameters[$key];
		        }
		        elseif (isset($parameters[(':'.$key)])) {
			        $go = true;
			        $validID[$key] = $parameters[(':'.$key)];
		        }
		        else {
			        $go = false;
			        break;
		        }
	        }
	        if($go){
		        $res = $this->app->cacheService->getCache('entities')->delete($entity->getClassName().'-'.serialize($validID));
		        return;
	        }

        } catch (\Throwable $e) {}
	        $this->app->cacheService->getCache('entities')->flush();
	    //$this->app->dbAdapter->insert('ApplicationLog',['source'=>'CCacheCleanUp','severity'=>'REPORT','title'=>'flushed','message'=>($table.'-'.serialize($parameters)),'context'=>serialize($event)]);
	    return;
    }
}