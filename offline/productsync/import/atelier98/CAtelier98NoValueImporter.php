<?php

namespace bamboo\ecommerce\offline\productsync\import\atelier98;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOutOfBoundException;


/**
 * Class CAtelier98ModificatoImporter
 * @package bamboo\ecommerce\offline\productsync\import\atelier98
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CAtelier98NoValueImporter extends AAtelier98Importer
{
    /**
     * @param array $array
     * @throws BambooException
     */
    protected function readListini(array $array)
    {
        $dpsr = $this->app->dbAdapter->query('SELECT id, extId, price, `value`, salePrice FROM DirtyProduct WHERE shopId = ?', [$this->getShop()->id])->fetchAll();
        $dps = [];
        $dpsPrices = [];
        foreach ($dpsr as $dp) {
            $dps[$dp['id']] = $dp['extId'];
            $dpsPrices[$dp['id']] = md5(json_encode(['price' => $dp['price'], 'value' => $dp['value'], 'salePrice' => $dp['salePrice']]));
        }
        unset($dpsr);
        $affected = 0;
        $count = 0;
        $nomeListino = $this->config->fetch('miscellaneous', 'nome-listino');

        foreach ($array['LISTINI'] as $element) {
            try {
                $count++;
                if($count%1000 == 0) $this->report('readListini','Read Listini: '.$count);
                $listino = $element['LI_CODICE'];
                if ($listino == $nomeListino) {
                    $ext = $element['LI_ID_VARIANTI'];
                    if ($did = array_search($ext, $dps)) {
                        $upd = [];
                        $upd['price'] = $element['LI_PREZZO_VEN'];
                        $upd['value'] = $element['LI_PREZZO_VEN'];
                        $upd['salePrice'] = $element['LI_PREZZO_SAL'];
                        if($dpsPrices[$did] == md5(json_encode($upd))) continue;
                        $rows = $this->app->dbAdapter->update('DirtyProduct', $upd, ['id' => $did]);
                        if ($rows == 1) {
                            $affected++;
                        } elseif ($rows > 1) {
                            throw new BambooException('More than 1 row updated, ERROR!', json_encode($element));
                        }
                    } else {
                        throw new BambooOutOfBoundException('DirtyProduct non found while working for listino ' . $listino . ' ext: ' . $ext);
                    }
                }
            } catch (BambooOutOfBoundException $e) {
                $this->error('readListini', 'errore durante la lettura', $e);
            }
        }
        $this->report('readListini', 'Updated ' . $affected . ' rows over ' . $count);
    }
}