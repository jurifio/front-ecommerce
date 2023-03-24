<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\theming\CRestrictedAccessWidgetHelper;
use bamboo\domain\entities\CInvoice;
use bamboo\domain\entities\CProductSku;
use PDO;

/**
 * Class CInvoiceDocumentRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 02/05/2018
 * @since 1.0
 */
class CInvoiceRepo extends ARepo
{
    public function createNewInvoiceToOrderParallel(int $orderId,int $remoteOrderSupplierId,int $remoteShopSupplierId,int $amountForInvoice): bool
    {

        $orderRepo = \Monkey::app()->repoFactory->create('Order');
        $shopRepo = \Monkey::app()->repoFactory->create('Shop');
        $addressBookRepo = \Monkey::app()->repoFactory->create('AddressBook');

        $order = $orderRepo->findOneBy(['id' => $orderId]);
        $remoteShopSellerId = $order->remoteShopSellerId;


        // prendo l'intestazione
        $shopInvoices = $shopRepo->findOneBy(['id' => 44]);
        $logo = $shopInvoices->logo;
        $intestation = $shopInvoices->intestation;
        $intestation2 = $shopInvoices->intestation2;
        $address = $shopInvoices->address;
        $address2 = $shopInvoices->address2;
        $iva = $shopInvoices->iva;
        $tel = $shopInvoices->tel;
        $email = $shopInvoices->email;
        /***sezionali******/
        $receipt = $shopInvoices->receipt;
        $invoiceUe = $shopInvoices->invoiceUe;
        $invoiceExtraUe = $shopInvoices->invoiceExtraUe;
        $siteInvoiceChar = $shopInvoices->siteInvoiceChar;

        /* Dati destinatario Fattura shop Seller */
        $customerDataSeller = $shopRepo->findOneBy(['id' => $remoteShopSellerId]);
        $userAddress = $addressBookRepo->findOneBy(['id' => $customerDataSeller->billingAddressBookId]);
        $extraUe = $userAddress->countryId;
        $countryRepo = \Monkey::app()->repoFactory->create('Country');
        $findIsExtraUe = $countryRepo->findOneBy(['id' => $extraUe]);
        $isExtraUe = $findIsExtraUe->extraue;


        if ($extraUe != '110') {
            $changelanguage = "1";

        } else {
            $changelanguage = "0";
        }

//inserimento fattura e assegnazione sezionale
        $hasInvoice = 1;
        $invoiceRepo = \Monkey::app()->repoFactory->create('Invoice');
        $invoiceNew = $invoiceRepo->getEmptyEntity();
        $siteChar = $siteInvoiceChar;
        if ($order->invoice->isEmpty()) {
            try {
                $invoiceNew->orderId = $orderId;
                $today = new \DateTime();
                $invoiceNew->invoiceYear = $today->format('Y-m-d H:i:s');
                $year = (new \DateTime())->format('Y');
                $em = $this->app->entityManagerFactory->create('Invoice');
                // se è fattura
                if ($hasInvoice == '1') {
                    //se è extracee
                    if ($isExtraUe == '1') {
                        // se è Pickyshop
                        if ($remoteShopSellerId == 44) {
                            // è Pickyshop

                            //è Ecommerce Parallelo
                            $invoiceType = $invoiceExtraUe;
                            $invoiceTypeVat = 'newX';
                            $documentType = '20';
                        }
                        //se è non è inglese
                        if ($changelanguage != "1") {
                            // è inglese
                            $invoiceTypeText = "Fattura N. :";
                            $invoiceHeaderText = "FATTURA";
                            $invoiceTotalDocumentText = "Totale Fattura";
                            $documentType = '20';
                        } else {
                            //è italiano
                            $invoiceTypeText = "Invoice N. :";
                            $invoiceHeaderText = "INVOICE";
                            $invoiceTotalDocumentText = "Invoice Total";


                        }
                    } else {
                        // è fattura intracomunitario
                        // se è pickyshop
                        if ($remoteShopSellerId == '44') {
                            // è pickyshop
                            // è fattura Ecommerce Parallelo
                            $invoiceType = $invoiceUe;
                            $documentType = '21';
                            $invoiceTypeVat = 'newP';
                        }
                        // se non è inglese
                        if ($changelanguage != "1") {
                            // è italiano
                            $invoiceTypeText = "Fattura N. :";
                            $invoiceHeaderText = "FATTURA";
                            $invoiceTotalDocumentText = "Totale Fattura";
                        } else {
                            // non è italiano
                            $invoiceTypeText = "Invoice N. :";
                            $invoiceHeaderText = "INVOICE";
                            $invoiceTotalDocumentText = "Invoice Total";
                        }
                    }
                    // fine distinzione  tra intracee e extracee
                } else {
                    // è ricevuta
                    // se è pickyshop
                    if ($remoteShopSellerId == '44') {
                        //è pickyshop
                        $documentType = '16';
                        $invoiceType = 'K';
                        $invoiceTypeVat = 'newK';
                    } else {
                        // non è pickyshop
                        $invoiceType = $receipt;
                        $documentType = '22';
                        $invoiceTypeVat = 'newK';
                    }
                    // se non è inglese
                    if ($changelanguage != "1") {

                        // è italiano
                        $invoiceTypeText = "Ricevuta N. :";
                        $invoiceHeaderText = "RICEVUTA";
                        $invoiceTotalDocumentText = "Totale Ricevuta";
                    } else {
//è inglese
                        $invoiceTypeText = "Receipt N. :";
                        $invoiceHeaderText = "RECEIPT";
                        $invoiceTotalDocumentText = "Receipt Total";

                    }
                }
                switch($customerDataSeller->id){
                    case 1:
                        $filterUserShop=3692;
                        break;
                    case 51:
                        $filterUserShop=5935;
                        break;
                }

                //definzione dello shop seller al fine di reperire l'id utente che lo gestisce
                $userHasShop = \Monkey::app()->repoFactory->create('UserHasShop')->findOneBy(['shopId' => $customerDataSeller->id,'id'=>$filterUserShop]);
                $remoteUserSellerId = $userHasShop->userId;

                $number = $em->query("SELECT ifnull(MAX(invoiceNumber),0)+1 AS new
                                      FROM Invoice
                                      WHERE
                                      Invoice.invoiceYear = ? AND
                                      Invoice.invoiceType='" . $invoiceType . "' AND
                                      Invoice.invoiceShopId='" . $shopInvoices->id . "' AND
                                      Invoice.invoiceSiteChar= ?",[$year,$siteChar])->fetchAll()[0]['new'];


                $invoiceNew->invoiceShopId = $shopInvoices->id;
                $invoiceNew->invoiceNumber = $number;
                $invoiceNew->invoiceSiteChar = $siteChar;
                $invoiceNew->invoiceType = $invoiceType;
                $invoiceNew->invoiceDate = $today->format('Y-m-d H:i:s');
                $todayInvoice = $today->format('d/m/Y');
                $invoiceRepo->insert($invoiceNew);
                $sectional = $number . '/' . $invoiceType;


                /** ottengo il valore della Vendita per la registrazione del  Documento */
                $orderLineRepo = \Monkey::app()->repoFactory->create('OrderLine')->findBy(['orderId' => $orderId]);
                $documentRepo = \Monkey::app()->repoFactory->create('Document');
                // codice per inserire all'interno della cartella document
                $checkIfDocumentExist = $documentRepo->findOneBy(['number' => $number,'year' => $year]);
                if ($checkIfDocumentExist == null) {
                    $insertDocument = $documentRepo->getEmptyEntity();
                    $insertDocument->userId = $remoteUserSellerId;
                    $insertDocument->shopRecipientId = $customerDataSeller->id;
                    $insertDocument->number = $sectional;
                    $insertDocument->date = $order->orderDate;
                    $insertDocument->invoiceTypeId = $documentType;
                    $insertDocument->paydAmount = $amountForInvoice;
                    $insertDocument->paymentExpectedDate = $order->paymentDate;
                    $insertDocument->note = $order->note;
                    $insertDocument->creationDate = $order->orderDate;
                    $insertDocument->totalWithVat = $amountForInvoice;
                    $insertDocument->year = $year;
                    $insertDocument->insert();
                }
                /* definizione dello shop Supplier*/
                $remoteShopSupplier = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $remoteShopSupplierId]);
                /*** dati db esterno ***/
                $db_host = $remoteShopSupplier->dbHost;
                $db_name = $remoteShopSupplier->dbName;
                $db_user = $remoteShopSupplier->dbUsername;
                $db_pass = $remoteShopSupplier->dbPassword;





            } catch (\Throwable $e) {
                throw $e;
                $this->app->router->response()->raiseProcessingError();
                $this->app->router->response()->sendHeaders();
            }
        }


