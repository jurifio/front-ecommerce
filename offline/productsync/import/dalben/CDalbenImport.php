<?php


namespace bamboo\offline\productsync\import\dalben;
use bamboo\core\base\CFTPClient;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;


/**
 * Class CDalbenImport
 * @package offline\productsync\import\dalben
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 20/02/2018
 * @since 1.0
 */
class CDalbenImport extends ABluesealProductImporter {

    protected $config;
    protected $log;

    protected $skusF;
    protected $skus;
    protected $mainF;
    protected $main;
    protected $shop;
    protected $err = false;

    protected $mainRows = 0;
    protected $skusRows = 0;

    protected $seenSkus;

    /**
     * Fetch file from ftp, copy it and set path on $this->mainF
     */
    public function fetchFTPFiles()
    {
        $localDir = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->getShop()->name.'/import/';

        $istructions = $this->config->fetch('filesConfig', 'istructions');

        $ftp = new CFTPClient($this->app, $this->config->fetchAll('filesFtpConfig'));
        $remoteDir = $istructions['remoteFolder'];
        $regex = $istructions['regex'];
        $ftp->changeDir($remoteDir);
        $list = $ftp->nList();
        $cn = $ftp->isConnected();

        usort($list, function ($a, $b) use ($cn) {
            return ftp_mdtm($cn, $a) > ftp_mdtm($cn, $b);
        });
        $this->report('Files ftp', implode(' - ', $list));

        foreach ($list as $one) {
            if (preg_match($regex, $one) == 1) {
                try {
                    if ($ftp->get($localDir . '/' . pathinfo($one)["basename"], $one)) {
                        $ftp->move($one, "old");
                    }
                } catch (\Throwable $e) {
                    $this->error('fetchFTPFiles', 'Could not get file', $one);
                }
            }

        }

        $this->fetchLocalFiles();
    }

    /**
     * Fetch local file and set path on $this->mainFilenames;
     */
    protected function fetchLocalFiles()
    {
        $istructions = $this->config->fetch('filesConfig', 'istructions');
        $root = $istructions['root'] == '__default__' ? $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->getShop()->name : $istructions['root'];
        $folder = $istructions['folder'];
        $glob = $istructions['glob'];

        $files = glob($root . '/' . $folder . '/' . $glob);
        if (empty($files)) {
            $this->mainFilenames = [];
            $this->report("fetchFiles", "Nessun file trovato", null);
        } else {
            usort($files, function ($a, $b) {
                return filemtime($a) > filemtime($b);
            });
            if (isset($istructions['lastOnly']) && $istructions['lastOnly'] == true) {
                $this->report('fetchFiles', "Uso solo l'ultimo di " . count($files) . " files: " . implode(', ', $files));
                $lastFile = $files[count($files) - 1];
                foreach ($files as $file) {
                    if ($file == $lastFile) break;
                    else unlink($file);
                }
                $files = [$lastFile];
            }

            foreach ($files as $k => $singleFile){
                if(filesize($singleFile) > 20000000) {
                    $now = new \DateTime();
                    $zipName = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/done/' . $now->format('YmdHis') . '_' . pathinfo($singleFile)['filename'] . '.tar';
                    $phar = new \PharData($zipName);

                    $phar->addFile($singleFile, pathinfo($singleFile)['basename']);

                    if ($phar->count() > 0) {
                        /** @var \PharData $compressed */
                        $compressed = $phar->compress(\Phar::GZ);
                        if (file_exists($compressed->getPath())) {
                            unlink($singleFile);
                            unlink($zipName);
                        }
                    }

                    unset($files[$k]);
                }
            }

            if(empty($files)){
                $this->mainFilenames = [];
                $this->report("fetchFiles", "File troppo grande", null);
            } else {
                $this->mainFilenames = $files;
                $this->report("fetchFiles", "Files usato: " . implode(', ', $files), null);
            }
        }
    }


    public function readFile($file)
    {
        $size = filesize($file);
        while($size != filesize($file)) {
            sleep(1);
            $size = filesize($file);
        }

        //Apro il file e conto le righe e lo chiudo
        $f = fopen($file,'r');
        $lines=0;
        while(fgets($f)!= false){
            $lines++;
        }
        fclose($f);
        $this->report('fetchFiles','Lines Counted: '.$lines);

        return true;
    }

    public function processFile($file)
    {
        $this->readMain($file);
        $this->readSku($file);
    }

