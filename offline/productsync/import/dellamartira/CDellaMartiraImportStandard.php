<?php
namespace bamboo\offline\productsync\import\dellamartira;

use bamboo\core\application\AApplication;
use bamboo\core\base\CConfig;
use bamboo\core\base\CFTPClient;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooFileException;
use bamboo\core\exceptions\RedPandaException;
use bamboo\core\exceptions\RedPandaFTPClientException;
use bamboo\core\utils\amazonPhotoManager\ImageEditor;
use bamboo\offline\productsync\import\standard\ABluesealProductImporter;

/**
 * Class CDellaMartiraImport
 * @package bamboo\import\productsync\dellamartira
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 28/05/2015
 * @since 1.0
 */
class CDellaMartiraImportStandard extends ABluesealProductImporter {

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

    public function fetchFiles()
    {
        /** PRODUCTS */
        $files = glob($this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->config->fetch('files','main')['location'].'/*.CSV');
        if(!isset($files[0])) return false;
        $products = $files[0];
        $time = filemtime($products);
        foreach($files as $file) {
            if(filemtime($file)>$time) {
                $products = $file;
                $time = filemtime($file);
            }
        }
        $this->report( "fetchFiles", "Files usato = ".$products,null);
        foreach($files as $file){
            try{
	            if($file == $products) continue;
                unlink($file);
            }catch (\Throwable $e){
                $this->warning('FetchFile', 'Could not unlink file '.$file,$e);
            }
        }
	    $this->mainFilenames = [$products];
    }

	public function readFile($file)
	{
		$size = filesize($file);
		while($size != filesize($file)) {
			sleep(1);
			$size = filesize($file);
		}
		$f = fopen($file,'r');
		$lines=0;
		while(fgets($f)!= false){
			$lines++;
		}
		fclose($f);
		$this->report('fetchFiles','Lines Counted: '.$lines);
		$info = pathinfo($file)['filename'];
		$info = explode('_',$info);
		$this->report('fetchFiles', 'Lines Counted: ' . $lines . ' Lines written: ' . $info[1]);
		if(abs($lines - $info[1]) > ($info[1] * 0.01) +1) {
			$this->report('fetchFiles', 'File Corrupted line mismatch more than 1%, throwing');
			throw new BambooFileException('Invalid file in Input');
		}
		return true;
	}

	public function processFile($file)
	{
		$this->readMain($file);
		$this->readSku($file);
		$this->findZeroSkus($this->seenSkus);
		// TODO: Implement processFile() method.
	}

	/** LEGGE IL FILE PER TROVARE I PRODOTTI */
    public function readMain($file)
    {
        //read main
        $main = fopen($file,'r');
        $iterator = 0;
        $newLines = 0;
        while (($values = fgetcsv($main,0, $this->config->fetch('miscellaneous','separator'), '"')) !== false ) {
            try {
                if (count($values) != $this->config->fetch('files', 'main')['columns']) continue;
                $mainMapping = $this->config->fetch('mapping', 'main');
                $mainValues = [];
                foreach ($mainMapping as $key => $val) {
                    $mainValues[$key] = trim($values[$val]);
                }
                $line = implode($this->config->fetch('miscellaneous', 'separator'), $mainValues);
                $md5 = md5($line);
                $mainValues['checksum'] = $md5;
                $exist = $this->app->dbAdapter->selectCount("DirtyProduct", ['checksum' => $md5, 'shopId' => $this->shop->id]);

                /** Already written */
                if ($exist == 1) {
                    continue;
                }
                /** Insert/update */
                if ($exist == 0) {
                    $newLines++;
                    /** RICERCA PER VALORI CHIAVE ESTERNO */
                    $identify['brand'] = $mainValues['brand'];
                    $identify['itemno'] = $mainValues['itemno'];
                    $identify['var'] = $mainValues['var'];
                    $identify['shopId'] = $this->shop->id;

                    $mainValues['price'] = (float)str_replace(',', '.', $mainValues['price']);
                    $mainValues['value'] = (float)str_replace(',', '.', $mainValues['value']);
                    $mainValues['salePrice'] = (float)str_replace(',', '.', $mainValues['salePrice']);

                    $res = $this->app->dbAdapter->select('DirtyProduct', $identify)->fetchAll();
                    /** find if exist the same product and update entities */
                    if (count($res) == 1) {
                        $update = $mainValues;
                        $update['text'] = implode(',', $values);
                        if(isset($update['clipboard'])) unset($update['clipboard']);
                        unset($update['photos']);
                        unset($update['details']);
                        $res = $this->app->dbAdapter->update('DirtyProduct', array_diff($update, $identify), $identify);
                        //log
                        continue;
                    } elseif (count($res) == 0) {
                        /** è un nuovo prodotto lo scrivo */
                        $insert = $mainValues;
                        $insert['text'] = implode(',', $values);
                        unset($insert['photos']);
                        unset($insert['details']);
                        $insert['shopId'] = $this->shop->id;
                        $insert['dirtyStatus'] = 'F';
                        $res = $this->app->dbAdapter->insert('DirtyProduct', $insert);
                        if ($res < 0) {
                            continue;
                        }
						$this->fillProduct($res,$values);
                    } else {
                        //error
                        //log
                        continue;
                    }
                }
            }catch (\Throwable $e){
                $this->error( 'Error reading Main','read Context',$e);
            }
        }
        $this->report( 'Read Main done', 'read line: '.$newLines,null);
        return $iterator;
    }

