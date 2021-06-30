<?php


namespace bamboo\offline\productsync\import\edstema;
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

class CEdsTemaOrderApi
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
        $shop=\Monkey::app()->repoFactory->create('Shop')->findOneBy(['id'=>$this->rows->shopId]);
        $dirtyProduct=\Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['productId'=>$this->rows->productId,'productVariantId'=>$this->rows->productVariantId]);
        $dirtySku=\Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['dirtyProductId'=>$dirtyProduct->id,'productSizeId'=>$this->rows->productSizeId]);
        $codiceNegozio='0'.$dirtySku->storeHouseId;
        $lenCodiceNegozio=strlen($codiceNegozio);
        $defCodiceNegozio=str_repeat(' ',2-$lenCodiceNegozio).$codiceNegozio;
        $codiceArticolo=$dirtyProduct->extId;
        $lenCodiceArticolo=strlen($codiceArticolo);
        $defCodiceArticolo=str_repeat(' ',10-$lenCodiceArticolo).$codiceArticolo;
        $variante=$dirtyProduct->var;
        $lenVariante=strlen($variante);
        $defVariante=str_repeat(' ',10-$lenVariante).$variante;
        $defASC=str_repeat(' ',12);
        $taglia=$dirtySku->size;
        $lenTaglia=strlen($taglia);
        $defTaglia=str_repeat(' ',4-$lenTaglia).$taglia;
        $defBarcode='000000000000';
        $defCausale='VE';
        $defData=(new DateTime())->format('ymd_his');
        $defBolla=str_repeat(' ',14);
        $realizzo=$this->rows->activePrice*1000;
        $lenRealizzo=strlen($realizzo);
        $defRealizzo=str_repeat(' ',9-$lenRealizzo).$realizzo;
        $defCommesso=str_repeat(' ',6);
        $defPrefisso='CLI';
        $listino=$this->rows->fullPrice*1000;
        $lenListino=strlen($listino);
        $defListino=str_repeat(' ',9-$lenListino).$listino;
        $defIva='22';
        $defSegno='+';
        $defQuantita='0001';
        $defFiller=str_repeat(' ',25);
        $flag='0';
        $orderLineString=$defCodiceNegozio.$defCodiceArticolo.$defVariante.$defASC.$defTaglia.$defBarcode.$defCausale.$defData.$defBolla.$defRealizzo.$defCommesso.$defPrefisso.$defListino.$defIva.$defSegno.$defQuantita.$defFiller.$flag;
        $name=$shop->name;
        if(ENV=='prod') {
            $directory='/home/iwespro/public_html/client/public/media/productsync/'.$name.'/export/';

        }else{
            $directory='/media/sf_sites/iwespro/client/public/media/productync/'.$name.'/export/';
        }
        $fp = fopen($directory.$this->rows->orderId.'-'.$defData.'.csv','w');
        fwrite($fp, $orderLineString);
        fclose($fp);



        \Monkey::app()->applicationReport(
            'CEdsTemaOrderApi',
            'Request: addOrder',
            'Request addOrder to'. $shopname,
            $directory.$this->rows->orderId.'-'.$defData.'.csv');


        return true;
    }

    public function deleteOrder(){



    }
}