        foreach ($order->invoice as $invoice) {
            if (is_null($invoice->invoiceText)) {
                $userAddress=\Monkey::app()->repoFactory->create('UserAddress')->findOneBy(['id'=>$filterUserShop]);
                $userShipping=\Monkey::app()->repoFactory->create('UserAddress')->findOneBy(['id'=>$filterUserShop]);


                $productRepo = \Monkey::app()->repoFactory->create('ProductNameTranslation');
                if ($hasInvoice == '1') {
                    if ($isExtraUe == '1') {
                        if ($remoteShopSellerId == '44') {
                            $invoiceType = 'X';
                            $invoiceTypeVat = 'newX';
                        } else {
                            $invoiceType = $invoiceExtraUe;
                            $invoiceTypeVat = 'newX';
                        }
                        if ($changelanguage != "1") {
                            $invoiceTypeText = "Fattura N. :";
                            $invoiceHeaderText = "FATTURA";
                            $invoiceTotalDocumentText = "Totale Fattura";
                        } else {
                            $invoiceTypeText = "Invoice N. :";
                            $invoiceHeaderText = "INVOICE";
                            $invoiceTotalDocumentText = "Invoice Total";
                        }
                    } else {
                        if ($remoteShopSellerId == '44') {
                            $invoiceType = 'P';
                            $invoiceTypeVat = 'newP';
                        } else {
                            $invoiceType = $invoiceUe;
                            $invoiceTypeVat = 'newP';
                        }
                        if ($changelanguage != "1") {
                            $invoiceTypeText = "Fattura N. :";
                            $invoiceHeaderText = "FATTURA";
                            $invoiceTotalDocumentText = "Totale Fattura";
                        } else {
                            $invoiceTypeText = "Invoice N. :";
                            $invoiceHeaderText = "INVOICE";
                            $invoiceTotalDocumentText = "Invoice Total";
                        }
                    }
                } else {
                    if ($remoteShopSellerId == '44') {
                        $invoiceType = 'K';
                        $invoiceTypeVat = 'newK';
                    } else {
                        $invoiceType = $receipt;
                        $invoiceTypeVat = 'newK';
                    }
                    if ($changelanguage != "1") {

                        $invoiceTypeText = "Ricevuta N. :";
                        $invoiceHeaderText = "RICEVUTA";
                        $invoiceTotalDocumentText = "Totale Ricevuta";
                    } else {

                        $invoiceTypeText = "Receipt N. :";
                        $invoiceHeaderText = "RECEIPT";
                        $invoiceTotalDocumentText = "Receipt Total";

                    }
                }



                /*'logo' => $this->app->cfg()->fetch("miscellaneous", "logo"),
                    'fiscalData' => $this->app->cfg()->fetch("miscellaneous", "fiscalData"),*/
                $invoice->invoiceText = compileParallelInvoicecompileParallelInvoice(
                    $userAddress,
                    $userShipping,
                    $order,
                    $invoice,
                    $productRepo,
                    $logo,
                    $intestation,
                    $intestation2,
                    $address,
                    $address2,
                    $iva,
                    $tel,
                    $email,
                    $invoiceType,
                    $invoiceTypeVat,
                    $invoiceTypeText,
                    $invoiceTotalDocumentText,
                    $invoiceHeaderText,
                    $changelanguage);
                try {
                    $invoiceRepo->update($invoice);

                    if ($remoteShopSellerId == '44') {
                        $api_uid = $this->app->cfg()->fetch('fattureInCloud', 'api_uid');
                        $api_key = $this->app->cfg()->fetch('fattureInCloud', 'api_key');
                        if ($hasInvoice == '1' && $isExtraUe == '0') {
                            $insertJson = '{
  "api_uid": "' . $api_uid . '",
  "api_key": "' . $api_key . '",
  "id_cliente": "0",
  "id_fornitore": "0",
  "nome": "' . $userAddress->surname . ' ' . $userAddress->name . ' ' . $userAddress->company . '",
  "indirizzo_via": "' . $userAddress->address . '",
  "indirizzo_cap": "' . $userAddress->postcode . '",
  "indirizzo_citta": "' . $userAddress->city . '",
  "indirizzo_provincia": "' . $userAddress->province . '",
  "indirizzo_extra": "",
  "paese": "Italia",
  "paese_iso": "' . $userAddress->country->ISO . '",
  "lingua": "it",
  "piva": "' . $userAddress->fiscalCode . '",
  "cf": "' . $userAddress->fiscalCode . '",
  "autocompila_anagrafica": false,
  "salva_anagrafica": false,
  "numero": "' . $sectional . '",
  "data": "' . $todayInvoice . '",
  "valuta": "EUR",
  "valuta_cambio": 1,
  "prezzi_ivati": true,
  "rivalsa": 0,
  "cassa": 0,
  "rit_acconto": 0,
  "imponibile_ritenuta": 0,
  "rit_altra": 0,
  "marca_bollo": 0,
  "oggetto_visibile": "",
  "oggetto_interno": "",
  "centro_ricavo": "",
  "centro_costo": "",
  "note": "",
  "nascondi_scadenza": false,
  "ddt": false,
  "ftacc": false,
  "id_template": "0",
  "ddt_id_template": "0",
  "ftacc_id_template": "0",
  "mostra_info_pagamento": false,';
                            $orderPaymentMethodId = $order->orderPaymentMethodId;
                            $orderPaymentMethodTranslation = \Monkey::app()->repoFactory->create('OrderPaymentMethodTranslation')->findOneBy(['orderPaymentMethodId' => $orderPaymentMethodId, 'langId' => 1]);
                            $metodo_pagamento = $orderPaymentMethodTranslation->name;
                            switch ($orderPaymentMethodId) {
                                case 1:
                                    $metodo_titoloN = 'Merchant Paypal';
                                    $metodo_descN = $api_uid = $this->app->cfg()->fetch('payPal', 'business');
                                    break;
                                case 2:
                                    $metodo_titoloN = 'Merchant Nexi';
                                    $metodo_descN = '';
                                    break;
                                case 3:
                                    $metodo_titoloN = 'IBAN';
                                    $metodo_descN = 'IT54O0521613400000000002345';
                                    break;
                                case 5:
                                    $metodo_titoloN = '';
                                    $metodo_descN = '';
                                    break;

                            }


                            $insertJson .= '"metodo_pagamento": "' . $metodo_pagamento . '",
  "metodo_titoloN": "' . $metodo_titoloN . '",
  "metodo_descN": "' . $metodo_descN . '",
  "mostra_totali": "tutti",
  "mostra_bottone_paypal": false,
  "mostra_bottone_bonifico": false,
  "mostra_bottone_notifica": false,';
                            $insertJson .= '"lista_articoli": ';
                            $tot = 0;
                            $i = 0;
                            $scontotot = 0;
                            $articoli = [];
                            $ordinearticolo = 0;
                            foreach ($order->orderLine as $orderLine) {
                                $idlineaordine = $i + 1;
                                $idOrderLine = $orderLine->id;
                                $ordinearticolo + 1;
                                $productSku = CProductSku::defrost($orderLine->frozenProduct);
                                $codice = $orderLine->orderId . "-" . $orderLine->id;
                                $productNameTranslation = $productRepo->findOneBy(['productId' => $productSku->productId, 'productVariantId' => $productSku->productVariantId, 'langId' => '1']);
                                $nome = $productSku->productId . "-" . $productSku->productVariantId . "-" . $productSku->productSizeId;
                                $um = "";
                                $quantity = $productSku->stockQty;
                                $descrizione = (($productNameTranslation) ? $productNameTranslation->name : '') . ($orderLine->warehouseShelfPosition ? ' / ' . $orderLine->warehouseShelfPosition->printPosition() : '') . ' ' . $productSku->product->productBrand->name . ' - ' . $productSku->productId . '-' . $productSku->productVariantId . " " . $productSku->getPublicSize()->name;
                                $categoria = "";
                                $prezzo_netto = number_format($orderLine->activePrice + $orderLine->couponCharge, 2,'.');
                                $prezzo_lordo = number_format($orderLine->activePrice, 2,'.');
                                $scontoCharge = number_format($orderLine->couponCharge, 2,'.');
                                $sconto = abs($scontoCharge);
                                $sconto = number_format(100 * $sconto / $orderLine->activePrice, 2,'.');
                                $cod_iva = "0";
                                $applica_ra_contributi = "true";
                                $ordine = $ordinearticolo;
                                $sconto_rosso = "0";
                                $in_ddt = false;
                                $magazzino = true;
                                $scontotot += abs($scontoCharge);
                                $tot += $orderLine->activePrice;
                                /*  $insertLineJSon.='{
                                         "id": "'.$idlineaordine.'",
                                        "codice": "'.$codice.'",
                                        "nome": "'.$descrizione.'",
                                        "um": "",
                                        "quantita": '.$quantity.',
                                        "descrizione": "'.$descrizione.'",
                                        "categoria": "",
                                        "prezzo_netto": '.$prezzo_netto.',
                                        "prezzo_lordo": '.$prezzo_lordo.',
                                        "cod_iva": 0,
                                        "tassabile": true,
                                        "sconto": '.$sconto.',
                                        "applica_ra_contributi": true,
                                        "ordine": '.$ordine.',
                                        "sconto_rosso": 0,
                                        "in_ddt": false,
                                        "magazzino": true},
                                  ';*/
                                $articoli[] = [
                                    'id' => $idlineaordine,
                                    'codice' => $codice,
                                    'nome' => $nome,
                                    'um' => $um,
                                    'quantita' => 1,
                                    'descrizione' => $descrizione,
                                    'categoria' => $categoria,
                                    'prezzo_netto' => $prezzo_netto,
                                    'prezzo_lordo' => $prezzo_lordo,
                                    'cod_iva' => $cod_iva,
                                    'tassabile' => true,
                                    'sconto' => $sconto,
                                    'applica_ra_contributi' => $applica_ra_contributi,
                                    'ordine' => $ordine,
                                    'sconto_rosso' => $sconto_rosso,
                                    'in_ddt' => $in_ddt,
                                    'magazzino' => $magazzino
                                ];
                            }
                            $tot = number_format($tot, 2,'.') - number_format($scontotot, 2,'.');
                            $today = new \DateTime();
                            $dateInvoice = $today->format('d/m/Y');
                            $insertJson .= json_encode($articoli) . ',
                  
                "lista_pagamenti": [
              {
               "data_scadenza":"' . $dateInvoice . '",
               "importo": ' . number_format($tot, 2) . ',
               "metodo": "not",
               "data_saldo": "' . $dateInvoice . '" 
              }
              ],
              "ddt_numero": "",
              "ddt_data": "' . $dateInvoice . '",
              "ddt_colli": "",
              "ddt_peso": "",
              "ddt_causale": "",
              "ddt_luogo": "",
              "ddt_trasportatore": "",
              "ddt_annotazioni": "",
              "PA": false, 
              "PA_tipo_cliente": "B2B", 
              "PA_tipo": "nessuno",
              "PA_numero": "",
              "PA_data": "' . $dateInvoice . '",
              "PA_cup": "",
              "PA_cig": "",
              "PA_codice": "",
              "PA_pec": "",
              "PA_esigibilita": "N",
              "PA_modalita_pagamento": "MP01",
              "PA_istituto_credito": "",
              "PA_iban": "",
              "PA_beneficiario": "",
              "extra_anagrafica": {
                "mail": "",
                "tel": "",
                "fax": ""
              },
              "split_payment": false
            }';
                            \Monkey::app()->applicationLog('InvoiceAjaxController', 'Report', 'jsonfattureincloud', 'Json Fatture in Cloud fattura Numero' . $number . ' data:' . $dateInvoice, $insertJson);
                            $urlInsert = "https://api.fattureincloud.it/v1/fatture/nuovo";
                            $options = array(
                                "http" => array(
                                    "header" => "Content-type: text/json\r\n",
                                    "method" => "POST",
                                    "content" => $insertJson
                                ),
                            );
                            $context = stream_context_create($options);
                            $result = json_decode(file_get_contents($urlInsert, false, $context), true);
                            if (array_key_exists('success', $result)) {
                                $resultApi = "Risultato=" . $result['success'] . " new_id:" . $result['new_id'] . " token:" . $result['token'];
                            } else {
                                $resultApi = "Errore=" . $result['error'] . " codice di errore:" . $result['error_code'];
                            }
                            \Monkey::app()->applicationLog('InvoiceAjaxController', 'Report', 'ResponseApi fatture in Cloud Numero' . $sectional . ' data:' . $dateInvoice, 'Risposta FatturaincCloud', $resultApi);
                            if (array_key_exists('new_id', $result)) {
                                $fattureinCloudId = $result['new_id'];
                            }
                            if (array_key_exists('token', $result)) {
                                $fattureinCloudToken = $result['token'];

                                $updateInvoice = \Monkey::app()->repoFactory->create('Invoice')->findOneBy(['orderId' => $orderId]);
                                $updateInvoice->fattureInCloudId = $fattureinCloudId;
                                $updateInvoice->fattureInCloudToken = $fattureinCloudToken;
                                $updateInvoice->update();
                            }


                        }
                    }

                } catch (\Throwable $e) {
                    throw $e;
                    $this->app->router->response()->raiseProcessingError();
                    $this->app->router->response()->sendHeaders();


                }
            }
            $db_con = new PDO("mysql:host={$db_host};dbname={$db_name}", $db_user, $db_pass);
            $db_con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $updateRemoteInvoice = $invoiceRepo->findOneBy(['orderId' => $order->id]);
            $invoiceTextUpdate = $updateRemoteInvoice->invoiceText;
            $stmtInvoiceUpdate = $db_con->prepare(" UPDATE Invoice SET invoiceText = :invoiceText where orderId = :remoteId");