	public function fillProduct($id,$values) {
		$fullMapping = $this->config->fetch('mapping', 'full');
		$fullValues = [];
		foreach ($fullMapping as $key => $val) {
			$fullValues[$key] = trim($values[$val]);
		}
		$this->app->dbAdapter->update('DirtyProduct',['brand'=>$fullValues['brand']],['id'=>$id]);
		$this->app->dbAdapter->insert('DirtyProductExtend',['dirtyProductId'=>$id,
		                                                    'shopId'=>$this->getShop()->id,
		                                                    'name'=>$fullValues['name'],
		                                                    'season'=>$fullValues['season'],
		                                                    'audience'=>$fullValues['audience'],
		                                                    'cat1'=>$fullValues['category'],
		                                                    'sizeGroup'=>$fullValues['sizeGroup'],
		                                                    'generalColor'=>$fullValues['color']
															]);
		$this->insertDetails($id,explode('|',$fullValues['details']));
		$this->insertPhotos($id,explode('|',$fullValues['photos']));
	}


	public function insertDetails($id, array $details)
	{
		foreach($details as $detail) {
			$this->app->dbAdapter->insert('DirtyDetail',['dirtyProductId'=>$id,'content'=>$detail]);
		}
	}

	public function insertPhotos($id, array $photos)
	{
		$i = 1;
		foreach($photos as $photo) {
			if($photo == '**INVIATO PER FOTO PRESSO VS SEDE**') continue;
			$this->app->dbAdapter->insert('DirtyPhoto',['dirtyProductId'=>$id,'url'=>$photo,'position'=>$i,'worked'=>0,'shopId'=>$this->getShop()->id]);
			$i++;
		}
	}

