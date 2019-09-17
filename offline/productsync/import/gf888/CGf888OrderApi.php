<?php


namespace bamboo\offline\productsync\import\gf888;
/**
 * Class CGf888OrderApi
 * @package bamboo\offline\productsync\import\gf888
 */

class CGf888OrderApi
{
    protected $addHoldOrderUrl="https://www.luxury888.it/holdorder/";
    protected $addOrderUrl = "https://www.luxury888.it/orders/";
    protected $deleteOrderUrl = "http://rest.alducadaosta.com/api/Order?Username=Pickyshop&Password=%2B%2BPic%3DShp%2B%2B&OrderID=";

    protected $orderId;
    protected $rows;

    public function __construct($orderId, array $rows = null)
    {
        $this->orderId = $orderId;
        $this->rows = $rows;
    }
    public function newHoldOrder(){

    }

    public function newOrder(){

        $order = [
            [
                "OrderID" => $this->orderId,
                "Rows" => [
                    $this->rows
                ],
                "Addresses" => [
                    [
                        "CustomerID" => "C34839",
                        "AddressType" => "D",
                        "Name" => "Iwes snc",
                        "Address" => "via Cesare Pavese, 1",
                        "City" => "Civitanova Marche",
                        "ZIP" => "62012",
                        "State" => "MC",
                        "Country" => "Italy",
                        "CountryISO3" => "ITA",
                        "CEE" => "S",
                        "PhoneNumber" => "",
                        "eMail" => "",
                        "VatNumber" => "01865380438",
                        "Note" => ""
                    ]
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->addOrderUrl);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept',
            'Access-Control-Allow-Methods: POST',
            'Access-Control-Allow-Origin: *',
            'Content-type: application/json'
        ]);
        \Monkey::app()->applicationReport(
            'AlducaOrderApi',
            'Request: addOrder',
            'Request addOrder to' . $this->addOrderUrl,
            json_encode($order));
        $result = curl_exec($ch);
        $e = curl_error($ch);
        curl_close($ch);

        \Monkey::app()->applicationReport(
            'AlducaOrderApi',
            'Response: addOrder',
            'Response addOrder to' . $this->addOrderUrl,
            $result);

        return true;
    }

    public function deleteOrder(){

        $completeUrl = $this->deleteOrderUrl.$this->orderId;

        \Monkey::app()->applicationReport(
            'AlducaOrderApi',
            'Request: deleteOrder',
            'Request deleteOrder to' . $completeUrl
        );

        $result = file_get_contents(
            $completeUrl,
            false,
            stream_context_create(array(
                'http' => array(
                    'method' => 'DELETE'
                )
            ))
        );

        \Monkey::app()->applicationReport(
            'AlducaOrderApi',
            'Response: deleteOrder',
            'Response deleteOrder to' . $this->deleteOrderUrl,
            $result);

        return true;
    }
}