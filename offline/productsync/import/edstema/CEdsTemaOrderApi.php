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
    protected $addHoldOrderUrl = "https://www.luxury888.it/holdorder/";
    protected $addOrderUrl = "https://testing.efashion.cloud/api/v3.0/place/order?storeCode=ASAHP";
    protected $deleteOrderUrl = "https://testing.efashion.cloud/api/v3.0/cancel/order?storeCode=ASAHP";

    protected $orderId;
    protected $rows;

    public function __construct($orderId,array $rows = null)
    {
        $this->orderId = $orderId;
        $this->rows = $rows;
    }

    public function newHoldOrder()
    {

    }

    public function newOrder()
    {
        $shop = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $this->rows->shopId]);
        $dirtyProduct = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['productId' => $this->rows->productId,'productVariantId' => $this->rows->productVariantId]);
        $storeHouseId = 1;
        $dirtySku = \Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['dirtyProductId' => $dirtyProduct->id,'productSizeId' => $this->rows->productSizeId,'storeHouseId' => 1]);
        if ($dirtySku->qty > 0) {
            $storeHouseId = $dirtySku->storeHouseId;
        } else {
            $dirtySku2 = \Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['dirtyProductId' => $dirtyProduct->id,'productSizeId' => $this->rows->productSizeId,'storeHouseId' => 3]);
            if ($dirtySku2->qty > 0) {
                $storeHouseId = $dirtySku2->storeHouseId;
            } else {
                $dirtySku3 = \Monkey::app()->repoFactory->create('DirtySku')->findOneBy(['dirtyProductId' => $dirtyProduct->id,'productSizeId' => $this->rows->productSizeId,'storeHouseId' => 4]);
                if ($dirtySku3->qty > 0) {
                    $storeHouseId = $dirtySku3->storeHouseId;
                } else {
                    $storeHouseId = 1;
                }
            }
        }
        if ($dirtySku)
            $codiceNegozio = '0' . $storeHouseId;
        $lenCodiceNegozio = strlen($codiceNegozio);
        $defCodiceNegozio = $codiceNegozio . str_repeat(' ',2 - $lenCodiceNegozio);
        $codiceArticolo = trim($dirtyProduct->extId);
        $lenCodiceArticolo = strlen($codiceArticolo);
        $defCodiceArticolo = $codiceArticolo . str_repeat(' ',10 - $lenCodiceArticolo);
        $variante = $dirtyProduct->var;
        $lenVariante = strlen($variante);
        $defVariante = $variante . str_repeat(' ',9 - $lenVariante);
        $defAnno = '';
        $defStagione = str_repeat(' ',1);
        $defCosto = str_repeat(' ',9);
        $taglia = $dirtySku->size;
        $lenTaglia = strlen($taglia);
        $defTaglia = $taglia . str_repeat(' ',4 - $lenTaglia);
        $defBarcode = '000000000000';
        $defCausale = 'VE';
        $defData = (new DateTime())->format('ymd');
        $defDataNew = (new DateTime())->format('ymd_his');
        $defBolla = str_repeat(' ',12);
        $defneg = '';
        $realizzo = $this->rows->activePrice * 1000;
        $lenRealizzo = strlen($realizzo);
        $defRealizzo = str_repeat(' ',8 - $lenRealizzo) . $realizzo;
        $defCommesso = str_repeat(' ',9);
        $defPrefisso = 'CLI';
        $defCliente = '888888';
        $listino = $this->rows->fullPrice * 1000;
        $lenListino = strlen($listino);
        $defListino = $listino . str_repeat(' ',9 - $lenListino);
        $defIva = '22';
        $defSegno = '+';
        $defQuantita = '0001';
        $defFiller = str_repeat(' ',25);
        $flag = '0';
        $orderLineString = $defCodiceNegozio . $defCodiceArticolo . $defVariante . $defAnno . $defStagione . $defCosto . $defTaglia . $defBarcode . $defCausale . $defData . $defBolla . $defneg . $defRealizzo . $defCommesso . $defPrefisso . $defCliente . $defListino . $defIva . $defSegno . $defQuantita . $defFiller . $flag;
        $name = $shop->name;
        if (ENV == 'prod') {
            $directory = '/home/iwespro/public_html/client/public/media/productsync/' . $name . '/export/';

        } else {
            $directory = '/media/sf_sites/iwespro/client/public/media/productync/' . $name . '/export/';
        }
        $fp = fopen($directory . $this->rows->orderId . '-' . $defDataNew . '.csv','w');
        fwrite($fp,$orderLineString);
        fclose($fp);


        \Monkey::app()->applicationReport(
            'CEdsTemaOrderApi',
            'Request: addOrder',
            'Request addOrder to' . $shopname,
            $directory . $this->rows->orderId . '-' . $defData . '.csv');


        return true;
    }

    public function deleteOrder()
    {


    }
}