	/** LEGGE IL FILE PER TROVARE GLI SKUS */
    public function readSku($file)
    {
        //read SKUS ------------------
	    $skus = fopen($file,'r');
        $iterator = 0;
        $changedSku = 0;
        while (($values = fgetcsv($skus,0, $this->config->fetch('miscellaneous','separator') ,'"')) !== false ) {

            try {
                if (count($values) != $this->config->fetch('files', 'skus')['columns']) {
                    //ERROR
                    continue;
                }
                $sku = [];
                /** Isolate values and find good ones */
                $mapping = $this->config->fetch('mapping','skus');
                foreach($mapping as $key=>$val){
                    $sku[$key] = trim($values[$val]);
                }

                $line = implode( $this->config->fetch('miscellaneous' ,'separator'),$sku);
                $md5 = md5($line);
                $exist = $this->app->dbAdapter->select("DirtySku", ['checksum' => $md5])->fetchAll();

                /** Already written */
                if (is_array($exist) && count($exist) === 1) {
                    $this->seenSkus[] = $exist[0]['id'];
                    continue;
                } elseif (is_array($exist) && count($exist) === 0) {
                    /** Insert or Update */
                    $changedSku++;

                    $sku['shopId'] = $this->shop->id;
                    /** RICERCA PER CODICE ESTERNO */

                    $keys = $this->config->fetch('files', 'main')['extKeys'];
                    /** find keys */
                    $matchProduct = [];
                    foreach ($keys as $key) {
                        $matchProduct[$key] = $sku[$key];
                    }
                    $matchProduct['shopId'] = $this->shop->id;

                    /** Find Product  */
                    $dirtyProduct = $this->app->dbAdapter->select('DirtyProduct', $matchProduct)->fetchAll();
                    if (is_array($dirtyProduct) && count($dirtyProduct) !== 1) {
                        //error - PRODUCT not found? are u kidding me? it's the same file!
                        $this->error( 'Reading Skus', 'Dirty Product not found while looking at sku', $values);
                        continue;
                    }
                    $dirtyProduct = $dirtyProduct[0];
                    $sku['text'] = $line;
                    $sku['checksum'] = $md5;
                    /** Adjust prices */
                    $sku['price'] = str_replace(',','.', $sku['price']);
                    $sku['value'] = str_replace(',','.', $sku['value']);
                    $sku['salePrice'] = str_replace(',','.', $sku['salePrice']);
                    $res = $this->app->dbAdapter->select('DirtySku', ['dirtyProductId' => $dirtyProduct['id'], 'size' => $sku['size']])->fetchAll();
                    /** Update */
                    if (is_array($res) && count($res) == 1) {
                        $sku['changed'] = true;
                        $id = $res[0]['id'];
                        $res = $this->app->dbAdapter->update('DirtySku', array_diff($sku, $matchProduct), ["id" =>$id ]);
                        $this->seenSkus[] = $id;
                        //check ok
                        /** Insert New */
                    } else if (is_array($res) && count($res) == 0) {
                        if($sku['qty'] == 0) continue;
                        unset($sku['brand']);
                        unset($sku['itemno']);
                        unset($sku['var']);
                        $sku['dirtyProductId'] = $dirtyProduct['id'];
                        $sku['shopId'] = $this->shop->id;
                        $sku['changed'] = true;
                        $new = $this->app->dbAdapter->insert('DirtySku', $sku);
                        $this->seenSkus[] = $new;
                    } else {
                        $this->error( 'Reading Skus', 'Found more than one DirtySku', $values);
                        continue;
                    }
                }
            }catch(\Throwable $e){
                $this->error('Error Reading Skus','Skus reading error', $e);
                continue;
            }
        }
        $this->report( 'Read Skus done', 'read line: '.$changedSku,null);
        return $iterator;
    }

	/**
	 * @param $dirtyProductId
	 * @param AApplication $app
	 * @return bool|string
	 * @throws RedPandaFTPClientException
	 * @throws \bamboo\core\exceptions\RedPandaDBALException
	 */
    public static function getDummyPic($dirtyProductId, AApplication $app)
    {
        $config = new CConfig(__DIR__."/import.dellamartira.config.json");
        $config->load();
        $ftp = new CFTPClient($app,$config->fetch('miscellaneous','photoFTPClient'));
        $ieditor = new ImageEditor();
        $dummyFolder = $app->rootPath().$app->cfg()->fetch('paths','dummyFolder').'/';

        $photoList = $ftp->nList();
        $photoListSearch = array_map('strtolower', $photoList);
        $photo = $app->dbAdapter->query("SELECT url FROM DirtyPhoto WHERE dirtyProductId = ?",[$dirtyProductId])->fetchAll()[0];
        $photo = $photo['url'];
        $fileName = pathinfo($photo);

        if(($id = array_search(str_replace(' ','_',strtolower('./'.$photo)), $photoListSearch))=== false) {
            return false;
        }
	    $dummyName = rand(0,9999999999).'.'.$fileName['extension'];
        if (($got = $ftp->get(__DIR__.'/temp/photos/'.$dummyName,$photoList[$id]))) {
            $ieditor->load(__DIR__.'/temp/photos/'.$dummyName);
            $ieditor->resizeToWidth(500);
            $ieditor->save($dummyFolder.'/'.$dummyName);
            return $dummyFolder.$dummyName;
        }

        return false;
    }

