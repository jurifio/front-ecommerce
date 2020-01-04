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

            /** @var CProductSkuRepo $skuRepo */
            $skuRepo = \Monkey::app()->repoFactory->create('ProductSku');
            /** @var CProductRepo $productRepo */
            /** @var \bamboo\domain\entities\COrder $order */
            $productRepo = \Monkey::app()->repoFactory->create('Product');
            $bodyOrder='';
            foreach ($order->orderLine as $orderLine){
                /** @var \bamboo\domain\entities\COrderLine $orderLine */

                $bodyOrder.= '<tr>
                                                <td align="left" valign="top" width="11%"
                                                    style="margin: 0px; padding:10px 0;"
                                                    class="">
                                                    <table border="0" cellpadding="0" cellspacing="0" align="center"
                                                           width="100%">
                                                        <tbody>
                                                        <tr>
                                                            <td valign="top" align="center" style="padding:0;margin:0;">
                                                                <table border="0" cellpadding="0" cellspacing="0"
                                                                       align="left" data-editable="image"
                                                                       data-mobile-width="0" width="64">
                                                                    <tbody>
                                                                    <tr>
                                                                        <td valign="top" align="left"
                                                                            style="display: inline-block; padding: 0px 0px 0px 40px; margin: 0px;"
                                                                            class="tdBlock"><img
                                                                                    src="https://cdn.iwes.it/'.$orderLine->productSku->product->getPhoto(1,\bamboo\domain\entities\CProductPhoto::SIZE_THUMB).'"
                                                                                    height="100" border="0"
                                                                                    style="border-width: 0px; border-style: none; border-color: transparent; font-size: 12px; display: block;">
                                                                        </td>
                                                                    </tr>
                                                                    </tbody>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                </td>

                                                <td valign="top" align="left" width="50%"
                                                    style="padding: 0px; margin: 0px;"
                                                    class="">
                                                    <table width="100%" border="0" cellpadding="0" cellspacing="0"
                                                           align="center" data-editable="text">
                                                        <tbody>
                                                        <tr>
                                                            <td valign="top" align="center" class="lh-1"
                                                                style="padding: 50px 0px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:300;color:#000000; line-height:0.5;">
                                                                '.$orderLine->productSku->product->getName().'
                                                            </span>
                                                            </td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                                <td valign="top" align="left" width="30%"
                                                    style="padding: 0px; margin: 0px;"
                                                    class="">
                                                    <table align="center" width="100%" border="0" cellpadding="0"
                                                           cellspacing="0" data-editable="text">
                                                        <tbody>
                                                        <tr>
                                                            <td valign="top" align="right" class="lh-3"
                                                                style="padding: 50px 20px 5px 30px; margin: 0px; line-height: 1.35; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:300;color:#000000; line-height:0.5;">
                                                               <span style="font-weight:700;">'.$orderLine->activePrice.'</span>
                                                            </span>
                                                            </td>
                                                        </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>';
            }
            $orderDiscount = $order->grossTotal - $order->netTotal - $order->shippingPrice;
            //shipmentAddress compiler
            $shipmentAddress='';
            $shipmentAddress.='
                                                                <tr>
                                                                    <td valign="top" align="lef" class="lh-1"
                                                                        style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                                '.$order->shipmentAddress->name . ' ' . $order->shipmentAddress->surname.'
                                                            </span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td valign="top" align="lef" class="lh-1"
                                                                        style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                               '.$order->shipmentAddress->address.'
                                                            </span>
                                                                    </td>
                                                                </tr>';
            if (!empty($order->shipmentAddress->extra)):
                $shipmentAddress.='<tr>
                                                                        <td valign="top" align="lef" class="lh-1"
                                                                            style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">'.
                    $order->shipmentAddress->extra.'
                                                            </span>
                                                                        </td>
                                                                    </tr>';
            endif;
            $shipmentAddress.='<tr>
                                                                    <td valign="top" align="lef" class="lh-1"
                                                                        style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                               '.$order->shipmentAddress->postcode.'
                                                                , '.$order->shipmentAddress->city.'
                                                                , '.$order->shipmentAddress->province.'
                                                            </span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td valign="top" align="lef" class="lh-1"
                                                                        style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                                '.$order->shipmentAddress->country->name.'
                                                            </span>
                                                                    </td>
                                                                </tr>';

