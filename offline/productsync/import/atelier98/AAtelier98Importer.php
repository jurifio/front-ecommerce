<?php

namespace bamboo\ecommerce\offline\productsync\import\atelier98;

use bamboo\core\base\CFTPClient;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooFileException;
use bamboo\core\exceptions\BambooFTPClientException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\exceptions\BambooShouldNeverHappenException;
use bamboo\core\utils\slugify\CSlugify;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;

class AAtelier98Importer extends ABluesealProductImporter
{

    public function fetchFtpFiles()
    {
        if ($this->config->fetch('filesConfig', 'mode') && $this->config->fetch('filesConfig', 'mode') == 'differential') $this->fetchFTPFilesDifferential();
        else $this->fetchFTPFilesFull();
    }

    /**
     * @deprecated
     */
    public function fetchFTPFilesDifferential()
    {
        $localDir = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->getShop()->name;

        $istructions = $this->config->fetch('filesConfig', 'istructions');

        $ftp = new CFTPClient($this->app, $this->config->fetchAll('filesFtpConfig'));
        $remoteDir = $istructions['remoteFolder'];
        $regex = $istructions['regex'];
        $ftp->changeDir($remoteDir);
        $list = $ftp->nList();

        $localDir .= '/' . $istructions['folder'];

        $workedItems = $this->app->dbAdapter->query('SELECT name FROM DirtyFile WHERE shopId = ?', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);

        foreach ($list as $one) {
            if (array_search($one, $workedItems) === false) {
                if (preg_match($regex, $one) == 1) {
                    try {
                        $this->report('fetchFTPFiles', 'Getting file:' . $one);
                        if ($ftp->get($localDir . '/' . $one, $one, false)) {
                            $this->app->dbAdapter->insert('DirtyFile', ['shopId' => $this->getShop()->id, 'name' => $one]);
                        }
                    } catch (\Throwable $e) {
                        $this->error('fetchFTPFiles', 'Could not get file', $one);
                    }
                }
            }
        }

        $this->fetchLocalFiles();
    }

    public function fetchLocalFiles()
    {
        $istructions = $this->config->fetch('filesConfig', 'istructions');
        $root = $istructions['root'] == '__default__' ? $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->getShop()->name : $istructions['root'];
        $folder = $istructions['folder'];

        $globs = ['Prodott*.xml', 'Listin*.xml', 'Disponibilit*.xml', 'Riferiment*.xml'];

        foreach ($globs as $glob) {
            $files = glob($root . '/' . $folder . '/' . $glob);
            if (empty($files)) {
                $this->report("fetchFiles", "Nessun file trovato per " . $glob, null);
            } else {
                usort($files, function ($a, $b) {
                    return filemtime($a) > filemtime($b);
                });
                array_push($this->mainFilenames, ...$files);
            }
        }

        $this->report("fetchFiles", "Files usati: " . count($this->mainFilenames), implode(', ', $this->mainFilenames));
    }

    /**
     *
     */
    public function fetchFTPFilesFull()
    {
        $localDir = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->getShop()->name;

        $istructions = $this->config->fetch('filesConfig', 'istructions');

        $ftp = new CFTPClient($this->app, $this->config->fetchAll('filesFtpConfig'));
        $remoteDir = $istructions['remoteFolder'];
        $regex = $istructions['regex'];
        $ftp->changeDir($remoteDir);
        $list = $ftp->nList();

        $localDir .= '/' . $istructions['folder'];

        foreach ($list as $one) {
            if (preg_match($regex, $one) == 1) {
                try {
                    $this->report('fetchFTPFiles', 'Getting file:' . $one);
                    $ftp->get($localDir . '/' . $one, $one, false,400000);
                } catch (\Throwable $e) {
                    $this->error('fetchFTPFiles', 'Could not get file', $one);
                }
            }
        }

        $this->fetchLocalFiles();
    }

    /**
     * @param $file
     * @return bool
     */
    public function readFile($file)
    {
        return true;
    }

    /**
     * @param $file
     * @throws BambooException
     * @throws BambooFileException
     * @throws BambooOutOfBoundException
     */
    public function processFile($file)
    {
        $baseName = pathinfo($file, PATHINFO_BASENAME);

        try {
            $dirtyFile = $this->app->dbAdapter->select('DirtyFile', ['name' => $baseName, 'shopId' => $this->getShop()->id])->fetchAll()[0];
        } catch (\Throwable $e) {
        }
        try {

            $this->report('processFile', 'translating to json: ' . $baseName, $file);
            $xml = simplexml_load_file($file);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);
        } catch (\Throwable $e) {
            $this->error('processFile', 'error while translating to json', $e);
            throw new $e;
        }


