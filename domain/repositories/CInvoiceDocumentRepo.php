<?php
namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\exceptions\BambooException;
use bamboo\domain\entities\CInvoiceDocument;
use PDO;
use PDOException;

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
class CInvoiceDocumentRepo extends ARepo
{
    public function insertNewInvoiceDocument(int $orderId, $file, string $type) : bool{

        /** @var CInvoiceDocument $invDoc */
        $invDoc = $this->getEmptyEntity();

        try {
            $invDoc->orderId = $orderId;
            $invDoc->bin = file_get_contents($file["tmp_name"]);
            $invDoc->fileName = $file["name"];
            $invDoc->type = $type;
            $invDoc->smartInsert();
            $orderRepo=\Monkey::app()->repoFactory->create('Order')->findOneBy(['Order'=>$orderId]);
            $remoteShopSellerId=$orderRepo->remoteShopSellerId;
            $remoteOrderSellerId=$orderRepo->remoteOrderSellerId;
            $findShopId = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $remoteShopSellerId]);
            if ($findShopId->hasEcommerce == '1' && $findShopId->id != '44') {
                /* find  orderId*/

                $db_host = $findShopId->dbHost;
                $db_name = $findShopId->dbName;
                $db_user = $findShopId->dbUsername;
                $db_pass = $findShopId->dbPassword;
                try {

                    $db_con = new PDO("mysql:host={$db_host};dbname={$db_name}",$db_user,$db_pass);
                    $db_con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                    $res = " connessione ok <br>";
                } catch (PDOException $e) {
                    throw new BambooException('fail to connect');

                }
                try {
                    $stmtInsertSellerInvoiceDocument = $db_con->prepare('INSERT INTO Invoice (orderId, bin, date,fileName,type) 
                                                                                    VALUES(
                                                                                         \'' . $remoteOrderSellerId . '\',
                                                                                         \'' . file_get_contents($file["tmp_name"]) . '\',
                                                                                         \'date_format(NOW(),\'%Y-%m-%d %H:%i:%s\')\',
                                                                                         \'' . $file["name"] . '\',
                                                                                         \'' . $type . '\'
                                                                                    ) ');
                    $stmtInsertSellerInvoiceDocument->execute();
                } catch (PDOException $e) {
                    $sql = 'INSERT INTO Invoice (orderId, bin, date,fileName,type) 
                                                                                    VALUES(
                                                                                         \'' . $remoteOrderSellerId . '\',
                                                                                         \'' . file_get_contents($file["tmp_name"]) . '\',
                                                                                         \'date_format(NOW(),\'%Y-%m-%d %H:%i:%s\')\',
                                                                                         \'' . $file["name"] . '\',
                                                                                         \'' . $type . '\'
                                                                                    ) ';
                    \Monkey::app()->applicationLog('CInvoiceDocumentRepo','error','invoicedocumentSellerRemote',$sql,'');
                }
            }
                $orderLines=\Monkey::app()->repoFactory->create('OrderLine')->findBy(['orderId'=>$orderId]);
                foreach($orderLines as $orderLine) {
                    if ($orderLine->remoteOrderSupplierId != null) {
                        $findShopSupplier = \Monkey::app()->repoFactory->create('Shop')->findOneBy(['id' => $orderLine->shopId]);
                        if ($findShopSupplier->hasEcommerce == '1' && $findShopSupplier->id != '44') {
                            /* find  orderId*/

                            $db_host1 = $findShopSupplier->dbHost;
                            $db_name1 = $findShopSupplier->dbName;
                            $db_user1 = $findShopSupplier->dbUsername;
                            $db_pass1 = $findShopSupplier->dbPassword;
                            try {

                                $db_con1 = new PDO("mysql:host={$db_host1};dbname={$db_name1}",$db_user1,$db_pass1);
                                $db_con1->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                                $res = " connessione ok <br>";
                            } catch (PDOException $e) {
                                throw new BambooException('fail to connect');

                            }

                            try {
                                $stmtInsertSupplierInvoiceDocument = $db_con1->prepare('INSERT INTO Invoice (orderId, bin, date,fileName,type) 
                                                                                    VALUES(
                                                                                         \'' . $orderLine->remoteOrderSupplierId . '\',
                                                                                         \'' . file_get_contents($file["tmp_name"]) . '\',
                                                                                         \'date_format(NOW(),\'%Y-%m-%d %H:%i:%s\')\',
                                                                                         \'' . $file["name"] . '\',
                                                                                         \'' . $type . '\'
                                                                                    ) ');
                                $stmtInsertSupplierInvoiceDocument->execute();
                            } catch (PDOException $e) {
                                $sql = 'INSERT INTO Invoice (orderId, bin, date,fileName,type) 
                                                                                    VALUES(
                                                                                         \'' . $orderLine->remoteOrderSupplierId . '\',
                                                                                         \'' . file_get_contents($file["tmp_name"]) . '\',
                                                                                         \'date_format(NOW(),\'%Y-%m-%d %H:%i:%s\')\',
                                                                                         \'' . $file["name"] . '\',
                                                                                         \'' . $type . '\'
                                                                                    ) ';
                                \Monkey::app()->applicationLog('CInvoiceDocumentRepo','error','invoicedocumentSupplierRemote',$sql,'');
                            }
                        }
                    }


                }


        } catch (\Throwable $e){
            return false;
        }

        return true;
    }
}