<?php
namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CInvoice;

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
    public function insertNewInvoiceDocument(int $orderId, $file, string $type) : bool{

        /** @var CInvoiceDocument $invDoc */
        $invDoc = $this->getEmptyEntity();

        try {
            $invDoc->orderId = $orderId;
            $invDoc->bin = file_get_contents($file["tmp_name"]);
            $invDoc->fileName = $file["name"];
            $invDoc->type = $type;
            $invDoc->smartInsert();
        } catch (\Throwable $e){
            return false;
        }

        return true;
    }
}