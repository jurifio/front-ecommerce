<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 05/06/2015
 * Time: 15:42
 */

namespace bamboo\business\payment;

use bamboo\core\ecommerce\APaymentGateway;
use bamboo\core\exceptions\RedPandaException;

class CCreditCardGateway extends APaymentGateway {

    private $lang = ['it'=>'ITA', 'en'=>'ENG', 'de'=>'GER'];

    protected $paymentName = 'CreditCard';

    protected function getSecret()
    {
        return $this->app->cfg()->fetch('miscellaneous','orderGateways')['creditCard']['secret'];
    }

    protected function elaborateLinkUrl()
    {
        $base = 'https://ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet';
        //$TEST= [];
        //$TEST['url'] = 'https://coll-ecommerce.keyclient.it/ecomm/ecomm/DispatcherServlet';
        //$TEST['alias'] = 'payment_3444153';
        //$TEST['chiaveSegreta'] = 'TLGHTOWIZXQPTIZRALWKG';

        $cfg = $this->app->cfg()->fetch('miscellaneous','orderGateways');

        $param =[];
        $param['alias'] = $cfg['creditCard']['alias']; //'payment_341355';
        $param['importo'] = $this->order->netTotal*100;
        $param['divisa'] = 'EUR';

        try {
            $this->transactionNumber = isset($this->order->transactionNumber) && is_numeric($this->order->transactionNumber) ? ++$this->order->transactionNumber : 1;
            //TODO parametrizzare numero di tentativi
        } catch(\Throwable $e) {
            $this->transactionNumber = 1;
        }

        $param['codTrans'] = $this->order->id.'-'.$this->transactionNumber;
	    $param['mail'] = $this->order->user->email;

        $url = $cfg['thankYou'];
        $url = str_replace(':loc',$this->app->getLang()->getLang(),$url);
        $url = str_replace(':ord',$this->order->id,$url);
        $param['url'] = $url;

        $urlBck = $cfg['error'];
        $urlBck = str_replace(':loc',$this->app->getLang()->getLang(),$urlBck);
        $urlBck = str_replace(':ord',$this->order->id,$urlBck);

        $param['url_back'] = $urlBck;
        $param['languageId'] = $this->lang[$this->app->getLang()->getLang()];
        $param['urlpost'] = $cfg['creditCard']['urlpost'];
        $param['TABCONTAB'] = 'I';

        foreach($param as $key=>$val){
            $param[$key] = $key.'='.$val;
        }
        $this->transactionMac = sha1($param['codTrans'].$param['divisa'].$param['importo'].$this->getSecret());
        $param['mac']= 'mac='.$this->transactionMac;
        $this->url = $base.'?'.implode('&',$param);
        return true;
    }

    public function elaborateResponse($params)
    {
        try {
            $ordnum = explode('-',$params['codTrans']);
            $ordId = $ordnum[0];
            $ordTry = $ordnum[1];
            $orderRepo = \Monkey::app()->repoFactory->create('Order');
            $orders = $orderRepo->em()->findBySql("SELECT id FROM `Order` WHERE id = ? and transactionNumber = ?",array($ordId,$ordTry));

            if ($orders->count() != 1 ) {
                foreach($orders as $order){
                    $this->app->orderManager->registerEvent($order->id,'Pagamento Errore','Tentativo di pagamento fallito - CreditCard 1',$order->status);
                }
                throw new RedPandaException('Payment confrontation Error');
            }
            $order = $orders->getFirst();
            if ($params['esito'] != 'OK') {
                $this->app->orderManager->registerEvent($order->id,'Pagamento RIFIUTATO','Ricevuta risposta negativa per tentato pagamento - CreditCard',$order->status);
                throw new RedPandaException('Payment error, not OK');
            }

            $this->app->orderManager->registerEvent($order->id,'Pagamento notifica','Ricevuta risposta per tentato pagamento esito OK, elaborazione risposta.. - CreditCard',$order->status);

            /** Verifica Integrità */
            $verifica = "codTrans=".$params['codTrans'].
                        "esito=".$params['esito'].
                        "importo=".$params['importo'].
                        "divisa=".$params['divisa'].
                        "data=".$params['data'].
                        "orario=".$params['orario'].
                        "codAut=".$params['codAut'].
                        $this->getSecret();

            $sha = sha1($verifica);
            $this->app->orderManager->registerEvent($order->id,'Pagamento notifica','calcolo verifica = '.$verifica,$order->status);
            $this->app->orderManager->registerEvent($order->id,'Pagamento notifica','input mac = '.$params['mac'],$order->status);

            if ($sha  != $params['mac']) {
                return false;
            }
            $paidAmount = $params['importo'] / 100;
            $this->app->orderManager->pay($order,$paidAmount);

            $this->app->orderManager->registerEvent($order->id,'Pagamento ACCETTATO','Ricevuta risposta per tentato pagamento esito OK, sicurezza OK, pagato: '.$paidAmount.' - CreditCard',$order->status);
            return true;

        } catch(\Throwable $e) {

            try {
                $ordnum = explode('-',$params['codTrans']);
                $ordId = $ordnum[0];
                $this->app->orderManager->registerEvent($ordId,'Pagamento Errore','Tentativo di pagamento fallito - CreditCard 2',$order->status);
                try {

                    $order = \Monkey::app()->repoFactory->create("Order")->findOneBy(['id' => $ordId]);
                    $order->transactionMac = "";
                    $order->update();

                } catch (\Throwable $e) {
                    $this->app->router->response()->raiseUnauthorized();
                }
	            $this->app->applicationError("CreditCartGateway","Error while parsingResponse",$e->getMessage(),$e);

                return false;
            } catch(\Throwable $e) {
                $ordnum = explode('-',$params['codTrans']);
                $ordId = $ordnum[0];
                $this->app->orderManager->registerEvent($ordId,'Pagamento Errore','Tentativo di pagamento fallito - CreditCard 3',"???");
                try {

                    $order = \Monkey::app()->repoFactory->create("Order")->findOneBy(['id' => $ordId]);
                    $order->transactionMac = "";
                    $order->update();

                } catch (\Throwable $e) {
                    $this->app->router->response()->raiseUnauthorized();
                }
	            $this->app->applicationError("CreditCartGateway","Error while parsingResponse",$e->getMessage(),$e);
                //$this->app->dbAdapter->update('Order', array("transactionMac"=>""), array("id"=>$ordId));
                return false;
            }
        }
    }
}