    /** LEGGE IL FILE PER TROVARE I PRODOTTI */
    public function readMain($file)
    {
        //$this->debug('Read main custom', 'Read main custom, go', null);
        //read main
        $main = fopen($file,'r');
        $newLines = 0;
        $mainK = $this->config->fetch('mapping', 'main');
        $second = $this->config->fetch('mapping', 'second');

        //$this->debug('File opened', 'File is opened', null);

        while (($values = fgetcsv($main,0, $this->config->fetch('miscellaneous','separator'), '"')) !== false ) {
            try {
                //$this->debug('In while', 'Valori nel file: '.count($values).' | Valori in conf: '.$this->config->fetch('files', 'main')['columns'].' | Brand: '.$values[12], null);
                if ((count($values) != $this->config->fetch('files', 'main')['columns']) || empty($values[12])){continue;}

                $keyForResearch = [];
                $season = [];
                $priceM = [];
                $valueM = [];

                //preparo i valori principali
                $mainKey = $this->mapDirectKeys($values, $mainK);
                $mainKey["shopId"] = $this->getShop()->id;
                $season["season"] = $values[9];
                $priceM["price"] = $values[11];
                $valueM["value"] = $values[14];

                $keyForResearch[] = $mainKey;
                $keyForResearch[] = $season;
                $keyForResearch[] = $priceM;
                $keyForResearch[] = $valueM;

                $fullKeyForDirtyProducts = $this->joinMultipleAssociativeArray($keyForResearch);

                //CHECKSUM CHE COMPRENDE LA STAGIONE PER VEDERE QUANDO CAMBIA
                $elaboratedKey['checksum'] = md5(implode(',', $fullKeyForDirtyProducts));

                //-------------
                //preparo i valori secondari
                $secondKey = $this->mapDirectKeys($values, $second);
                $secondKey["dirtyStatus"] = "F";
                $secondKey["price"] = floatval(str_replace(',','.', $secondKey["price"]));
                $secondKey["value"] = floatval(str_replace(',','.', $secondKey["value"]));

                $allKey = [];
                $allKey[] = $mainKey;
                $allKey[] = $secondKey;
                $allKey[] = $elaboratedKey;

                $fullKey = $this->joinMultipleAssociativeArray($allKey);

                //preparo valore elaborato
                $fullKey['text'] = implode(',', $fullKey);

                //-------


                //controllo se esistono in db con checksum
                $existingDirtyProduct = \Monkey::app()->dbAdapter->selectCount("DirtyProduct", ['checksum' => $elaboratedKey['checksum']]);

                //$this->debug('Founded products in first global research', $existingDirtyProduct);

                //se esiste in db controllo
                if ($existingDirtyProduct == 1) {
                    //$this->debug('Checksum check ok', 'Product was founded with same checksum');
                    continue;
                } else if ($existingDirtyProduct == 0) {
                    $newLines++;
                    //cerco per mainKey | SE ESISTE è CAMBIATA LA STAGIONE
                    $existProductWithMainKey = \Monkey::app()->dbAdapter->select('DirtyProduct', $mainKey)->fetch();

                    //se esiste è cambiata la stagione o il prezzo o il valore
                    if($existProductWithMainKey){
                        //aggiorno checksum e text su product
                        //$this->debug('old | new - text', $existProductWithMainKey["text"].' | '.$fullKey["text"]);
                        //$this->debug('old | new - checksum',$existProductWithMainKey["checksum"].' | '.$fullKey["checksum"] );
                        \Monkey::app()->dbAdapter->update('DirtyProduct', ["text"=>$fullKey["text"], "checksum"=>$fullKey["checksum"], "price"=>$fullKey["price"], "value"=>$fullKey["value"]],$mainKey);

                        //aggiorno la stagione su extend
                        $this->fillProduct($existProductWithMainKey["id"], $values, false);
                        continue;
                    }

                    //$this->debug('New  dirty product', implode(' | ', $fullKey));
                    $res = \Monkey::app()->dbAdapter->insert('DirtyProduct', $fullKey);
                    //$this->debug('OK DirtyProduct', 'Insert DirtyProduct: '.implode(' | ', $mainKey));

                    $this->fillProduct($res,$values);

                } else {
                    $this->error('Multiple dirty product founded', 'Procedure has founded '.$existingDirtyProduct.' dirty product');
                }
            }catch (\Throwable $e){
                $this->error( 'Error reading Main','read Context',$e);
            }
        }
        $this->report( 'Read Main done', 'read line: '.$newLines,null);
        return true;
    }

    /**
     * @param $id
     * @param $values
     * @param bool $insert
     * @return bool
     * @throws \bamboo\core\exceptions\BambooDBALException
     */
    public function fillProduct($id,$values, $insert = true) {

        //$this->debug('offset for fill product', 'value: '.implode(' - ', $values));

        $extendConfig = $this->config->fetch('mapping', 'extend');
        $extend = $this->mapDirectKeys($values, $extendConfig);
        $extend["dirtyProductId"] = $id;
        $extend["shopId"] = $this->getShop()->id;

        if($insert){
            //$this->debug('offset for fill product', 'value: '.implode(' - ', $extend));
            \Monkey::app()->dbAdapter->insert('DirtyProductExtend', $extend);
            //$this->debug('OK DirtyProductExtend', 'Insert DirtyProductExtend: '.$values[0]);
        } else {
            //$this->debug('Update season', $extend["dirtyProductId"].' - '.$extend["season"]);

            $seasonIsChanged = \Monkey::app()->dbAdapter->select('DirtyProductExtend', ["dirtyProductId"=>$id])->fetch();

            if($seasonIsChanged){
                \Monkey::app()->dbAdapter->update('DirtyProductExtend', ["season" => $extend["season"]], ["dirtyProductId"=>$extend["dirtyProductId"]]);
                //$this->debug('Update season', $extend["dirtyProductId"]);
            } else {
                //$this->debug('Season is already changed', 'Season is alredy changed');
            }
        }


        return true;

    }