            $stmtInvoiceUpdate->bindValue(':invoiceText', $invoice->invoiceText, PDO::PARAM_STR);
            $stmtInvoiceUpdate->bindValue(':remoteId', $order->remoteOrderSellerId, PDO::PARAM_INT);
            $stmtInvoiceUpdate->execute();





            return $invoice->invoiceText;
        }





            return true;
        }

    /**
     * @param integer $userAddress
     * @param integer $userShipping
     * @param integer $divisionTime ['month', 'week', 'day', 'hour']
     * @param string $start date, parsed by strtoTime. I dati in uscita hanno ordine cronologico decrescente, perciò start è la data più alta
     * @param array ['fieldname', 'alias'] i campi del select riportati
     * @return string $invoiceText;
     */

    public function compileParallelInvoice( $userAddress,
                                            $userShipping,
                                            $order,
                                            $invoice,
                                            $productRepo,
                                            $logo,
                                            $intestation,
                                            $intestation2,
                                            $address,
                                            $address2,
                                            $iva,
                                            $tel,
                                            $email,
                                            $invoiceType,
                                            $invoiceTypeVat,
                                            $invoiceTypeText,
                                            $invoiceTotalDocumentText,
                                            $invoiceHeaderText,
                                            $changelanguage): string
    {
        $invoiceDate = new DateTime($invoice->invoiceDate);

        $invoiceText .= '
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/html">
<head>';
        $invoiceText .= '<meta http-equiv="content-type" content="text/html;charset=UTF-8"/>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
<link rel="icon" type="image/x-icon" sizes="32x32" href="/assets/img/favicon32.png"/>
<link rel="icon" type="image/x-icon" sizes="256x256" href="/assets/img/favicon256.png"/>
<link rel="icon" type="image/x-icon" sizes="16x16" href="/assets/img/favicon16.png"/>
<link rel="apple-touch-icon" type="image/x-icon" sizes="256x256" href="/assets/img/favicon256.png"/>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-touch-fullscreen" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta content="" name="description"/>
<meta content="" name="author"/>
<script>
    paceOptions = {
        ajax: {ignoreURLs: [\'/blueseal/xhr/TemplateFetchController\', \'/blueseal/xhr/CheckPermission\']}
    }
</script>
    <link type="text/css" href="https://www.iwes.pro/assets/css/pace.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://code.jquery.com/ui/1.11.4/themes/flick/jquery-ui.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://s3-eu-west-1.amazonaws.com/bamboo-css/jquery.scrollbar.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://s3-eu-west-1.amazonaws.com/bamboo-css/bootstrap-colorpicker.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://github.com/mar10/fancytree/blob/master/dist/skin-common.less" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.24.0/skin-bootstrap/ui.fancytree.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.4/css/selectize.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.0.1/min/basic.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.0.1/min/dropzone.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/ui.dynatree.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.6.16/summernote.min.css" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://fonts.googleapis.com/css?family=Raleway" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,700,300" rel="stylesheet" media="screen"/>
<link type="text/css" href="https://raw.githubusercontent.com/kleinejan/titatoggle/master/dist/titatoggle-dist-min.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/pages-icons.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/pages.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/style.css" rel="stylesheet" media="screen,print"/>
<link type="text/css" href="https://www.iwes.pro/assets/css/fullcalendar.css" rel="stylesheet" media="screen,print"/>
<script  type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js"></script>
<script  type="application/javascript" src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
<script  type="application/javascript" src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
<script  type="application/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/pages.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/blueseal.prototype.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/blueseal.ui.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.3/jquery.easing.min.js"></script>
<script defer type="application/javascript" src="https://cdn.jsdelivr.net/jquery.bez/1.0.11/jquery.bez.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/unveil/1.3.0/jquery.unveil.min.js"></script>
<script defer type="application/javascript" src="https://s3-eu-west-1.amazonaws.com/bamboo-js/jquery.scrollbar.min.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/Sortable.min.js"></script>
<script defer type="application/javascript" src="https://s3-eu-west-1.amazonaws.com/bamboo-js/bootstrap-colorpicker.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.24.0/jquery.fancytree-all.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.12.4/js/standalone/selectize.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.0.1/min/dropzone.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/4.0.1/min/dropzone-amd-module.min.js"></script>
<script defer type="application/javascript" src="https://cdn.jsdelivr.net/jquery.dynatree/1.2.4/jquery.dynatree.min.js"></script>
<script defer type="application/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.6.16/summernote.min.js"></script>
<script defer type="application/javascript" src="https://s3-eu-west-1.amazonaws.com/bamboo-js/summernote-it-IT.js"></script>
<script defer type="application/javascript" src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.14.0/jquery.validate.min.js"></script>
<script defer type="application/javascript" src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.14.0/additional-methods.min.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/blueseal.kickstart.js"></script>
<script  type="application/javascript" src="https://www.iwes.pro/assets/js/monkeyUtil.js"></script>
<script defer type="application/javascript" src="https://www.iwes.pro/assets/js/invoice_print.js"></script>
<script defer async type="application/javascript" src="https://www.iwes.pro/assets/js/blueseal.common.js"></script>
    <title>BlueSeal - Stampa fattura</title>
    <style type="text/css">';


        $invoiceText .= '
        @page {
            size: A4;
            margin: 5mm 0mm 0mm 0mm;
        }

        @media print {
            body {
                zoom: 100%;
                width: 800px;
                height: 1100px;
                overflow: hidden;
            }

            .container {
                width: 100%;
            }

            .newpage {
                page-break-before: always;
                page-break-after: always;
                page-break-inside: avoid;
            }

            @page {
                size: A4;
                margin: 5mm 0mm 0mm 0mm;
            }

            .cover {
                display: none;
            }

            .page-container {
                display: block;
            }

            /*remove chrome links*/
            a[href]:after {
                content: none !important;
            }

            .col-md-1,
            .col-md-2,
            .col-md-3,
            .col-md-4,
            .col-md-5,
            .col-md-6,
            .col-md-7,
            .col-md-8,
            .col-md-9,
            .col-md-10,
            .col-md-11,
            .col-md-12 {
                float: left;
            }

            .col-md-12 {
                width: 100%;
            }

            .col-md-11 {
                width: 91.66666666666666%;
            }

            .col-md-10 {
                width: 83.33333333333334%;
            }

            .col-md-9 {
                width: 75%;
            }

            .col-md-8 {
                width: 66.66666666666666%;
            }

            .col-md-7 {
                width: 58.333333333333336%;
            }

            .col-md-6 {
                width: 50%;
            }

            .col-md-5 {
                width: 41.66666666666667%;
            }

            .col-md-4 {
                width: 33.33333333333333%;
            }

            .col-md-3 {
                width: 25%;
            }

            .col-md-2 {
                width: 16.666666666666664%;
            }

            .col-md-1 {
                width: 8.333333333333332%;
            }

            .col-md-pull-12 {
                right: 100%;
            }

            .col-md-pull-11 {
                right: 91.66666666666666%;
            }

            .col-md-pull-10 {
                right: 83.33333333333334%;
            }

            .col-md-pull-9 {
                right: 75%;
            }

            .col-md-pull-8 {
                right: 66.66666666666666%;
            }

            .col-md-pull-7 {
                right: 58.333333333333336%;
            }

            .col-md-pull-6 {
                right: 50%;
            }

            .col-md-pull-5 {
                right: 41.66666666666667%;
            }

            .col-md-pull-4 {
                right: 33.33333333333333%;
            }

            .col-md-pull-3 {
                right: 25%;
            }

            .col-md-pull-2 {
                right: 16.666666666666664%;
            }

            .col-md-pull-1 {
                right: 8.333333333333332%;
            }

            .col-md-pull-0 {
                right: 0;
            }

            .col-md-push-12 {
                left: 100%;
            }

            .col-md-push-11 {
                left: 91.66666666666666%;
            }

            .col-md-push-10 {
                left: 83.33333333333334%;
            }

            .col-md-push-9 {
                left: 75%;
            }

            .col-md-push-8 {
                left: 66.66666666666666%;
            }

            .col-md-push-7 {
                left: 58.333333333333336%;
            }

            .col-md-push-6 {
                left: 50%;
            }

            .col-md-push-5 {
                left: 41.66666666666667%;
            }

            .col-md-push-4 {
                left: 33.33333333333333%;
            }

            .col-md-push-3 {
                left: 25%;
            }

            .col-md-push-2 {
                left: 16.666666666666664%;
            }

            .col-md-push-1 {
                left: 8.333333333333332%;
            }

            .col-md-push-0 {
                left: 0;
            }

            .col-md-offset-12 {
                margin-left: 100%;
            }

            .col-md-offset-11 {
                margin-left: 91.66666666666666%;
            }

            .col-md-offset-10 {
                margin-left: 83.33333333333334%;
            }

            .col-md-offset-9 {
                margin-left: 75%;
            }

            .col-md-offset-8 {
                margin-left: 66.66666666666666%;
            }

            .col-md-offset-7 {
                margin-left: 58.333333333333336%;
            }

            .col-md-offset-6 {
                margin-left: 50%;
            }

            .col-md-offset-5 {
                margin-left: 41.66666666666667%;
            }

            .col-md-offset-4 {
                margin-left: 33.33333333333333%;
            }

            .col-md-offset-3 {
                margin-left: 25%;
            }

            .col-md-offset-2 {
                margin-left: 16.666666666666664%;
            }

            .col-md-offset-1 {
                margin-left: 8.333333333333332%;
            }

            .col-md-offset-0 {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="fixed-header">

<!--start-->
<div class="container container-fixed-lg">

    <div class="panel panel-default">
        <div class="panel-body">
            <div class="invoice padding-50 sm-padding-10">
                <div>
                    <div class="pull-left">
                        <!--logo negozio-->
                        <img width="235" height="47" alt="" class="invoice-logo"
                             data-src-retina=' . $logo . ' data-src=' . $logo . ' src=' . $logo . '>
                        <!--indirizzo negozio-->
                        <br><br>
                        <address class="m-t-10"><b>' . $intestation . '
                                <br>' . $intestation2 . '</b>
                            <br>' . $address . '
                            <br>' . $address2 . '
                            <br>' . $iva . '
                            <br>' . $tel . '
                            <br>' . $email . '
                        </address>
                        <br>
                        <div>
                            <div class="pull-left font-montserrat all-caps small">
                                <strong>' . $invoiceTypeText . '</strong>  ' . $invoice->invoiceNumber . "/" . $invoiceType . '<strong> del </strong>' . $invoiceDate->format('d-m-Y') . '
                            </div>

                        </div>
                        <br>
                        <div>
                            <div class="pull-left font-montserrat small"><strong>';

        if ($changelanguage != 1) {
            $referOrder = 'Rif. ordine N. ';
        } else {
            $referOrder = 'Order Reference N:';
        }
        $invoiceText .= $referOrder;
        $invoiceText .= '</strong>';
        $date = new DateTime($order->orderDate);
        if ($changelanguage != 1) {
            $refertOrderIdandDate = '  ' . $invoice->orderId . ' del ' . $date->format('d-m-Y');
        } else {
            $refertOrderIdandDate = '  ' . $invoice->orderId . ' date ' . $date->format('Y-d-m');
        };
        $invoiceText .= $refertOrderIdandDate . '</div>
                        </div>
                        <div><br>
                            <div class="pull-left font-montserrat small"><strong>';
        if ($changelanguage != 1) {
            $invoiceText .= 'Metodo di pagamento';
        } else {
            $invoiceText .= 'Payment Method';
        }
        $invoiceText .= '</strong>';
        $invoiceText .= ' ' . $order->orderPaymentMethod->name . '</div>

                        </div>
                    </div>
                    <div class="pull-right sm-m-t-0">
                        <h2 class="font-montserrat all-caps hint-text"><?php echo $invoiceHeaderText; ?></h2>

                        <div class="col-md-12 col-sm-height sm-padding-20">
                            <p class="small no-margin">';
        if ($changelanguage != 1) {
            $invoiceText .= 'Intestata a';
        } else {
            $invoiceText .= 'Invoice Address';
        }
        $invoiceText .= '</p>';
        $invoiceText .= '<h5 class="semi-bold m-t-0 no-margin">' . $userAddress->surname . ' ' . $userAddress->name . '</h5>';
        $invoiceText .= '<address>';
        $invoiceText .= '<strong>';
        if (!empty($userAddress->company)) {
            $invoiceText .= $userAddress->company . '<br>';
        } else {
            $invoiceText .= '<br>';
        }
        $invoiceText .= $userAddress->address;
        $invoiceText .= '<br>' . $userAddress->postcode . ' ' . $userAddress->city . ' (' . $userAddress->province . ')';
        $invoiceText .= '<br>' . $userAddress->country->name;
        if ($changelanguage != 1) {
            $transfiscalcode = 'C.FISC. o P.IVA: ';
        } else {
            $transfiscalcode = 'VAT';
        }
        $invoiceText .= '<br>';
        if (!is_null($order->user->userDetails->fiscalCode)) {
            $invoiceText .= $transfiscalcode . $order->user->userDetails->fiscalCode;
        }

        $invoiceText .= '</strong>';
        $invoiceText .= '</address>';
        $invoiceText .= '<div class="clearfix"></div><br><p class="small no-margin">';
        if ($changelanguage != 1) {
            $invoiceText .= 'Indirizzo di Spedizione';
        } else {
            $invoiceText .= 'Shipping Address';
        }

        $invoiceText .= '</p><address>';
        $invoiceText .= '<strong>' . $userShipping->surname . ' ' . $userShipping->name;
        $invoiceText .= (!empty($userShipping->company)) ? '<br>' . $userShipping->company : null;
        $invoiceText .= '<br>' . $userShipping->address;
        $invoiceText .= '<br>' . $userShipping->postcode . ' ' . $userShipping->city . ' (' . $userShipping->province . ')';
        $invoiceText .= '<br>' . $userShipping->country->name . '</strong>';
        $invoiceText .= '</address>';
        $invoiceText .= '</div>';
        $invoiceText .= '</div>';
        $invoiceText .= '</div>';
        $invoiceText .= '<table class="table invoice-table m-t-0">';
        $invoiceText .= '<thead>
                    <!--tabella prodotti-->
                    <tr>';
        $invoiceText .= '<th class="small">';
        if ($changelanguage != 1) {
            $invoiceText .= 'Descrizione Prodotto';
        } else {
            $invoiceText .= 'Description';
        }
        $invoiceText .= '</th>';
        $invoiceText .= '<th class="text-center small">';
        if ($changelanguage != 1) {
            $invoiceText .= 'Taglia';

        } else {
            $invoiceText .= 'Size';
        }
        $invoiceText .= '</th>';
        $invoiceText .= '<th></th>';
        $invoiceText .= '<th class="text-center small">';
        if ($changelanguage != 1) {
            $invoiceText .= 'Importo';
        } else {
            $invoiceText .= 'Amount';
        }
        $invoiceText .= '</th>';

        $invoiceText .= '</tr></thead><tbody>';
        $tot = 0;
        foreach ($order->orderLine as $orderLine) {
            $invoiceText .= '<tr>';
            $invoiceText .= '<td class="">';

            $productSku = \bamboo\domain\entities\CProductSku::defrost($orderLine->frozenProduct);

            $productNameTranslation = $productRepo->findOneBy(['productId' => $productSku->productId,'productVariantId' => $productSku->productVariantId,'langId' => '1']);
            $invoiceText .= (($productNameTranslation) ? $productNameTranslation->name : '') . ($orderLine->warehouseShelfPosition ? ' / ' . $orderLine->warehouseShelfPosition->printPosition() : '') . '<br />' . $productSku->product->productBrand->name . ' - ' . $productSku->productId . '-' . $productSku->productVariantId;
            $invoiceText .= '</td>';
            $invoiceText .= '<td class="text-center">' . $productSku->getPublicSize()->name;
            $invoiceText .= '<td></td>';
            $invoiceText .= '</td>';
            $invoiceText .= '<td class="text-center">';
            $tot += $orderLine->activePrice;
            $invoiceText .= number_format($orderLine->activePrice) . ' &euro;' . '</td></tr>';

        }
        $invoiceText .= '</tbody><br><tr class="text-left font-montserrat small">
                        <td style="border: 0px"></td>
                        <td style="border: 0px"></td>
                        <td style="border: 0px">
                            <strong>';
        if ($changelanguage != 1) {
            $invoiceText .= 'Totale della Merce';
        } else {
            $invoiceText .= 'Total Amount ';
        }
        $invoiceText .= '</strong></td>
                        <td style="border: 0px"
                            class="text-center">' . number_format($tot) . ' &euro;' . '</td>
                    </tr>';
        $discount = $order->couponDiscount + $order->userDiscount;
        ($changelanguage != 1) ? $transdiscount = 'Sconto' : $transdiscount = 'Discount';
        ($changelanguage != 1) ? $transmethodpayment = 'Modifica di pagamento' : $transmethodpayment = 'Transaction Discount';
        ($changelanguage != 1) ? $transdeliveryprice = 'Spese di Spedizione' : $transdeliveryprice = 'Shipping Cost';
        $invoiceText .= ((!is_null($discount)) && ($discount != 0)) ? '<tr class="text-left font-montserrat small">
                            <td style="border: 0px"></td>
                            <td style="border: 0px"></td>
                            <td style="border: 0px">' . $transdiscount . '<strong></strong></td>
                            <td style="border: 0px" class="text-center">' . number_format($discount) . ' &euro; </td></tr>' : null;
        $invoiceText .= ((!is_null($order->paymentModifier)) && ($order->paymentModifier != 0)) ? '<tr class="text-left font-montserrat small">
                            <td style="border: 0px"></td>
                            <td style="border: 0px"></td><td style="border: 0px"><strong>' . $transmethodpayment . '</strong></td>
                            <td style="border: 0px" class="text-center">' . number_format($order->paymentModifier) . ' &euro; </td></tr>' : null;
        $invoiceText .= '<tr class="text-left font-montserrat small">
                        <td style="border: 0px"></td>
                        <td style="border: 0px"></td>
                        <td class="separate"><strong>' . $transdeliveryprice . '</strong></td>
                        <td class="separate text-center">' . number_format($order->shippingPrice) . ' &euro;</td>
                    </tr>
                    <tr style="border: 0px" class="text-left font-montserrat small hint-text">
                        <td class="text-left" width="30%">';

        if ($invoiceType == 'P') {
            if ($changelanguage != 1) {
                $invoiceText .= 'Imponibile<br>';
            } else {
                $invoiceText .= 'Net Amount<br>';
            }
            $imp = ($order->netTotal * 100) / 122;
            $invoiceText .= number_format($imp) . ' &euro;';
        } elseif ($invoiceType == "X") {

            $imp = ($order->netTotal * 100) / 122;

            $invoiceText .= '<br>';
        } else {
            $imp = ($order->netTotal * 100) / 122;
            $invoiceText .= '<br>';
        }

        $invoiceText .= '</td>
                        <td class="text-left" width="25%">';

        if ($invoiceTypeVat == 'NewP') {
            if ($changelanguage != 1) {
                $invoiceText .= 'IVA 22%<br>';
            } else {
                $invoiceText .= 'VAT 22%<br>';
            }
            $iva = $order->vat;
            $invoiceText .= number_format($iva) . ' &euro;';
        } elseif ($invoiceTypeVat == "NewX") {
            $invoiceText .= 'non imponibile ex art 8/A  D.P.R. n. 633/72';
            $iva = "0,00";
            $invoiceText .= '<br>';
        } else {
            $iva = $order->vat;
            $invoiceText .= '<br>';
        }


        $invoiceText .= '<br></td>';
        $invoiceText .= '<td class="semi-bold"><h4>' . $invoiceTotalDocumentText . '</h4></td>';
        $invoiceText .= '<td class="semi-bold text-center">
                            <h2>' . number_format($order->netTotal) . ' &euro; </h2></td>
                    </tr>

                </table>
            </div>
            <br>
            <br>
            <br>
            <br>
            <br>
            <div>
                <center><img alt="" class="invoice-thank" data-src-retina="/assets/img/invoicethankyou.jpg"
                             data-src="/assets/img/invoicethankyou.jpg" src="/assets/img/invoicethankyou.jpg">
                </center>
            </div>
            <br>
            <br>
        </div>
    </div>
</div><!--end-->';

        $invoiceText .= '<script type="application/javascript">
    $(document).ready(function () {

        Pace.on(\'done\', function () {

            setTimeout(function () {
                window.print();

                setTimeout(function () {
                    window.close();
                }, 1);

            }, 200);

        });
    });
</script>
</body>
</html>';
        return $invoiceText;

    }
}