        //$dom = new \DOMDocument();
        //$dom->loadXML(file_get_contents($file));

        $fileName = pathinfo($file, PATHINFO_FILENAME);
        $time = microtime(true);
        switch (explode('_', $fileName)[0]) {
            case 'Prodotti':
                $this->report('processFile', 'going to readProduct on ' . $baseName);
                $return = $this->readProducts($array);
                $this->report('processFile', 'done readProduct on' . $baseName . ' in ' . (microtime(true) - $time));
                break;
            case 'Listini':
                $this->report('processFile', 'going to readListini on ' . $baseName);
                $return = $this->readListini($array);
                $this->report('processFile', 'done readListini on' . $baseName . ' in ' . (microtime(true) - $time));
                break;
            case 'Riferimenti':
                $this->report('processFile', 'going to readFoto on ' . $baseName);
                $return = $this->readFoto($array);
                $this->report('processFile', 'done readFoto on' . $baseName . ' in ' . (microtime(true) - $time));
                break;
            case 'Disponibilita':
                $this->report('processFile', 'going to readDisponibilita on ' . $baseName);
                $return = $this->readDisponibilita($array);
                $this->report('processFile', 'done readDisponibilita on' . $baseName . ' in ' . (microtime(true) - $time));
                break;
            default:
                throw new BambooOutOfBoundException('Unknown file %s', [$file]);
        }
        try {
            $this->app->dbAdapter->update('DirtyFile', ['worked' => 1, 'elaborationDate' => date("Y-m-d H:i:s", time())], ['id' => $dirtyFile['id'], 'shopId' => $this->getShop()->id]);
        } catch (\Throwable $e) {
        }


