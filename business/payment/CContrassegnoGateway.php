<?php
namespace bamboo\business\payment;

use bamboo\core\ecommerce\APaymentGateway;

/**
 * Class CContrassegnoGateway
 * @package bamboo\app\business\payment
 */
class CContrassegnoGateway extends APaymentGateway
{
    protected $paymentName = 'Contrassegno';

    /**
     * @return string|bool
     */
    protected function elaborateLinkUrl()
    {
        $rawUrl = $this->app->cfg()->fetch('miscellaneous','orderGateways')['thankYou'];
        $url = str_replace(':loc',$this->app->getLang()->getLang(),$rawUrl);
        $url = str_replace(':ord',$this->order->id,$url);

        $this->transactionNumber = "1";
        $this->transactionMac = "1";

        $this->url = $url;
        return true;
    }

    /**
     * @param $params
     * @return bool
     */
    public function elaborateResponse($params) {}
}