    /** LEGGE IL FILE PER TROVARE GLI SKUS */
    public function readSku($file)
    {
        $skus = fopen($file,'r');
        $changedSku = 0;

        while (($values = fgetcsv($skus,0, $this->config->fetch('miscellaneous','separator') ,'"')) !== false ) {

            try {
                if ((count($values) != $this->config->fetch('files', 'main')['columns']) || empty($values[12])) {continue;}

                //trovo il DIRTYPRODUCT per prendere il dirtyProductId
                $mainKeySkus = $this->config->fetch('mapping', 'main');
                $mainKeyForSearch = $this->mapDirectKeys($values, $mainKeySkus);
                $mainKeyForSearch["shopId"] = $this->getShop()->id;

                //$this->debug('mainKeys for search product', $mainKeyForSearch);
                $dirtyProduct = \Monkey::app()->dbAdapter->select('DirtyProduct',$mainKeyForSearch)->fetch();
                //$this->debug('DirtyProductFounded', $dirtyProduct);

                if(!$dirtyProduct){
                    $this->error( 'Reading Skus', 'Dirty Product not found while looking at sku', $values);
                    continue;
                }

                //Trovo le chiavi -> taglia, qty, shop, dp.id
                $mainSku = $this->config->fetch('mapping','skusMain');
                $mainSkuKeys = $this->mapDirectKeys($values, $mainSku);
                $mainSkuKeys["dirtyProductId"] = $dirtyProduct["id"];
                $mainSkuKeys["shopId"] = $this->getShop()->id;

                $secondarySku = $this->config->fetch('mapping','skuSecondary');
                $secondarySkuKeys = $this->mapDirectKeys($values, $secondarySku);

                $allSkusKey = [];
                $allSkusKey[] = $mainSkuKeys;
                $allSkusKey[] = $secondarySkuKeys;

                $fullSkusKey = $this->joinMultipleAssociativeArray($allSkusKey);

                $fullSkusKey["price"] = floatval(str_replace(',','.', $fullSkusKey["price"]));
                $fullSkusKey["value"] = floatval(str_replace(',','.', $fullSkusKey["value"]));

                $fullSkusKey["text"] = implode(',',$fullSkusKey);

                $fullSkusKey["checksum"] = md5(implode(',',$fullSkusKey));


                $exist = $this->app->dbAdapter->selectCount("DirtySku", ['checksum' => $fullSkusKey["checksum"]]);

                //$this->debug('Founded skus in first global research', $exist);

                if ($exist == 1) {
                    $this->seenSkus[] = $exist['id'];
                    continue;
                } elseif ($exist === 0) {

                    $oldSkusValues = [];

                    //$this->debug('New skus to insert/update', $fullSkusKey);

                    $existSkuWithMainKey = \Monkey::app()->dbAdapter->select('DirtySku', $mainSkuKeys)->fetch();

                    if($existSkuWithMainKey){
                        $changedSku++;

                        //$this->debug('Existent sku', $existSkuWithMainKey["size"]);

                        $oldSkusValues["size"] = $existSkuWithMainKey["size"];
                        $oldSkusValues["dirtyProductId"] = $existSkuWithMainKey["dirtyProductId"];
                        $oldSkusValues["shopId"] = $existSkuWithMainKey["shopId"];
                        $oldSkusValues["qty"] = $existSkuWithMainKey["qty"];
                        $oldSkusValues["price"] = $existSkuWithMainKey["price"];
                        $oldSkusValues["value"] = $existSkuWithMainKey["value"];
                        $oldSkusValues["text"] = $existSkuWithMainKey["text"];
                        $oldSkusValues["checksum"] = $existSkuWithMainKey["checksum"];

                        $valuesToUpdate = array_diff($fullSkusKey, $oldSkusValues);
                        $valuesToUpdate["changed"] = 1;

                        //$this->debug('Old values', implode(' | ', $oldSkusValues));
                        //$this->debug('New values', implode(' | ', $fullSkusKey));

                        //$this->debug('Changed value in existent sku', implode(' | ', $valuesToUpdate));
                        \Monkey::app()->dbAdapter->update('DirtySku', $valuesToUpdate, $mainSkuKeys);
                        //$this->debug('OK DirtySku', 'Updated DirtySku: '.$values[0].' Size: '.$values[1]);

                    } else if (!$existSkuWithMainKey) {
                        \Monkey::app()->dbAdapter->insert('DirtySku', $fullSkusKey);
                        //$this->debug('OK DirtySku', 'Insert DirtySku: '.$values[0]);
                    }
                }
            } catch(\Throwable $e){
                $this->error('Error Reading Skus','Skus reading error', $e);
                continue;
            }
        }
        $this->report( 'Read Skus done', 'read line: '.$changedSku,null);
        return true;
    }
}