//bill address compiler
            $billAddress='';
            $billAddress.='    <tr>
                                                                                            <td valign="top" align="lef"
                                                                                                class="lh-1"
                                                                                                style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                                '.$order->billingAddress->name . ' ' . $order->billingAddress->surname.'
                                                            </span>
                                                                                            </td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td valign="top" align="lef"
                                                                                                class="lh-1"
                                                                                                style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                               '.$order->billingAddress->address.'
                                                            </span>
                                                                                            </td>
                                                                                        </tr>';
            if (!empty($order->billingAddress->extra)):
                $billAddress.='<tr>
                                                                                                <td valign="top"
                                                                                                    align="lef"
                                                                                                    class="lh-1"
                                                                                                    style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                                '.$order->billingAddress->extra.'
                                                            </span>
                                                                                                </td>
                                                                                            </tr>';
            endif;
            $billAddress.='<tr>
                                                                                            <td valign="top" align="lef"
                                                                                                class="lh-1"
                                                                                                style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                                '.$order->billingAddress->postcode.'
                                                                , '.$order->billingAddress->city.'
                                                                , '.$order->billingAddress->province.'
                                                            </span>
                                                                                            </td>
                                                                                        </tr>
                                                                                        <tr>
                                                                                            <td valign="top" align="lef"
                                                                                                class="lh-1"
                                                                                                style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                                '.$order->billingAddress->country->name.'
                                                            </span>
                                                                                            </td>
                                                                                        </tr>';
            if(!empty($order->billingAddress->fiscalCode)):
                $billAddress.='<tr>
                                                                                            <td valign="top" align="lef"
                                                                                                class="lh-1"
                                                                                                style="padding: 0px 20px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;">
                                                            <span style="font-family:Helvetica,sans-serif;font-size:14px;font-weight:400;color:#000000
                                                               ;line-height: 1.1;">
                                                                '.$order->billingAddress->fiscalCode.'
                                                            </span>
                                                                                            </td>
                                                                                        </tr>';
            endif;

            $to = [$order->user->email];
            $toIwes=['gianluca@iwes.it'];
            $toIt=['it@iwes.it'];

            /*$this->app->mailer->prepare('neworderclient','no-reply', $to,[],[],['order'=>$order,'orderId'=>$order->id]);
            $res = $this->app->mailer->send();*/

            /** @var CEmailRepo $emailRepo */
            $emailRepo = \Monkey::app()->repoFactory->create('Email');
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $to,[],[],['order'=>$order,
                'orderId'=>$order->id,
                'orderDate'=>$order->orderDate,
                'userNameOrder'=>$order->user->name,
                'bodyOrder'=>$bodyOrder,
                'grossTotal'=>$order->grossTotal,
                'shippingPrice'=>$order->shippingPrice,
                'orderDiscount'=>$orderDiscount,
                'netTotal'=>$order->netTotal,
                'shipmentAddress'=>$shipmentAddress,
                'billAddress'=>$billAddress
            ]);
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $toIwes,[],[],['order'=>$order,
                'orderId'=>$order->id,
                'orderDate'=>$order->orderDate,
                'userNameOrder'=>$order->user->name,
                'bodyOrder'=>$bodyOrder,
                'grossTotal'=>$order->grossTotal,
                'shippingPrice'=>$order->shippingPrice,
                'orderDiscount'=>$orderDiscount,
                'netTotal'=>$order->netTotal,
                'shipmentAddress'=>$shipmentAddress,
                'billAddress'=>$billAddress
            ]);
            $emailRepo->newPackagedTemplateMail('neworderclient','no-reply@pickyshop.com', $toIt,[],[],['order'=>$order,
                'orderId'=>$order->id,
                'orderDate'=>$order->orderDate,
                'userNameOrder'=>$order->user->name,
                'bodyOrder'=>$bodyOrder,
                'grossTotal'=>$order->grossTotal,
                'shippingPrice'=>$order->shippingPrice,
                'orderDiscount'=>$orderDiscount,
                'netTotal'=>$order->netTotal,
                'shipmentAddress'=>$shipmentAddress,
                'billAddress'=>$billAddress
            ]);

        } catch (\Throwable $e) {
            $this->error('MailOrderClient',$e->getMessage(),$e);
        }
    }
}