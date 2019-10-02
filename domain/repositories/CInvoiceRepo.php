<?php
namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CInvoice;
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
    public function createNewInvoiceToOrderParallel(int $orderId, int $remoteOrderSupplierId,int $remoteShopSupplierId): bool
    {

        $orderRepo = \Monkey ::app() -> repoFactory -> create('Order');
        $shopRepo = \Monkey ::app() -> repoFactory -> create('Shop');
        $addressBookRepo = \Monkey ::app() -> repoFactory -> create('AddressBook');

        $order = $orderRepo -> findOneBy(['id' => $orderId]);
        $remoteShopSellerId = $order -> remoteShopSellerId;


        // prendo l'intestazione
        $shopInvoices = $shopRepo -> findOneBy(['id' => 44]);
        $logo = $shopInvoices -> logo;
        $intestation = $shopInvoices -> intestation;
        $intestation2 = $shopInvoices -> intestation2;
        $address = $shopInvoices -> address;
        $address2 = $shopInvoices -> address2;
        $iva = $shopInvoices -> iva;
        $tel = $shopInvoices -> tel;
        $email = $shopInvoices -> email;
        /***sezionali******/
        $receipt = $shopInvoices -> receipt;
        $invoiceUe = $shopInvoices -> invoiceUe;
        $invoiceExtraUe = $shopInvoices -> invoiceExtraUe;
        $siteInvoiceChar = $shopInvoices -> siteInvoiceChar;


        $customerDataSeller = $shopRepo -> findOneBy(['id' => $remoteShopSellerId]);
        $userAddress = $addressBookRepo -> findOneBy(['id' => $customerDataSeller -> billingAddressBookId]);
        $extraUe = $userAddress -> countryId;
        $countryRepo = \Monkey ::app() -> repoFactory -> create('Country');
        $findIsExtraUe = $countryRepo -> findOneBy(['id' => $extraUe]);
        $isExtraUe = $findIsExtraUe -> extraue;


        if ($extraUe != '110') {
            $changelanguage = "1";

        } else {
            $changelanguage = "0";
        }


        $hasInvoice = 1;
        $invoiceRepo = \Monkey ::app() -> repoFactory -> create('Invoice');
        $invoiceNew = $invoiceRepo -> getEmptyEntity();
        $siteChar = $siteInvoiceChar;
        if ($order -> invoice -> isEmpty()) {
            try {
                $invoiceNew -> orderId = $orderId;
                $today = new \DateTime();
                $invoiceNew -> invoiceYear = $today -> format('Y-m-d H:i:s');
                $year = (new \DateTime()) -> format('Y');
                $em = $this -> app -> entityManagerFactory -> create('Invoice');
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
                $userHasShop = \Monkey ::app() -> repoFactory -> create('UserHasShop') -> findOneBy(['shopId' => $customerDataSeller -> id]);
                $remoteUserSellerId = $userHasShop -> userId;

                $number = $em -> query("SELECT ifnull(MAX(invoiceNumber),0)+1 AS new
                                      FROM Invoice
                                      WHERE
                                      Invoice.invoiceYear = ? AND
                                      Invoice.invoiceType='" . $invoiceType . "' AND
                                      Invoice.invoiceShopId='" . $shopInvoices -> id . "' AND
                                      Invoice.invoiceSiteChar= ?", [$year, $siteChar]) -> fetchAll()[0]['new'];

                $invoiceNew -> invoiceShopId = $shopInvoices -> id;
                $invoiceNew -> invoiceNumber = $number;
                $invoiceNew -> invoiceSiteChar = $siteChar;
                $invoiceNew -> invoiceType = $invoiceType;
                $invoiceNew -> invoiceDate = $today -> format('Y-m-d H:i:s');
                $todayInvoice = $today -> format('d/m/Y');

                $invoiceRepo -> insert($invoiceNew);
                $sectional = $number . '/' . $invoiceType;
                $documentRepo = \Monkey ::app() -> repoFactory -> create('Document');
                // codice per inserire all'interno della cartella document
                $checkIfDocumentExist = $documentRepo -> findOneBy(['number' => $number, 'year' => $year]);
                if ($checkIfDocumentExist == null) {
                    $insertDocument = $documentRepo -> getEmptyEntity();
                    $insertDocument -> userId = $remoteUserSellerId;
                    $insertDocument -> shopRecipientId = $customerDataSeller -> id;
                    $insertDocument -> number = $sectional;
                    $insertDocument -> date = $order -> orderDate;
                    $insertDocument -> invoiceTypeId = $documentType;
                    $insertDocument -> paydAmount = $order -> paidAmount;
                    $insertDocument -> paymentExpectedDate = $order -> paymentDate;
                    $insertDocument -> note = $order -> note;
                    $insertDocument -> creationDate = $order -> orderDate;
                    $insertDocument -> totalWithVat = $order -> netTotal;
                    $insertDocument -> year = $year;
                    $insertDocument -> insert();
                }
                $remoteShopSupplier=\Monkey::app()->repoFactory->create('Shop')->findOneBy(['id'=>$remoteShopSupplierId]);
                /*** dati db esterno ***/
                $db_host = $remoteShopSupplier -> dbHost;
                $db_name = $remoteShopSupplier -> dbName;
                $db_user = $remoteShopSupplier -> dbUsername;
                $db_pass = $remoteShopSupplier -> dbPassword;

                $db_con = new PDO("mysql:host={$db_host};dbname={$db_name}", $db_user, $db_pass);
                $db_con -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmtCheckDocumentExist = $db_con -> prepare("SELECT count(*) AS counterDocument from Document WHERE `number`='" . $sectional . "'");
                $stmtCheckDocumentExist -> execute();
                while ($rowCheckDocumentExist = $stmtCheckDocumentExist -> fetch(PDO::FETCH_ASSOC)) {
                    if ($rowCheckDocumentExist['counterDocument'] == 1) {
                        $doQuery = 1;
                    } else {
                        $doQuery = 2;
                    }
                }
                if ($doQuery == '2') {
                    $remoteAddress = $userAddressRepo -> findOneBy(['id' => $order -> billingAddressId]);
                    if ($remoteAddress != null) {
                        $remoteUserAddressId = $remoteAddress -> remoteUserId;
                    } else {
                        $remoteUserAddressId = '';
                    }
                    $documentRemoteUpdate = $documentRepo -> findOneBy(['userId' => $remoteUserSellerId, 'number' => $sectional]);
                    $insertRemoteDocument = $db_con -> prepare("INSERT INTO Document (
                                                                     userId,
                                                                     userAddressRecipientId,
                                                                     shopRecipientId,
                                                                     `number`,
                                                                     `date`,
                                                                     invoiceTypeId,
                                                                     paymentDate,
                                                                     paydAmount,
                                                                     paymentExpectedDate,
                                                                     note,
                                                                     creationDate,
                                                                     carrierId,
                                                                     totalWithVat,
                                                                     year )
                                                                    VALUES
                                                                    ( 
                                                                     '" . $remoteUserAddressId . "',
                                                                      '" . $documentRemoteUpdate -> userAddressRecipientId . "',
                                                                      '" . '44' . "',
                                                                      '" . $documentRemoteUpdate -> number . "',
                                                                      '" . $documentRemoteUpdate -> date . "',
                                                                      '" . $documentRemoteUpdate -> invoiceTypeId . "',
                                                                      '" . $documentRemoteUpdate -> paymentDate . "',
                                                                      '" . $documentRemoteUpdate -> paydAmount . "',
                                                                      '" . $documentRemoteUpdate -> paymentExpectedDate . "',
                                                                      '" . $documentRemoteUpdate -> note . "',
                                                                      '" . $documentRemoteUpdate -> creationDate . "',
                                                                      '" . $documentRemoteUpdate -> carrierId . "',
                                                                      '" . $documentRemoteUpdate -> totalWithVat . "',
                                                                      '" . $documentRemoteUpdate -> year . "'           
                                                                                )");
                }
                $stmtInvoiceExist = $db_con -> prepare("SELECT 
                                     count(*) AS counterInvoice from Invoice where orderId =" . $order -> remoteOrderSellerId);
                $stmtInvoiceExist -> execute();
                while ($rowInvoiceExist = $stmtInvoiceExist -> fetch(PDO::FETCH_ASSOC)) {

                    if ($rowInvoiceExist['counterInvoice'] == '1') {
                        $doQuery = '1';
                    } else {
                        $doQuery = '2';
                    }
                }
                if ($doQuery == '2') {
                    $insertRemoteInvoice = $invoiceRepo -> findOneBy(['orderId' => $order -> id]);
                    $stmtInvoiceInsert = $db_con -> prepare("INSERT INTO  Invoice 
                                                                               (
                                                                               orderId,
                                                                               invoiceYear,
                                                                               invoiceType,
                                                                               invoiceSiteChar,
                                                                               invoiceNumber,
                                                                               invoiceDate,
                                                                               invoiceText,
                                                                               creationDate)
                                                                               VALUES(
                                                                               '" . $order -> remoteOrderSellerId . "',
                                                                                '" . $insertRemoteInvoice -> invoiceYear . "',
                                                                                '" . $insertRemoteInvoice -> invoiceType . "',
                                                                                '" . $insertRemoteInvoice -> invoiceSiteChar . "',
                                                                                '" . $insertRemoteInvoice -> invoiceNumber . "',
                                                                                '" . $insertRemoteInvoice -> invoiceDate . "',
                                                                                
                                                                                '',
                                                                                '" . $insertRemoteInvoice -> creationDate . "'
                                                                               )
                                                                               ");
                    $stmtInvoiceInsert -> execute();


                }


            } catch (\Throwable $e) {
                throw $e;
                $this -> app -> router -> response() -> raiseProcessingError();
                $this -> app -> router -> response() -> sendHeaders();
            }
        }

        foreach ($order -> invoice as $invoice) {
            if (is_null($invoice -> invoiceText)) {
                $userAddress = $userAddressRepo -> findOneBy(['id' => $order -> billingAddressId]);
                if (!is_null($order -> shipmentAddressId)) {
                    $userShipping = $userAddressRepo -> findOneBy(['id' => $order -> shipmentAddressId]);
                } else {
                    $userShipping = $userAddress;
                }


                $productRepo = \Monkey ::app() -> repoFactory -> create('ProductNameTranslation');
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


            }


            return true;
        }
    }
}