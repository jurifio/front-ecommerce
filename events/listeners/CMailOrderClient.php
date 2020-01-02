<?php

namespace bamboo\events\listeners;

use bamboo\core\events\AEvent;
use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\COrder;
use bamboo\domain\repositories\CEmailRepo;

/**
 * Class COtherTest
 * @package bamboo\app\evtlisteners
 */
class CMailOrderClient extends AEventListener
{
    public function work($event)
    {
	    /** @var COrder $order */
	    try {
            $this->app = \Monkey::app();
            if(!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');
            $this->report('Sending Mail', "begin to send mail",$event);
            $order = \Monkey::app()->repoFactory->create('Order')->findOne([$event->getEventData('orderId')]);
		    $to = [$order->user->email];
		    $toIwes=['gianluca@iwes.it'];
		    $toIt=['it@iwes.it'];

		    /*$this->app->mailer->prepare('neworderclient','no-reply', $to,[],[],['order'=>$order,'orderId'=>$order->id]);
		    $res = $this->app->mailer->send();*/

		    /** @var CEmailRepo $emailRepo */
            $emailRepo = \Monkey::app()->repoFactory->create('Email');
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $to,[],[],['order'=>$order,'orderId'=>$order->id]);
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $toIwes,[],[],['order'=>$order,'orderId'=>$order->id]);
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $toIt,[],[],['order'=>$order,'orderId'=>$order->id]);

	    } catch (\Throwable $e) {
		    $this->error('MailOrderClient',$e->getMessage(),$e);
	    }
    }
}