	/**
	 * @throws RedPandaFTPClientException
	 * @throws \bamboo\core\exceptions\RedPandaDBALException
	 */
    public function fetchPhotos()
    {
        $widht = 500;
        $photos = $this->app->dbAdapter->query("SELECT df.id, dp.productId, dp.productVariantId, df.url, df.position
													FROM DirtyProduct dp, DirtyPhoto df
													WHERE
														dp.id = df.dirtyProductId and
														dp.productId is not null AND
														dp.productVariantId is not null AND
														df.shopId = ? AND
														df.worked = 0 order by dp.id, df.position
														",[$this->shop->id])->fetchAll();
        if(count($photos) == 0) {
            //log nothing to do
            return;
        }
        $dummyFolder = $this->app->rootPath().$this->app->cfg()->fetch('paths','dummyFolder').'/';
        $this->app->vendorLibraries->load("amazon2723");
        $imager = new ImageEditor();
        $this->report('PhotoDownload','Downloading '.count($photos).' photos',null);
        $ftpSource = new CFTPClient($this->app,$this->config->fetch('miscellaneous','photoFTPClient'));
        $done = 0;
        $counter = 0;
	    $photoList = $ftpSource->nList();
	    if(!$photoList) {
		    throw new BambooException('Could not read the photo dir: '.$photoList,$ftpSource);
	    }
	    $photoListSearch = array_map('strtolower', $photoList);

        foreach ($photos as $photo) {
            try {
                if ($counter % 50 == 0) {
                    $this->report('PhotoDownload','Worked '.$counter.' photos till now',null);
                }
	            $baseFolder= $this->app->rootPath().$this->app->cfg()->fetch('paths','image-temp-folder').'/';
                $fileName = pathinfo($photo['url']);
                if(($id = array_search(str_replace(' ','_',strtolower('./'.$photo['url'])), $photoListSearch))=== false) continue;
                $name = $photo['productId'].'-'.$photo['productVariantId'].'__'.$fileName['filename'].'.'.$fileName['extension'];
                $counter++;
                if (($got = $ftpSource->get($baseFolder.$name,$photoList[$id]))) {
	                $this->app->dbAdapter->update('DirtyPhoto',['worked'=>1],['id'=>$photo['id']]);
                    if ($photo['position']==1){
                        $imager->load($baseFolder.$name);
                        $imager->resizeToWidth($widht);
                        $dummyName = rand(0,9999999999).'.'.$fileName['extension'];
                        $imager->save($dummyFolder.'/'.$dummyName);
                        $this->app->dbAdapter->update('Product',['dummyPicture'=>$dummyName],['id'=>$photo['productId'],'productVariantId'=>$photo['productVariantId']]);
                        $this->report( 'PhotoDownload', 'Set dummyPicture: '.$dummyName);
                    }
                }
            } catch(\Throwable $e){
                $this->error('PhotoDownload','error while working photo '.$photo['id'], $e);
            }
        }

        $this->report('PhotoDownload','Dowloaded photo for '.$done.' photos',null);
    }

	/**
	 * @throws RedPandaFTPClientException
	 */
    public function sendPhotos()
    {
        sleep(1);
        $ftpDestination = new CFTPClient($this->app, $this->config->fetch('miscellaneous', 'destFTPClient'));
        if(!$ftpDestination->changeDir('/shootImport/incoming/DellaMartira')){
            throw new RedPandaFTPClientException('Could not change dir');
        };
        $photos = glob($this->app->rootPath().$this->app->cfg()->fetch('paths','image-temp-folder').'/*');
        $got = 0;
        $this->report('PhotoUpload','Photos to send: '.count($photos).'',null);
        foreach($photos as $photo){
            if($got % 100 == 0) {
                $this->report('PhotoUpload','Got '.$got.' photos till now',null);
            }
            try{
                if($ftpDestination->put($photo,basename($photo))){
                    $got++;
                    unlink($photo);
                }
            }catch (\Throwable $e){ $this->warning('PhotoUpload','Error while putting/deleting photo',$e); }
        }
        $this->report('PhotoUpload','Photos sent: '.$got.'',null);
    }

    public function saveFiles()
    {
        $dest = $this->err ? "err" : "done";

        fclose($this->mainF);
        fclose($this->skusF);

        $now = new \DateTime();
        $phar = new \PharData($this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->shop->name.'/import/'.$dest.'/'.$now->format('YmdHis').'.tar');

        $phar->addFile($this->main);
        $phar->addFile($this->skus);
        if ($phar->count() > 0) {
            $phar->compress(\Phar::GZ);
        }

        unlink($this->app->rootPath().$this->app->cfg()->fetch('paths','productSync').'/'.$this->shop->name.'/import/'.$dest.'/'.$now->format('YmdHis').'.tar');
        unlink($this->main);
        unlink($this->skus);
    }
}