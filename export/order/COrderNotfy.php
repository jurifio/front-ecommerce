<?php


namespace export\order;


use bamboo\domain\entities\COrder;

class COrderNotfy
{
    /** @var COrder $order */
    protected $order;

    public function __construct(COrder $order)
    {
        $this->order = $order;
    }

    public function sendMail()
    {
        foreach($this->order->productSku as $sku)
        {

        }
    }

    public function sendPreOrder(){

        foreach($this->order->productSku as $sku)
        {
            new $sku();
        }
    }

    public function sendConfirmedOrder()
    {
        // da eseguire dopo
    }

}