<?php
namespace bamboo\business\payment;

use bamboo\core\base\CToken;
use bamboo\core\ecommerce\APaymentGateway;
use bamboo\core\exceptions\RedPandaException;

/**
 * Class CPayPalGateway
 * @package bamboo\app\business\payment
 */
class CPayPalGateway extends APaymentGateway
{
    protected $paymentName = 'PayPal';

    /**
     * @return bool
     * @throws RedPandaException
     */
    protected function elaborateLinkUrl()
    {
        $param = [];
        if(!isset($this->order) || empty($this->order)){
            throw new RedPandaException('No order Found');
        }
        $base = 'https://www.paypal.com/cgi-bin/webscr';// o nostro?
        $cfg = $this->app->cfg()->fetch('miscellaneous','orderGateways');
        $param['amount'] = $this->order->netTotal;
        $param['divisa'] = "EUR";
        //$param['form_action'] = 'https://www.paypal.com/cgi-bin/webscr';
        //  $param['currency_code'] = "EUR";
        $param['cmd'] = "_xclick";
        $param['no_note'] = 1;

        $param['image_url'] =  "https://www.thomasboutique.it/assets/logowid.png";

        $param['business'] =  $cfg['payPal']['business'];
        $param['item_name'] = "Ordine ".$this->app->getName()." ".$this->order->id." da ".$this->order->user->getFullName();

        if(!isset($this->order->transactionNumber) || empty($this->order->transactionNumber)) {
            $this->order->transactionNumber = 0 ;
        }
        $this->transactionNumber = $this->order->transactionNumber + 1;
        $token = new CToken(16);
        $this->transactionMac = $token->getUrlencodedToken();

        //inserisco nel custom un nuovo token casuale
        $param['custom'] = $this->transactionMac;

        $param['item_number'] = $this->order->id.'-'.$this->transactionNumber;
        $param['quantity'] = 1;

        $url = $cfg['thankYou'];
        $url = str_replace(':loc',$this->app->getLang()->getLang(),$url);
        $url = str_replace(':ord',$this->order->id,$url);
        $param['return'] = $url;

        $urlBck = $cfg['error'];
        $urlBck = str_replace(':loc',$this->app->getLang()->getLang(),$urlBck);
        $urlBck = str_replace(':ord',$this->order->id,$urlBck);
        $param['cancel_return'] = $urlBck;

        $param['notify_url'] = $cfg['payPal']['urlPost'];

        $this->url = $base.'?currency_code=EUR&'.http_build_query($param);
        return true;
    }

    /**
     * @param $params
     * @return bool
     * @throws RedPandaException
     */
    public function elaborateResponse($params)
    {
        $this->app->applicationReport('PaypalGateway','ElaborateResponse','Called to elaborate response: ',$params);
        $req = 'cmd=_notify-validate';
        $ordnum = explode('-', $params['item_number']);
        $ordId = $ordnum[0];
        $ordTry = $ordnum[1];
        $orderRepo = \Monkey::app()->repoFactory->create('Order');
        $orders = $orderRepo->em()->findBySql("SELECT id FROM `Order` WHERE id = ? AND transactionNumber = ? AND transactionMac = ?", array($ordId, $ordTry, $params['custom']));
        if ($orders->count() != 1) {
            foreach ($orders as $order) {
                $this->app->orderManager->registerEvent($order->id, 'Pagamento Errore', 'Tentativo di pagamento fallito - PayPal ', $order->status);
            }
            throw new RedPandaException('Payment confrontation Error');
        }
        $order = $orders->getFirst();

        $this->app->orderManager->registerEvent($order->id, 'Pagamento Paypal - in verifica', 'Ricevuto un pagamento da paypal, verifico autenticità', $order->status);

        foreach ($params as $key => $value) {
            $value = urlencode(stripslashes($value));
            $req .= "&$key=$value";
        }
        // post back to PayPal system to validate
        $header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
        $header .= "Host: ipnpb.paypal.com:443\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . strlen($req) . "\r\n\r\n";


        //$fp = fsockopen ('ssl://www.sandbox.paypal.com', 443, $errno, $errstr, 30);
        $fp = fsockopen('ssl://ipnpb.paypal.com', 443, $errno, $errstr, 30);

        if (!$fp) {
            $this->app->orderManager->registerEvent($order->id, "Pagamento Paypal - FAIL verifica'","'Errore nell'apertura fsock", $order->status);
        } else {
            fputs($fp, $header . $req);
            usleep(1000);
            while (!feof($fp)) {
                $res = fgets($fp, 1024);
                if (strcmp($res, "VERIFIED") == 0) {
                    $this->app->orderManager->registerEvent($order->id, 'Pagamento Paypal - Effettuato', 'Pagamento effettuato su Paypal, OK', $order->status);
                    $paidAmount = $params['mc_gross'];
                    $this->app->orderManager->pay($order, $paidAmount);
                    $this->app->orderManager->registerEvent($order->id, 'Pagamento ACCETTATO', 'Ricevuta risposta per tentato pagamento esito OK, sicurezza OK, pagato: ' . $paidAmount . ' - PayPal', $order->status);
                    return true;
                } else {
                    $this->app->orderManager->registerEvent($order->id, 'Pagamento RIFIUTATO', 'Ricevuta risposta per tentato pagamento esito KO order: ' . $order->id, $order->status);
                }
            }
        }
        return false;
    }
}