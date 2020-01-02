<?php

namespace bamboo\events\listeners;

use bamboo\core\events\AEvent;
use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;
use bamboo\domain\repositories\CEmailRepo;

/**
 * Class CMailOrderBack
 * @package bamboo\app\events\listeners
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CMailOrderBack extends AEventListener
{
    public function work($event)
    {
        $this->app = \Monkey::app();
        if(!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');

        $order = \Monkey::app()->repoFactory->create('Order')->findOne([$event->getEventData('orderId')]);
        $to = $this->app->cfg()->fetch('miscellaneous','mailer')['clientRecipient']['orders'];
        $toIwes=['gianluca@iwes.it'];
        $toIt=['it@iwes.it'];

        /*$this->app->mailer->prepare('neworderback','no-reply', $to,[],[],['order'=>$order,'orderId'=>$event->getEventData('orderId')]);
        $res = $this->app->mailer->send();*/

        /** @var CEmailRepo $emailRepo */
        $emailRepo = \Monkey::app()->repoFactory->create('Email');
        $emailRepo->newPackagedTemplateMail('neworderback','no-reply@pickyshop.com', $to,[],[],['order'=>$order,'orderId'=>$event->getEventData('orderId')]);
        $emailRepo->newPackagedTemplateMail('neworderback','no-reply@pickyshop.com', $toIwes,[],[],['order'=>$order,'orderId'=>$event->getEventData('orderId')]);
        $emailRepo->newPackagedTemplateMail('neworderback','no-reply@pickyshop.com', $toIt,[],[],['order'=>$order,'orderId'=>$event->getEventData('orderId')]);
    }
}