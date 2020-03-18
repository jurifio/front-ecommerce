<?php

namespace bamboo\events\listeners;

use bamboo\core\events\AEvent;
use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\CUser;
use bamboo\domain\repositories\CEmailRepo;

/**
 * Class COtherTest
 * @package bamboo\app\evtlisteners
 */
class CMailCoupon extends AEventListener
{
    public function work($event)
    {
        $this->app = \Monkey::app();
        if(!$event instanceof CEventEmitted) throw new BambooException('Event is not an event');
        /** @var CUser $user */
        $user = \Monkey::app()->repoFactory->create('User')->findOneBy(['id'=>$event->getUserId()]);
        $to[] = $user->getEmail();

        /*if ($user->isEmailChanged()) {
            $this->app->mailer->prepare('onActivate','no-reply', $to);
        } else {
            $this->app->mailer->prepare('onActivateCoupon','no-reply', $to);
        }*/

        /** @var CEmailRepo $emailRepo */
        $emailRepo = \Monkey::app()->repoFactory->create('Email');
        if ($user->isEmailChanged()) {
            $emailRepo->newPackagedTemplateMail('onActivate','no-reply@pickyshop.com', $to,[],[],[],,'MailGun',null);
        } else {
            $emailRepo->newPackagedTemplateMail('onActivateCoupon','no-reply@pickyshop.com', $to,[],[],[],'MailGun',null);
        }
    }
}