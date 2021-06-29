<?php


namespace bamboo\offline\productsync\import\mpk;
use bamboo\utils\time\STimeToolbox;
use bamboo\core\utils\slugify\CSlugify;
use DateTime;
use PDO;
use PDOException;
/**
 * Class CMpkOrderApi
 * @package bamboo\offline\productsync\import\mpk
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 28/06/2021
 * @since 1.0
 */

class CMpkOrderApi
{
    protected $addHoldOrderUrl="https://www.luxury888.it/holdorder/";
    protected $addOrderUrl = "https://testing.efashion.cloud/api/v3.0/place/order?storeCode=ASAHP";
    protected $deleteOrderUrl = "https://testing.efashion.cloud/api/v3.0/cancel/order?storeCode=ASAHP";

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
        $dirtyProduct=\Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['productId'=>$this->rows->productId,'productVariantId'=>$this->rows->productVariantId]);
        $dirtySku=\Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['dirtyProductId'=>$dirtyProduct->id,'productSizeId'=>$this->rows->productSizeId]);
        $order = '{
            
                "order_number":"'.$this->orderId.'",
                "date":"'.(new \DateTime($this->rows->creationDate))->format('Y-m-d').'",
                "items_count":"1",
                "items" : [
                    "product"=>"'.$dirtyProduct->extId.'",
                    "quantity":"1",
                    "size":"'.$dirtySku->size.'",
                    "purchase_price":"'.$this->rows->activePrice.'"

             }
       ]
            
        }';


        $curl = curl_init($this->addOrderUrl);
        curl_setopt($curl, CURLOPT_URL, $this->addOrderUrl);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            "Content-Type: application/x-www-form-urlencoded",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $data = "order=".$order;

        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

//for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);


        \Monkey::app()->applicationReport(
            'CMpkOrderApi',
            'Request: addOrder',
            'Request addOrder to' . $this->addOrderUrl,
            json_encode($order));
        $result = curl_exec($curl);
        $e = curl_error($curl);
        curl_close($curl);

        \Monkey::app()->applicationReport(
            'CMpkOrderApi',
            'Response: addOrder',
            'Response addOrder to' . $this->addOrderUrl,
            $result);

        return true;
    }

    public function deleteOrder(){

        $dirtyProduct=\Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['productId'=>$this->rows->productId,'productVariantId'=>$this->rows->productVariantId]);
        $dirtySku=\Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['dirtyProductId'=>$dirtyProduct->id,'productSizeId'=>$this->rows->productSizeId]);
        $order = '{
            
                "order_number":"'.$this->orderId.'",
                "date":"'.(new \DateTime($this->rows->creationDate))->format('Y-m-d').'",
                "items_count":"1",
                "items" : [
                    "product"=>"'.$dirtyProduct->extId.'",
                    "quantity":"1",
                    "size":"'.$dirtySku->size.'",
                    "purchase_price":"'.$this->rows->activePrice.'"

             }
       ]
            
        }';


        $curl = curl_init($this->addOrderUrl);
        curl_setopt($curl, CURLOPT_URL, $this->addOrderUrl);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            "Content-Type: application/x-www-form-urlencoded",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $data = "order=".$order;

        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

//for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        \Monkey::app()->applicationReport(
            'AlducaOrderApi',
            'Request: deleteOrder',
            'Request deleteOrder to' . $this->orderId
        );
        $result = curl_exec($curl);
        $e = curl_error($curl);
        curl_close($curl);



        \Monkey::app()->applicationReport(
            'CMpkOrderApi',
            'Response: deleteOrder',
            'Response deleteOrder to' . $this->deleteOrderUrl,
            $result);

        return true;
    }
}