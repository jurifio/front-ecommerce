<?php

namespace bamboo\events\listeners;

use bamboo\core\events\AEvent;
use bamboo\core\events\AEventListener;
use bamboo\core\events\CEventEmitted;
use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\COrder;
use bamboo\domain\repositories\CEmailRepo;
use bamboo\domain\repositories\CProductSkuRepo;
use bamboo\domain\repositories\CProductRepo;

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
           /* $typeMail=1;
            $shopId=44;
                if($typeMail==1){
                    $emailTemplateRepo=\Monkey::app()->repoFactory->create('EmailTemplate')->findOneBy(['oldTemplatephp'=>'newOrderClient','shopId'=>$shopId]);
                    $emailTemplateTranslationRepo=\Monkey::app()->repoFactory->create('EmailTemplateTranslation');
                    $langRepo=\Monkey::app()->repoFactory->create('Lang');
                    $templateEmailId=$emailTemplateRepo->id;
                    $template=$emailTemplateRepo->template;
                    $lang = isset($passedVars['lang']) ? $passedVars['lang'] : \Monkey::app()->getLang()->getLang();
                    $findLang=$langRepo->findOneBy(['lang'=>$lang]);
                    $langId=$findLang->id;
                    $translationTag=\Monkey::app()->repoFactory->create('EmailTemplateTag')->findBy(['templateId'=>13]);
                    $templateTranslation=$emailTemplateTranslationRepo->findOneBy(['templateEmailId'=>$templateEmailId,'langId'=>$langId]);

                    $arrayVar=[];
                    foreach($translationTag as $tag){
                        $arraVar[]=[$tag->tagName=>'<?php echo sprintf('.$tag->tagTemplate.')<;?>'];
                    }

                    $array=json_decode($templateTranslation->template,true);

                    foreach ($array as $key=>$value) {

                        if(strpos($template, $key) !== false) {
                            $newTemplate = str_replace('{'.$key.'}',$value,$template);
                            $template=$newTemplate;
                        }

                    }


                }else{

}

            /*$this->app->mailer->prepare('neworderclient','no-reply', $to,[],[],['order'=>$order,'orderId'=>$order->id]);
            $res = $this->app->mailer->send();*/

            /** @var CEmailRepo $emailRepo */
            $emailRepo = \Monkey::app()->repoFactory->create('Email');
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $to,[],[],['order'=>$order,'orderId'=>$order->id],'MailGun',null);
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $toIwes,[],[],['order'=>$order,'orderId'=>$order->id],'MailGun',null);
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $toIt,[],[],['order'=>$order,'orderId'=>$order->id],'MailGun',null);


        } catch (\Throwable $e) {
            $this->error('MailOrderClient',$e->getMessage(),$e);
        }
    }
}