        return $return;
    }

    /**
     * @param array $array
     * @throws \Throwable
     */
    protected function readProducts(array $array)
    {
        $details = ['TESSUTO', 'MOTIVO', 'PAESE_PRODUZIONE', 'COMPOSIZIONE_DETTAGLIATA', 'COMPOSIZIONE_DETTAGLIATA2', 'SIZE_AND_FIT'];

        $rebuild = false;
        if ($rebuild) {
            $dps = $this->app->dbAdapter->query('SELECT id,extId,brand,itemno,var,shopId FROM DirtyProduct WHERE shopId = ?', [$this->getShop()->id], true)->fetchAll();
            foreach ($dps as $dpk) {
                $dpid = $dpk['id'];
                unset($dpk['id']);
                \Monkey::app()->dbAdapter->update('DirtyProduct', ['keysChecksum' => md5(json_encode($dpk))], ['id' => $dpid]);
            }
        }

        $keysChecksum = $this->app->dbAdapter->query('SELECT keysChecksum, id FROM DirtyProduct WHERE shopId = ?', [$this->getShop()->id])->fetchAll();
        $keysChecksums = [];
        foreach ($keysChecksum as $val) {
            $keysChecksums[$val['keysChecksum']] = $val['id'];
        }
        unset($keysChecksum);

        $checksums = $this->app->dbAdapter->query("SELECT ifnull(checksum,'') FROM DirtyProduct WHERE shopId = ?", [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
        $checksums = array_flip($checksums);


        $counter = 0;
        $this->report('readProducts', 'Products to read: ' . count($array['Prodotti']));
        foreach ($array['Prodotti'] as $element) {
            try {
                foreach ($element as $key => $value) if (is_array($value)) $element[$key] = "";
                $counter++;
                if ($counter % 250 == 0) $this->report('readProducts', 'Read Products: ' . $counter);
                $dp = [];
                $dp['checksum'] = md5(json_encode($element));
                if (array_key_exists($dp['checksum'], $checksums)) continue;
                $dpk = [];
                $dpk['extId'] = $element['ID_ARTICOLO'];
                $dpk['brand'] = $element['BRAND'];
                $dpk['itemno'] = $element['CODICE_MODELLO'];
                $dpk['var'] = $element['CODICE_VARIANTE'];
                $dpk['shopId'] = $this->getShop()->id;
                $dp['keysChecksum'] = md5(json_encode($dpk));
                if ($element['CANCELLATO'] == 1) {
                    $dp['dirtyStatus'] = 'C';
                }

                if (array_key_exists($dp['keysChecksum'], $keysChecksums)) {
                    //UPDATE
                    $dpId = $keysChecksums[$dp['keysChecksum']];


                    //UPDATE DirtyProduct
                    $dp = $dp + $dpk;
                    unset($dp['shopId']);
                    $this->app->dbAdapter->update('DirtyProduct', $dp, ['id' => $dpId, 'shopId' => $this->getShop()->id]);

                    //UPDATE DirtyProductExtend
                    $dpe = $this->fillProductExtras($element);
                    unset($dpe['shopId']);
                    $this->app->dbAdapter->update('DirtyProductExtend', $dpe, ['dirtyProductId' => $dpId, 'shopId' => $this->getShop()->id]);

                    //UPDATE DirtyDetail
                    foreach ($details as $detailName) {
                        if (is_array($element[$detailName])) continue;
                        $detailValue = trim($element[$detailName]);
                        if (!empty($detailValue)) {
                            $this->app->dbAdapter->update('DirtyDetail',
                                ['content' => $detailValue],
                                ['dirtyProductId' => $dpId, 'label' => $detailName]);
                        }
                    }

                } else {
                    //INSERT DirtyProduct
                    $dp['dirtyStatus'] = 'F';
                    $dp = $dp + $dpk;
                    \Monkey::app()->repoFactory->beginTransaction();
                    $dpId = $this->app->dbAdapter->insert('DirtyProduct', $dp);

                    //INSERT DirtyProductExtend
                    $dpe = $this->fillProductExtras($element, $dpId);
                    $this->app->dbAdapter->insert('DirtyProductExtend', $dpe);

                    //INSERT DirtyDetail
                    foreach ($details as $detailName) {
                        $detailValue = trim($element[$detailName]);
                        if (!empty($detailValue)) {
                            $this->app->dbAdapter->insert('DirtyDetail',
                                ['content' => $detailValue, 'dirtyProductId' => $dpId, 'label' => $detailName]);
                        }
                    }
                    \Monkey::app()->repoFactory->commit();
                }
            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                $this->error('readProducts', 'Error while reading product', $element);
                throw $e;
            }
        }
        $this->report('readProduct', 'Letti :' . $counter . ' Prodotti');
    }

    /**
     * @param $element
     * @param null $dirtyProductId
     * @return array
     */
    protected function fillProductExtras($element, $dirtyProductId = null)
    {
        $dpe = [];
        if (!is_null($dirtyProductId)) {
            $dpe['dirtyProductId'] = $dirtyProductId;
        }
        $dpe['shopId'] = $this->getShop()->id;
        $dpe['audience'] = $element['SETTORE'];
        $dpe['season'] = $element['SIGLA_STAGIONE'];
        $dpe['cat1'] = $element['CATEGORIA'];
        $dpe['cat2'] = $element['GRUPPO_SUPER'];
        $dpe['cat3'] = $element['GRUPPO'];

        $dpe['name'] = $element['DESCRIZIONE_SPECIALE'];
        if (empty($dpe['name'])) {
            $dpe['name'] = $element['DESCRIZIONE_MODELLO'];
        }

        $dpe['description'] = $element['DESCRIZIONE'];
        if (empty($dpe['description'])) {
            $dpe['description'] = $element['DESCRIZIONE_BREVE'];
        }

        $dpe['generalColor'] = $element['COLORE_VERO'] . ' ' . $element['COLORE_SUPER'];
        $dpe['colorDescription'] = $element['COLORE'];

        $dpe['sizeGroup'] = $element['TIPOLOGIA_TAGLIE'];

        return $dpe;
    }

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
            $dpsPrices[$dp['id']] = md5(json_encode(['price' => $dp['price'], 'value' => $dp['price'], 'salePrice' => $dp['salePrice']]));
        }
        unset($dpsr);
        $affected = 0;
        $count = 0;
        $nomeListino = $this->config->fetch('miscellaneous', 'nome-listino');

        foreach ($array['LISTINI'] as $element) {
            try {
                $count++;
                if ($count % 1000 == 0) $this->report('readListini', 'Read Listini: ' . $count);
                $listino = $element['LI_CODICE'];
                if ($listino == $nomeListino) {
                    $ext = $element['LI_ID_VARIANTI'];
                    if ($did = array_search($ext, $dps)) {
                        $upd = [];
                        $upd['price'] = $element['LI_PREZZO_VEN'];
                        $upd['value'] = $element['LI_PREZZO_ACQ'];
                        $upd['salePrice'] = $element['LI_PREZZO_SAL'];
                        if ($dpsPrices[$did] == md5(json_encode($upd))) continue;
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

    /**
     * @param array $array
     */
    protected function readFoto(array $array)
    {
        $dpsr = $this->app->dbAdapter->query('SELECT id, extId FROM DirtyProduct WHERE shopId = ?', [$this->getShop()->id])->fetchAll();
        $dps = [];
        foreach ($dpsr as $dp) {
            $dps[$dp['id']] = $dp['extId'];
        }
        $dpsr = $this->app->dbAdapter->query('SELECT url FROM DirtyPhoto WHERE shopId = ?', [$this->getShop()->id])->fetchAll();
        $dfs = [];
        foreach ($dpsr as $dp) {
            $dfs[] = $dp['url'];
        }
        $affected = 0;
        $count = 0;
        foreach ($array['Riferimenti'] as $element) {
            $count++;
            $upd = [];
            $upd['url'] = null;
            try {
                if ($count % 1000 == 0) $this->report('readFoto', 'Read Photos: ' . $count);
                $ext = $element['RF_RECORD_ID'];
                $upd['url'] = $element['RIFERIMENTO'];
                if (array_search($upd['url'], $dfs)) {
                    continue;
                } elseif (($did = array_search($ext, $dps))) {
                    //$found = preg_match('#^[\w\s\d]+-{3}[\w\s\d&]+-{3}[\w\s\d]+_([\d]{1,2})(?:_P|D|S)?[0]?#u', $upd['url'], $position);
                    $position = $this->app->dbAdapter->query('SELECT max(ifnull(position,0))+1 FROM DirtyPhoto WHERE dirtyProductId = ?', [$did])->fetchAll(\PDO::FETCH_COLUMN, 0);
                    $upd['position'] = $position[0];

                    $upd['worked'] = 0;
                    $rows = $this->app->dbAdapter->insert('DirtyPhoto', $upd + ['dirtyProductId' => $did, 'shopId' => $this->getShop()->id]);
                    if (is_int($rows)) {
                        $affected++;
                    } else {
                        throw new BambooException('More than 1 row updated, ERROR!', json_encode($element));
                    }

                } else {
                    throw new BambooOutOfBoundException('DirtyProduct non found while working for riferimenti: ' . $ext);
                }

            } catch (BambooFileException $e) {
                $this->warning('readPhoto', 'Could not find Photo Position for filename ' . $upd['url']);
            } catch (BambooOutOfBoundException $e) {
                $this->error('readPhoto', 'errore durante la lettura', $e);
            } catch (\Throwable $e) {
                $this->error('readPhoto', 'errore Generico durante la lettura del file', $e);
            }
        }
        $this->report('readFoto', 'Inserted ' . $affected . ' rows over ' . count($array['Riferimenti']));
    }

    /**
     * @param array $array
     */
    protected function readDisponibilita(array $array)
    {
        $rows = \Monkey::app()->dbAdapter->query('SELECT checksum, id FROM DirtySku WHERE shopId = ? AND qty > 0', [$this->getShop()->id])->fetchAll();
        $skusChecksums = [];
        foreach ($rows as $row) {
            $skusChecksums[$row['checksum']] = $row['id'];
        }

        $rows = \Monkey::app()->dbAdapter->query('SELECT extId, id FROM DirtyProduct WHERE shopId = ?', [$this->getShop()->id])->fetchAll();
        $dpIds = [];
        foreach ($rows as $row) $dpIds[$row['id']] = $row['extId'];
        unset($rows);

        $affected = 0;
        $cycled = 0;
        $seenSkus = [];
        $skusToUpdate = [];
        foreach ($array['Disponibilita'] as $element) {
            $cycled++;
            try {
                if ($cycled % 1000 == 0) $this->report('readDisponibilita', 'Read Disponibilita: ' . $cycled);
                if ($this->isDebug()) {
                    $this->debug('readDisponibilita', 'element', $element);
                }

                $dirtySku = [];

                $extId = $element['ID_ARTICOLO'];
                $dirtySku['dirtyProductId'] = array_search($extId, $dpIds);

                if (!$dirtySku['dirtyProductId']) {
                    throw new BambooOutOfBoundException('No Specific DirtyProduct Found inserting Sku');
                }

                $dirtySku['size'] = $element['MM_TAGLIA'];
                $dirtySku['qty'] = $element['ESI'];
                $dirtySku['shopId'] = $this->getShop()->id;
                $dirtySku['barcode'] = $element['BARCODEEAN'];
                $dirtySku['checksum'] = md5(json_encode($dirtySku));

                if (isset($skusChecksums[$dirtySku['checksum']])) {
                    $dirtySkuId = $skusChecksums[$dirtySku['checksum']];
                    /** SKU GIA' TROVATO, SOMMO LE QUANTITA' */
                    $this->debug('readDisponibilita', 'DirtySku già in memoria, siamo a posto cosi ' . $skusChecksums[$dirtySku['checksum']], $dirtySku);
                } else {
                    /** SKU DIVERSO, LO PESCO DAL DB */
                    $dirtySkuCopy = $dirtySku;
                    unset($dirtySkuCopy['qty']);
                    unset($dirtySkuCopy['checksum']);

                    $foundSkus = \Monkey::app()->dbAdapter->select('DirtySku', $dirtySkuCopy)->fetchAll();

                    if (count($foundSkus) == 1) {
                        $this->debug('readDisponibilita', 'DirtySku ritrovato, controllo da aggiornare');
                        $dirtySkuId = $foundSkus[0]['id'];
                        unset($dirtySku['checksum']);

                        if (isset($skusToUpdate[$dirtySkuId])) {
                            $this->debug('readDisponibilita', 'DirtySku già esistente sommo disponibilità');
                            $skusToUpdate[$dirtySkuId]['qty'] += $dirtySku['qty'];
                        } else {
                            $this->debug('readDisponibilita', 'DirtySku non esistente inserisco disponibilità');
                            $skusToUpdate[$dirtySkuId] = $dirtySku;
                        }
                    } elseif (count($foundSkus) == 0) {
                        $this->debug('readDisponibilita', 'DirtySku non trovato, inserisco', $dirtySku);
                        /** LO SKU PROPRIO NON C'E, LO INSERISCO */
                        $dirtySku['changed'] = 1;
                        $dirtySkuId = $this->app->dbAdapter->insert('DirtySku', $dirtySku);

                    } else {
                        throw new BambooShouldNeverHappenException('Got more than 1 dirty sku searching for keys');
                    }
                }
                $seenSkus[] = $dirtySkuId;

            } catch (BambooOutOfBoundException $e) {
                $this->error('readDisponibilità', 'errore durante la lettura', $e);
            } catch (\Throwable $e) {
                $this->error('readDisponibilità', 'errore Generico durante la lettura', $e);
            }
        }
        $this->report('readDisponibilita', 'elaboro tutti i DirtySkus da aggiornare, ' . count($skusToUpdate));
        foreach ($skusToUpdate as $dirtySkuId => $dirtySku) {
            $dirtySku['checksum'] = md5(json_encode($dirtySku));
            $dirtySku['changed'] = 1;
            \Monkey::app()->dbAdapter->update('DirtySku', $dirtySku, ['id' => $dirtySkuId]);
        }

        $this->report('readDisponibilita', 'Updated ' . $affected . ' rows over ' . $cycled);

        if ($this->config->fetch('filesConfig', 'mode') && $this->config->fetch('filesConfig', 'mode') == 'full') {
            $this->findZeroSkus($seenSkus);
        }
    }

    public function sendPhotos()
    {
        try {
            $ftpSourceConfig = $this->config->fetch('miscellaneous', 'photoFTPClient');
            $ftpSource = new CFTPClient($this->app, $ftpSourceConfig);
            if (isset($ftpSourceConfig['folder']) && !empty($ftpSourceConfig['folder'])) {
                $ftpSource->changeDir($ftpSourceConfig['folder']);
            }

            $ftpDestConfig = $this->config->fetch('miscellaneous', 'destFTPClient');
            $ftpDest = new CFTPClient($this->app, $ftpDestConfig);
            if (isset($ftpDestConfig['folder']) && !empty($ftpDestConfig['folder'])) {
                $ftpDest->changeDir($ftpDestConfig['folder']);
            }

            $rawExistingPhotos = $ftpSource->nList();
            $this->report('PhotoDownload', 'Remote Photo count:' . count($rawExistingPhotos));
            $existingPhotos = [];
            $slugify = new CSlugify();
            try {
                foreach ($rawExistingPhotos as $key => $existingPhoto) {
                    $existingPhotos[md5($slugify->slugify(utf8_encode($existingPhoto)))] = $existingPhoto;
                }
            } catch (\Throwable $e) {
                $this->debug('sendPhotos','Error in Ftp List',$rawExistingPhotos);
                throw $e;
            }
            //CONTROLLO DOPPIA POSIZIONE 1 NELLE FOTO
            $this->correctDoublePhotos();


            $photos = $this->app->dbAdapter->query('SELECT df.id, dp.productId, dp.productVariantId, dp.id AS dirtyProductId, df.url, df.position
													FROM DirtyProduct dp, DirtyPhoto df
													WHERE
														dp.id = df.dirtyProductId AND
														dp.productId IS NOT NULL AND
														dp.productVariantId IS NOT NULL AND
														df.shopId = ? AND
														df.worked = 0 ORDER BY dp.id, df.position', [$this->getShop()->id])->fetchAll();


            $this->report('PhotoDownload', 'Downloading ' . count($photos) . ' photos', null);
            $done = 0;
            $counter = 0;
            foreach ($photos as $photo) {
                try {
                    if ($counter % 50 == 0) {
                        $this->report('PhotoDownload', 'Worked ' . $counter . ' photos till now', null);
                    }

                    $baseFolder = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'image-temp-folder') . '/';
                    $fileName = pathinfo($photo['url']);

                    $imgN = str_pad($photo['position'], 3, "0", STR_PAD_LEFT);
                    $name = $photo['productId'] . '-' . $photo['productVariantId'] . '__' . $fileName['filename'] . '_' . $imgN . '.' . $fileName['extension'];
                    $counter++;

                    $searchKey = md5($slugify->slugify($photo['url']));
                    if (isset($existingPhotos[$searchKey])) {
                        try {
                            $got = $ftpSource->get($baseFolder . $name, $existingPhotos[$searchKey]);
                        } catch (BambooFTPClientException $e) {
                            $got = $ftpSource->get($baseFolder . $name, escapeshellarg($photo['url']));
                        }
                        if ($got) {
                            if ($put = $ftpDest->put($baseFolder . $name, $this->getShop()->name . '/' . $name)) {
                                $this->app->dbAdapter->update('DirtyPhoto', ['worked' => 1], ['id' => $photo['id']]);
                                if ($photo['position'] == 1) {
                                    $this->saveDummyPicture(\Monkey::app()->repoFactory->create('Product')->findOne(
                                        [$photo['productId'],
                                            $photo['productVariantId']]), $baseFolder . $name);
                                }
                                unlink($baseFolder . $name);
                            }
                        }
                    } else {
                        $this->warning('PhotoDownload', 'Photo not found in remote folder ' . $photo['url']);
                    }
                } catch (\Throwable $e) {
                    $this->error('PhotoDownload', 'error while working photo ' . $photo['url'], $e);
                }
            }

            $this->report('PhotoDownload', 'Dowloaded photo for ' . $done . ' photos', null);

        } catch (\Throwable $e) {
            $this->error('PhotoDownload', 'Error retriving Photos', $e);
            throw $e;
        }

    }

    /**
     *
     */
    public function correctDoublePhotos()
    {
        $doubles = $this->app->dbAdapter->query("SELECT dirtyProductId
										FROM DirtyPhoto 
										WHERE position = 1 AND 
											shopId = ? 
										GROUP BY dirtyProductId, position 
										HAVING count(id) > 1 ", [$this->getShop()->id])->fetchAll();
        foreach ($doubles as $double) {
            $photos = $this->app->dbAdapter->query("SELECT id,url FROM DirtyPhoto WHERE position = 1 AND dirtyProductId = ?", [$double['dirtyProductId']])->fetchAll();
            foreach ($photos as $photo) {
                $photoName = pathinfo($photo['url'], PATHINFO_FILENAME);
                $endsWith = '0';
                //SE NON FINISCE CON 0 gli cambio numero
                if ($endsWith === "" || strrpos($photoName, $endsWith, -strlen($photoName)) === false) {
                    $this->app->dbAdapter->update('DirtyPhoto', ['position' => 7], ['id' => $photo['id']]);
                    break;
                }
            }
        }

    }

    /**
     * @param \DOMElement $element
     * @param $field
     * @return null|string
     */
    protected function getUniqueElementNodeValue(\DOMElement $element, $field)
    {
        return $element->getElementsByTagName($field)->item(0) !== null ? $element->getElementsByTagName($field)->item(0)->nodeValue : null;
    }
}