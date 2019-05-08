<?php

namespace bamboo\offline\productsync\import\standard;

use bamboo\core\application\AApplication;
use bamboo\core\base\CConfig;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\RedPandaFileException;
use bamboo\core\exceptions\RedPandaOutOfBoundException;

/**
 * Class ABluesealCSVProductImporter
 * @package bamboo\htdocs\pickyshop\import\blueseal
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, 13/01/2016
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
abstract class ABluesealCSVProductImporter extends ABluesealProductImporter
{

	/**
	 * ABluesealProductImporter constructor.
	 * @param AApplication $app
	 * @param null $jobExecution
	 */
	public function __construct(AApplication $app, $jobExecution = null)
	{
		parent::__construct($app, $jobExecution);
		$this->genericConfig = new CConfig(__DIR__ . "/config/bluesealCSVProductImporter.json");
		$this->genericConfig->load();
	}

	/**
	 * read files and validate them
	 * @param $file
	 * @return bool
	 * @throws RedPandaFileException
	 * @throws RedPandaOutOfBoundException
	 */
	public function readFile($file)
	{
		$separator = $this->config->fetch('filesConfig', 'separator') ? $this->config->fetch('filesConfig', 'separator') : $this->genericConfig->fetch('filesConfig', 'separator');
		$delimiter = $this->config->fetch('filesConfig', 'delimiter') ? $this->config->fetch('filesConfig', 'delimiter') : $this->genericConfig->fetch('filesConfig', 'delimiter');
		$columns = $this->genericConfig->fetch('filesConfig', 'columns');
		$mapping = $this->genericConfig->fetch('filesConfig', 'mapping');
		$numericFields = $this->genericConfig->fetch('filesConfig', 'control')['numeric'];

		$filename = pathinfo($file)['filename'];
		$parts = explode('_', $filename);

		$action = strtolower($parts[0]);
		$shop = $parts[1];
		$date = $parts[2];
		$countedRows = $parts[3];

		$f = fopen($file, 'r');
		$line = 2;
		fgetcsv($f, 0, $separator, $delimiter);
		fgetcsv($f, 0, $separator, $delimiter);
		while (($values = fgetcsv($f, 0, $separator, $delimiter)) !== false) {
			if(count($values) < $columns) throw new RedPandaFileException('Columns not valid at line %s ',[$line]);
			if(!empty(trim($values[0]))) {
				$values = $this->mapValues($values,$mapping);
				$line++;
				if(strtolower($values['relationship']) != 'child') continue;
				$numerics = $this->mapKeys($values, $numericFields);
				foreach($numerics as $key=>$num) if(!empty($num) && !preg_match('/^[0-9]+(?:[\.,][0-9]{0,2})?$/', $num)) throw new RedPandaFileException('Filed %s must be numeric at row %s, found %s',[$key,$line,$num]);
			}
		}
		fclose($f);

		$this->report('fetchFiles', 'Lines Counted: ' . $line . ' Lines written: ' . $countedRows);
		if (abs($countedRows - $line) > 2) {
			$this->report('fetchFiles', 'File Corrupted, throwing');
			throw new RedPandaFileException('Invalid file in Input');
		}
		if ($this->getShop()->id != $shop) {
			$this->report('fetchFiles', 'Shop not valid');
			throw new RedPandaOutOfBoundException('Shop not valid: recieved, %s ; expected, %s', [$shop, $shop]);
		}
		if ($action != 'add' && $action != 'revise' && $action != 'set') {
			$this->report('fetchFiles', 'Action not valid');
			throw new RedPandaOutOfBoundException('Action not valid: %s', [$action]);
		}
		if ($action == 'revise' && $countedRows > 1000) {
			$this->report('fetchFiles', 'Too many rows to revise: ' . $countedRows);
			throw new RedPandaFileException('Too many rows for revise: : %s', [$countedRows]);
		}

		return true;
	}

	/**
	 *  File must be valid now, i can assume it is
	 */
	public function processFile($file)
	{
		$filename = pathinfo($file)['basename'];
		$parts = explode('_', $filename);
		$this->{strtolower($parts[0])}($file);
	}

	/** Add products from the files in the Dirty table
	 * @param $file
	 * @throws \bamboo\core\exceptions\RedPandaDBALException
	 */
	public function add($file)
	{
		$this->report('add', 'Starting');
		$iterator = 0;
		$separator = $this->config->fetch('filesConfig', 'separator') ? $this->config->fetch('filesConfig', 'separator') : $this->genericConfig->fetch('filesConfig', 'separator');
		$delimiter = $this->config->fetch('filesConfig', 'delimiter') ? $this->config->fetch('filesConfig', 'delimiter') : $this->genericConfig->fetch('filesConfig', 'delimiter');
		$mapping = $this->genericConfig->fetch('filesConfig', 'mapping');

		$notEmptyProduct = $this->genericConfig->fetch('filesConfig', 'control')['productNotEmpty'];
		$emptyToFullProduct = $this->genericConfig->fetch('filesConfig', 'control')['emptyToFullProduct'];
		$notEmptySku = $this->genericConfig->fetch('filesConfig', 'control')['skuNotEmpty'];

		$relationshipMapping = $this->genericConfig->fetch('filesConfig', 'relationship');
		$productMapping = $this->genericConfig->fetch('filesConfig', 'product');
		$productExtendMapping = $this->genericConfig->fetch('filesConfig', 'productExtend');
		$photoMapping = $this->genericConfig->fetch('filesConfig', 'photos');
		$detailMapping = $this->genericConfig->fetch('filesConfig', 'details');
		$skuMapping = $this->genericConfig->fetch('filesConfig', 'sku');

		$productKeysMap = $this->config->fetch('keys', 'product');
		$skuKeys = $this->config->fetch('keys', 'sku');

		$workingParents = [];
		$workingProduct = [];

		$keysChecksums = $this->app->dbAdapter->query('SELECT keysChecksum FROM DirtyProduct WHERE shopId = ?', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
		$keysChecksums = array_flip($keysChecksums);
		$keysSkus = $this->app->dbAdapter->query('SELECT extSkuId FROM DirtySku WHERE shopId = ?', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
		$keysSkus = array_flip($keysSkus);

		$f = fopen($file,'r');
		fgets($f);
		fgets($f);
		$shopIdArr = ['shopId'=>$this->getShop()->id];
		$this->report('add', 'All Configuration Ready');

		$productCount = 0;
		$skuCount = 0;
		while (($values = fgetcsv($f, 0, $separator, $delimiter)) !== false) {
			if(empty(trim($values[0]))) continue;
			$iterator++;
			try {
				$values = $this->mapValues($values,$mapping);

				/** Check if this is a new parentProduct or an existing one */
				$relationshipVal = $this->mapKeys($values, $relationshipMapping);
				if (strtolower($relationshipVal['relationship']) == 'parent') {
					$res = $this->app->dbAdapter->query("SELECT id from DirtyProduct where shopId = ? and relationshipId is null and parentName = ? limit 1",[$this->getShop()->id, $relationshipVal['extSkuId']])->fetchAll();
					if(isset($res[0])) $workingParents[$relationshipVal['extSkuId']] = $res[0]['id'];
					else $workingParents[$relationshipVal['extSkuId']] = 0;
					$workingProduct = [];
					continue;
				}

				$productVal = $this->mapKeys($values, $productMapping);
				/** Check if this row is a new Variant (if new parent new Variant for sure) */
				if (empty($workingProduct) || $workingProduct['var'] != $productVal['var'] || $workingProduct['itemno'] != $productVal['itemno'] ) {
					/** controllo i campi obbligatori */
					foreach($this->mapKeys($values,$notEmptyProduct) as $name => $field) {
						if(empty(trim($field))) {
							$this->warning('Add - Product','Product mandatory field empty: '.$name.' Row REFUSED',$values);
							$this->app->dbAdapter->insert('ImportItemsReview',['shopId'=>$this->getShop()->id,
					                                                          'jobId'=>$this->jobId,
					                                                          'jobExecutionId'=>$this->jobExecutionId,
					                                                          'action'=>'add',
					                                                          'object'=>'product',
																			  'fileType'=>'csv',
					                                                          'file'=>$file,
					                                                          'data'=>json_encode($values)]);
							\Monkey::app()->repoFactory->commit();
						}
						continue;
					}
					/** i always switch the actual product with the working one, to be always updated */
					$workingProduct = $productVal;

					/** estraggo le chiavi specifiche di questo friend e ne faccio il checksum */
					$productKeys = $this->mapKeys($productVal, $productKeysMap);
					$keysChecksum = md5(implode('::', $productKeys));
					/** verifico nella lista delle chiavi se questo prodotto è già stato inserito, se si loggo il warning e salto tutto */
					if (array_key_exists($keysChecksum, $keysChecksums)) {
						$this->warning('add','Key checksum already existing: '.$keysChecksum);
						//prodotto già esistente... SERVIREBBE REVISE
						continue;
					} else {
						$keysChecksums[$keysChecksum] = true;
					}
					$workingProduct['keysChecksum'] = $keysChecksum;

					/** check if a relationship status is already present, if there i put the id in the "relationshio" field */
					if (isset($workingParents[$relationshipVal['parent']]) && $workingParents[$relationshipVal['parent']] != 0) {
						$workingProduct['relationshipId'] = $workingParents[$relationshipVal['parent']];
					} else {
						$workingProduct['relationshipId'] = null;
						unset($workingProduct['relationshipId']);
					}

					\Monkey::app()->repoFactory->beginTransaction();

					$workingProduct['dirtyStatus'] = 'F';
					foreach($this->mapKeys($values,$emptyToFullProduct) as $field) {
						if(empty($field)) $workingProduct['dirtyStatus'] = "E";
					}

					$workingProduct['id'] = $this->app->dbAdapter->insert('DirtyProduct', $workingProduct+$shopIdArr);
					if($workingProduct['id']>0) $productCount++;
					/** if relationship status was null now i fill it for future Variants */
					if (!isset($workingProduct['relationshipId']) || $workingProduct['relationshipId'] == null) {
						$workingParents[$relationshipVal['parent']] = $workingProduct['id'];
					}
					/** riempio tutte le varie tabelle che orrorrono, ormai non devo più fare controlli sul prodotto */
					/** productExtend */
					$extend = $this->mapKeys($values, $productExtendMapping);
					$extend['dirtyProductId'] = $workingProduct['id'];
					$extend['checksum'] = md5(implode("::", $extend));
					$this->app->dbAdapter->insert('DirtyProductExtend', $extend+$shopIdArr);


					/** productPhoto */
					$photos = $this->mapKeys($values, $photoMapping);
					$i = 0;
					foreach ($photos as $photo) {
						if (empty($photo)) continue;
						$i++;
						$this->app->dbAdapter->insert('DirtyPhoto', ['dirtyProductId' => $workingProduct['id'], 'url' => $photo, 'position' => $i, 'shopId' => $this->getShop()->id]);
					}
					/** productDetail */
					$details = $this->mapKeys($values, $detailMapping);
					foreach ($details as $key => $detail) {
						if (empty($detail)) continue;
						$this->app->dbAdapter->insert('DirtyDetail', ['dirtyProductId' => $workingProduct['id'], 'label' => $key, 'content' => $detail]);
					}
					/** aggiorno le informazioni di checksum detail e photo */
					$detailChecksum = md5(implode('::', $details + ['dirtyProductId' => $workingProduct['id']]));
					$photosChecksum = md5(implode('::', $photos + ['dirtyProductId' => $workingProduct['id']]));
					$this->app->dbAdapter->update('DirtyProduct', ['photosChecksum' => $photosChecksum, 'detailsChecksum' => $detailChecksum], ['id' => $workingProduct['id']]);
				}

				/** a questo punto working product dovrebbe essere valorizzato ed avere un 'id' inserisco quindi gli skus */
				/** controllo i campi obbligatori */
				foreach($this->mapKeys($values,$notEmptySku) as $name => $field) {
					if(empty($field) && ($field != 0)) {
						$this->warning('Add - Product','Product mandatory field empty: '.$name.' Row REFUSED',$values);
						$this->app->dbAdapter->insert('ImportItemsReview',['shopId'=>$this->getShop()->id,
						                                                   'jobId'=>$this->jobExecution->jobId,
						                                                   'jobExecutionId'=>$this->jobExecution->id,
						                                                   'action'=>'add',
						                                                   'object'=>'sku',
						                                                   'fileType'=>'csv',
						                                                   'file'=>$file,
						                                                   'data'=>json_encode($values)]);
					}
					continue;
				}

				$skuVal = $this->mapKeys($values, $skuMapping);
				if (isset($skuKeys[$skuVal['extSkuId']])) {

					continue;
				}
				
				$skuVal['dirtyProductId'] = $workingProduct['id'];
				$skusChecksum = md5(implode('::', $skuVal));
				$skuVal['checksum'] = $skusChecksum;
				$nuovo = $this->app->dbAdapter->insert('DirtySku', $skuVal+$shopIdArr);
				if($nuovo>0) $skuCount++;
				\Monkey::app()->repoFactory->commit();
			} catch (\Throwable $e) {
				\Monkey::app()->repoFactory->rollback();
				$this->error('add','exception',$e);
			}
		}
		$this->report('add','Iterator Count: '.$iterator);
		$this->report('add','Product Count: '.$productCount);
		$this->report('add','Sku Count: '.$skuCount);
		fclose($f);
	}

	/**
	 * Set products from the files in the Dirty table
	 * @param $file
	 * @throws BambooException
	 */
	public function set($file)
	{
		$this->report('set', 'Starting');
		$iterator = 0;
		$separator = $this->config->fetch('filesConfig', 'separator') ? $this->config->fetch('filesConfig', 'separator') : $this->genericConfig->fetch('filesConfig', 'separator');
		$delimiter = $this->config->fetch('filesConfig', 'delimiter') ? $this->config->fetch('filesConfig', 'delimiter') : $this->genericConfig->fetch('filesConfig', 'delimiter');
		$mapping = $this->genericConfig->fetch('filesConfig', 'mapping');

		$notEmptyProduct = $this->genericConfig->fetch('filesConfig', 'control')['productNotEmpty'];
		$emptyToFullProduct = $this->genericConfig->fetch('filesConfig', 'control')['emptyToFullProduct'];
		$notEmptySku = $this->genericConfig->fetch('filesConfig', 'control')['skuNotEmpty'];

		$relationshipMapping = $this->genericConfig->fetch('filesConfig', 'relationship');
		$productMapping = $this->genericConfig->fetch('filesConfig', 'product');
		$productExtendMapping = $this->genericConfig->fetch('filesConfig', 'productExtend');
		$photoMapping = $this->genericConfig->fetch('filesConfig', 'photos');
		$detailMapping = $this->genericConfig->fetch('filesConfig', 'details');
		$skuMapping = $this->genericConfig->fetch('filesConfig', 'sku');

		$productKeysMap = $this->config->fetch('keys', 'product');
		$skuKeys = $this->config->fetch('keys', 'sku');

		$workingParents = [];
		$workingProduct = [];

		$keysChecksums = $this->app->dbAdapter->query('SELECT keysChecksum FROM DirtyProduct WHERE shopId = ?', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
		$keysChecksums = array_flip($keysChecksums);
		$keysSkus = $this->app->dbAdapter->query('SELECT extSkuId FROM DirtySku WHERE shopId = ?', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
        $keysSkus = array_flip($keysSkus);

        $rowChecksum = [];
        $rawChecksum = $this->app->dbAdapter->query('SELECT checksum,id from DirtySku WHERE shopId = ? and checksum is not null', [$this->getShop()->id])->fetchAll();
        foreach ($rawChecksum as $check) {
            $rowChecksum[$check['checksum']]=$check['id'];
        }
		$f = fopen($file,'r');
		fgets($f);
		fgets($f);
		$shopIdArr = ['shopId'=>$this->getShop()->id];
		$this->report('set', 'All Configuration Ready');

		$productCount = 0;
		$skuCount = 0;
		while (($values = fgetcsv($f, 0, $separator, $delimiter)) !== false) {
			if(empty(trim($values[0]))) continue;
			$iterator++;
            if(($iterator%250) == 0) $this->report('set','Scanned '.$iterator.' rows');
			try {
				$values = $this->mapValues($values,$mapping);
                $skuChecksum = md5(implode('::',$values));
                if(array_key_exists($skuChecksum,$rowChecksum)) {
                    $this->addSeenSku($rowChecksum[$skuChecksum]);
                    $this->debug('Set','Row Skipped');
                    continue;
                }
				/** Check if this is a new parentProduct or an existing one */
				$relationshipVal = $this->mapKeys($values, $relationshipMapping);
				if (strtolower($relationshipVal['relationship']) == 'parent') {
					$res = $this->app->dbAdapter->query("SELECT id from DirtyProduct where shopId = ? and relationshipId is null and parentName = ? limit 1",[$this->getShop()->id, $relationshipVal['extSkuId']])->fetchAll();
					if(isset($res[0])) $workingParents[$relationshipVal['extSkuId']] = $res[0]['id'];
					else $workingParents[$relationshipVal['extSkuId']] = 0;
					$workingProduct = [];
					continue;
				}

				$productVal = $this->mapKeys($values, $productMapping);
				/** Check if this row is a new Variant (if new parent new Variant for sure) */
				if (empty($workingProduct) || $workingProduct['var'] != $productVal['var'] || $workingProduct['itemno'] != $productVal['itemno'] ) {
					
                    /** controllo i campi obbligatori */
					foreach($this->mapKeys($values,$notEmptyProduct) as $name => $field) {
						if(empty(trim($field))) {
							$this->warning('Add - Product','Product mandatory field empty: '.$name.' Row REFUSED',$values);
							$this->app->dbAdapter->insert('ImportItemsReview',['shopId'=>$this->getShop()->id,
								'jobId'=>$this->jobId,
								'jobExecutionId'=>$this->jobExecutionId,
								'action'=>'set',
								'object'=>'product',
								'fileType'=>'csv',
								'file'=>$file,
								'data'=>json_encode($values)]);
							\Monkey::app()->repoFactory->commit();
						}
						continue;
					}
					/** i always switch the actual product with the working one, to be always updated */
					$workingProduct = $productVal;

					/** estraggo le chiavi specifiche di questo friend e ne faccio il checksum */
					$productKeys = $this->mapKeys($productVal, $productKeysMap);
					$keysChecksum = md5(implode('::', $productKeys));

					$workingProduct['keysChecksum'] = $keysChecksum;

					/** check if a relationship status is already present, if there i put the id in the "relationshio" field */
					if (isset($workingParents[$relationshipVal['parent']]) && $workingParents[$relationshipVal['parent']] != 0) {
						$workingProduct['relationshipId'] = $workingParents[$relationshipVal['parent']];
					} else {
						$workingProduct['relationshipId'] = null;
						unset($workingProduct['relationshipId']);
					}



					$workingProduct['dirtyStatus'] = 'F';
					foreach($this->mapKeys($values,$emptyToFullProduct) as $field) {
						if(empty($field)) $workingProduct['dirtyStatus'] = "E";
					}

                    /** se il prodotto non esiste lo inserisco */
                    if (!array_key_exists($keysChecksum, $keysChecksums)) {

                        \Monkey::app()->repoFactory->beginTransaction();
                        try {
                            $workingProduct['id'] = $this->app->dbAdapter->insert('DirtyProduct', $workingProduct + $shopIdArr);

                            /* DirtyProductExtended */
                            $extend = $this->mapKeys($values, $productExtendMapping);
                            $extend['dirtyProductId'] = $workingProduct['id'];
                            $extend['checksum'] = md5(implode("::", $extend));
                            $this->app->dbAdapter->insert('DirtyProductExtend', $extend + $shopIdArr);


                            /* aggiungo le foto */
                            $photos = $this->mapKeys($values, $photoMapping);
                            $i = 0;
                            foreach ($photos as $photo) {
                                if (empty($photo)) continue;
	                            $i++;
                                $this->app->dbAdapter->insert(
                                    'DirtyPhoto',
                                    [
                                        'dirtyProductId' => $workingProduct['id'],
                                        'url' => $photo,
                                        'position' => $i,
                                        'shopId' => $this->getShop()->id
                                    ]
                                );
                            }

                            /* aggiungo i dettagli */
                            $details = $this->mapKeys($values, $detailMapping);
                            foreach ($details as $key => $detail) {
                                if (empty($detail)) continue;
                                $this->app->dbAdapter->insert(
                                    'DirtyDetail',
                                    [
                                        'dirtyProductId' => $workingProduct['id'],
                                        'label' => $key,
                                        'content' => $detail
                                    ]
                                );
                            }

                            /** aggiorno le informazioni di checksum detail e photo */
                            $detailChecksum = md5(implode('::', $details + ['dirtyProductId' => $workingProduct['id']]));
                            $photosChecksum = md5(implode('::', $photos + ['dirtyProductId' => $workingProduct['id']]));
                            $this->app->dbAdapter->update('DirtyProduct', ['photosChecksum' => $photosChecksum, 'detailsChecksum' => $detailChecksum], ['id' => $workingProduct['id']]);

                            \Monkey::app()->repoFactory->commit();
                        } catch (\Throwable $e) {
                            \Monkey::app()->repoFactory->rollback();
                            $this->error('set', "inserimento prodotto non presente", $e->getCode() . ' - ' . $e->getMessage());
                        }

                    } else {
                        $res = $this->app->dbAdapter->select('DirtyProduct', $productKeys)->fetchAll()[0];
                        $workingProduct['id'] = $res['id'];
                    }

					if ($workingProduct['id']>0) $productCount++;
					/** if relationship status was null now i fill it for future Variants */
					if (!isset($workingProduct['relationshipId']) || $workingProduct['relationshipId'] == null) {
                        $workingParents[$relationshipVal['parent']] = $workingProduct['id'];
                    }


                }

				/** a questo punto working product dovrebbe essere valorizzato ed avere un 'id' inserisco quindi gli skus */
				/** controllo i campi obbligatori */
				foreach($this->mapKeys($values,$notEmptySku) as $name => $field) {
					if(empty($field) && ($field != 0)) {
						$this->warning('Add - Product','Product mandatory field empty: '.$name.' Row REFUSED',$values);
						$this->app->dbAdapter->insert('ImportItemsReview',['shopId'=>$this->getShop()->id,
							'jobId'=>$this->jobExecution->jobId,
							'jobExecutionId'=>$this->jobExecution->id,
							'action'=>'add',
							'object'=>'sku',
							'fileType'=>'csv',
							'file'=>$file,
							'data'=>json_encode($values)]);
					}
					continue;
				}

				$skuVal = $this->mapKeys($values, $skuMapping);
				if (isset($keysSkus[$skuVal['extSkuId']])) {
					$this->reviseSingleSku($skuMapping, $values,$skuChecksum);
				} else {
					$skuVal['dirtyProductId'] = $workingProduct['id'];
					$skuVal['checksum'] = $skuChecksum;
					$nuovo = $this->app->dbAdapter->insert('DirtySku', $skuVal+$shopIdArr);
					if($nuovo>0) {
						$this->addSeenSku($nuovo);
						$skuCount++;
					}
				}
			} catch (\Throwable $e) {
				$this->error('set','exception',$e);
			}
		}
		$this->report('set','Iterator Count: '.$iterator);
		$this->report('set','Product Count: '.$productCount);
		$this->report('set','Sku Count: '.$skuCount);
		$this->report('set','Azzero Skus non visitati');

		if(count($this->getSeenSkus())  == 0){
			throw new BambooException('seenSkus contains 0 elements');
		}
		$res = $this->app->dbAdapter->query("SELECT ds.id
                                      FROM DirtySku ds, DirtyProduct dp, ProductSku ps
                                      WHERE
	                                      ps.productId = dp.productId AND
	                                      ps.productVariantId = dp.productVariantId AND
	                                      ds.dirtyProductId = dp.id AND
	                                      ps.shopId = ds.shopId AND
	                                      ds.productSizeId = ps.productSizeId AND
	                                      dp.fullMatch = 1 AND
	                                      ds.qty != 0 AND
	                                      ps.shopId = ?", [$this->getShop()->id])->fetchAll();

		$this->report( "findZeroSkus", "Product not at 0: " . count($res), []);

		$i = 0;
		foreach ($res as $one) {
			if (!in_array($one['id'], $this->getSeenSkus())) {
				$qty = $this->app->dbAdapter->update("DirtySku",["qty"=>0,"changed"=>1,"checksum"=>null],$one);
				$i++;
			}
		}
		$this->report("findZeroSkus", "Product set 0: " . $i, []);
		fclose($f);
	}

	/**
	 * Viene eseguita sui file in cui vengono inviati solo i file modificati
	 * @param $file
	 * @throws \bamboo\core\exceptions\RedPandaDBALException
	 */
	public function revise($file)
	{
		$iterator = 0;
		$separator = $this->config->fetch('filesConfig', 'separator') ? $this->config->fetch('filesConfig', 'separator') : $this->genericConfig->fetch('filesConfig', 'separator');
		$delimiter = $this->config->fetch('filesConfig', 'delimiter') ? $this->config->fetch('filesConfig', 'delimiter') : $this->genericConfig->fetch('filesConfig', 'delimiter');

		$mapping = $this->genericConfig->fetch('filesConfig', 'mapping');
		$notEmptySku = $this->genericConfig->fetch('filesConfig', 'control')['skuNotEmpty'];

		$relationshipMapping = $this->genericConfig->fetch('filesConfig', 'relationship');
		$productMapping = $this->genericConfig->fetch('filesConfig', 'product');
		$productExtendMapping = $this->genericConfig->fetch('filesConfig', 'productExtend');
		$photoMapping = $this->genericConfig->fetch('filesConfig', 'photos');
		$detailMapping = $this->genericConfig->fetch('filesConfig', 'photos');
		$skuMapping = $this->genericConfig->fetch('filesConfig', 'sku');

		$productKeysMap = $this->config->fetch('filesConfig', 'product')['keys'];
		$skuKeys = $this->config->fetch('filesConfig', 'sku')['keys'];

		$extendChecksums = $this->app->dbAdapter->query('SELECT checksum FROM DirtyProductExtend WHERE shopId = ?', [$this->getShop()->id])->fetchAll(\PDO::FETCH_COLUMN, 0);
		$extendChecksums = array_flip($extendChecksums);

		$skusData = $this->app->dbAdapter->query('SELECT extSkuId, checksum FROM DirtySku WHERE shopId = ?', [$this->getShop()->id])->fetchAll();
		$skusChecksums = [];
		foreach($skusData as $val){
			$skusChecksums[$val['checksum']] = $val['extSkuId'];
		}
		unset($skusData);
		$f = fopen($file,'r');
		fgets($f);
		fgets($f);

		while (($values = fgetcsv($f, 0, $separator, $delimiter)) !== false) {
			if(empty(trim($values[0]))) continue;
			$iterator++;
			try {
				$values = $this->mapValues($values,$mapping);

				/** Check if this is a new parentProduct or an existing one */
				$relationshipVal = $this->mapKeys($values, $relationshipMapping);

				switch (trim($relationshipVal['relationship'])) {
					case 'parent':
						continue 2;
						break;
					case '';
					case 'sku';
						$this->reviseSingleSku($skuMapping,$values);
						continue 2;
					default;
				}

				$workingProduct = $this->mapKeys($values, $productMapping);

				/** estraggo le chiavi specifiche di questo friend e ne faccio il checksum */
				$productKeys = $this->mapKeys($workingProduct, $productKeysMap);
				$keysChecksum = md5(implode('::', $productKeys));

				/** verifico nella lista delle chiavi se questo prodotto è già stato inserito, se si loggo il warning e salto tutto */
				$workingProduct = $this->app->dbAdapter->select('DirtyProduct', ['keysChecksum' => $keysChecksum, 'shopId' => $this->getShop()->id])->fetchAll();

				if (count($workingProduct) != 1) {
					//prodotto non presente, loggere!
					//serve ADD
					continue;
				}

				\Monkey::app()->repoFactory->beginTransaction();

				/** riempio tutte le varie tabelle che orrorrono, ormai non devo più fare controlli sul prodotto */
				/** productExtend */
				$extend = $this->mapKeys($values, $productExtendMapping);
				$extend['checksum'] = implode("::",$extend + ['dirtyProductId'=>$workingProduct['id']]);
				if(!isset($extendChecksums[$extend['checksum']])) {
					$this->app->dbAdapter->update('DirtyProductExtend',['dirtyProductId'=>$workingProduct['id']],$extend);
				}

				/** productPhoto, verifico se è aggiornato altrimenti aggiungo foto*/
				$photos = $this->mapKeys($values, $photoMapping);
				$photosChecksum = md5(implode('::', $photos + ['dirtyProductId' => $workingProduct['id']]));
				if($workingProduct['photosChecksum'] != $photosChecksum) {
					$dbPhotos = $this->app->dbAdapter->select('DirtyPhoto',['dirtyProductId'=>$workingProduct['id']])->fetchAll();
					$i=0;
					foreach ($photos as $key => $photo) {
						$i++;
						if (empty($photo)) {
							if(isset($dbPhotos[$i])) $photos[$key] = $dbPhotos[$i]['url'];
							continue;
						}
						if (isset($dbPhotos[$i]) && $dbPhotos[$i]['worked'] == 1) continue;
						$this->app->dbAdapter->insert('DirtyPhoto', ['dirtyProductId' => $workingProduct['id'], 'url' => $photo, 'position' => $i, 'shopId' => $this->getShop()->id]);
					}
					$photosChecksum = md5(implode('::', $photos));
					$this->app->dbAdapter->update('DirtyProduct', ['photosChecksum' => $photosChecksum], ['id' => $workingProduct['id']]);
				}

				/** productPhoto, verifico se è aggiornato altrimenti aggiungo foto*/
				$details = $this->mapKeys($values, $detailMapping);
				$detailsChecksum = md5(implode('::', $details + ['dirtyProductId' => $workingProduct['id']]));
				if($workingProduct['detailsChecksum'] != $detailsChecksum) {
					$dbDetails = $this->app->dbAdapter->select('DirtyDetail',['dirtyProductId'=>$workingProduct['id']])->fetchAll();
					$dbKeyedDetails = [];
					foreach($dbDetails as $dbDetail){
						$dbKeyedDetails[$dbDetails['label']] = $dbDetail;
					}
					$i=0;
					foreach ($details as $key => $detail) {
						$i++;
						if (empty($detail)) {
							if(isset($dbKeyedDetails[$key])) $details[$key] = $dbKeyedDetails[$key]['content'];
							continue;
						}
						if (isset($dbKeyedDetails[$key]) && $dbDetails[$i]['worked'] == 1) {
							$this->app->dbAdapter->update('DirtyDetail',['id'=>$dbDetails[$i],'dirtyProductId'=>$workingProduct['id']],['label'=>$key,'content'=>$detail]);
						} else {
							$this->app->dbAdapter->insert('DirtyDetail', ['dirtyProductId' => $workingProduct['id'], 'label' => $key, 'content' => $detail]);
						}

					}
					$photosChecksum = md5(implode('::', $photos));
					$this->app->dbAdapter->update('DirtyProduct', ['photosChecksum' => $photosChecksum], ['id' => $workingProduct['id']]);
				}

				\Monkey::app()->repoFactory->commit();

				/** a questo punto working product dovrebbe essere valorizzato ed avere un 'id' inserisco quindi gli skus */
				/** controllo i campi obbligatori */
				foreach($this->mapKeys($values,$notEmptySku) as $name => $field) {
					if(empty($field)) {
						$this->warning('Add - Product','Product mandatory field empty: '.$name.' Row REFUSED',$values);
						$this->app->dbAdapter->insert('ImportItemsReview',['shopId'=>$this->getShop()->id,
						                                                   'jobId'=>$this->jobId,
						                                                   'jobExecutionId'=>$this->jobExecutionId,
						                                                   'action'=>'add',
						                                                   'object'=>'sku',
						                                                   'fileType'=>'csv',
						                                                   'file'=>$file,
						                                                   'data'=>json_encode($values)]);
					}
					continue;
				}

				$skuVal = $this->mapKeys($values, $skuMapping);
				$skuVal['dirtyProductId'] = $workingProduct['id'];
				$skuVal['checksum'] = implode('::', $skuVal);

				if (isset($skusChecksums[$skuVal['sku']])) {
					//sku esistente ed identico
				} else if(in_array($skuVal['extSkuId'],$skusChecksums)) {
					//sku esistente ma con diverso checksum
					unset($skuVal['dirtyProductId']);
					$this->app->dbAdapter->update('DirtySku', $skuVal+['changed'=>1],$workingProduct['id']);
				} else {
					//sku inesistente
					$this->app->dbAdapter->insert('DirtySku', $skuVal+['changed=>1']);
				}

			} catch (\Throwable $e) {
				\Monkey::app()->repoFactory->rollback();
				$this->error('revise','Exception, rolled back',$e);
			}
		}
		fclose($f);
	}

	/**
	 * Update a single sku, usually on quantity
	 * @param $skuMapping
	 * @param $values
	 * @return bool
	 * @throws \bamboo\core\exceptions\RedPandaDBALException
	 */
	public function reviseSingleSku($skuMapping,$values,$chechsum = null)
	{
		$skuVal = $this->mapKeys($values, $skuMapping);
		$extSkuId =  ['extSkuId' => $skuVal['extSkuId']];
		$existingSku = $this->app->dbAdapter->select('DirtySku',$extSkuId + ['shopId'=>$this->getShop()->id])->fetchAll();
		if(count($existingSku) != 1) {
			$this->error('reviseSingleSku','DirtySku not found for '.$skuVal['extSkuId'],$values);
			return false;
		} else {
			$existingSku = $existingSku[0];
		}
		$this->addSeenSku($existingSku['id']);
		foreach ($skuVal as $key=>$val){
			if(!empty($val)) {
				if($key == 'qty') {
					$op = substr($val,0,1);
                    if($op == '+') {
                        $operator = substr($val, 1);
						$existingSku[$key] += $operator;
					} else if ($op == '-') {
                        $operator = substr($val, 1);
                        $existingSku[$key] -= $operator;
					} else {
						$existingSku[$key] = $val;
					}
				} else {
					$existingSku[$key] = $val;
				}
				$values[$key] = $existingSku[$key];
			}
		}
		$skuVal = $this->mapKeys($values, $skuMapping);
		$skuVal['dirtyProductId'] = $existingSku['dirtyProductId'];
		$existingSku['checksum'] = $chechsum ?? md5(implode('::', $values));
		$id = $existingSku['id'];
		unset($existingSku['id']);
		$rows = $this->app->dbAdapter->update('DirtySku',$existingSku+['changed'=>1],['id'=>$id]);
		$this->report('reviseSingleSku','Updated '.$existingSku['extSkuId'].' changed rows: '.$rows);
		return true;
	}

	protected function addSeenSku($id){
		if(!isset($this->seenSkus)) $this->seenSkus = [];
		$this->seenSkus[] = $id;
	}

	protected function getSeenSkus() {
		if(!isset($this->seenSkus)) $this->seenSkus = [];
		return $this->seenSkus;
	}

	/**
	 * Retrive assoc map values by matching an assoc map array to a scalar values array
	 * @param array $values
	 * @param array $map
	 * @return array
	 */
	public function mapValues(array $values, array $map)
	{
		$newValues = [];
		foreach ($map as $key => $val) {
			$newValues[$key] = utf8_encode($values[$val]);
		}

		return $newValues;
	}

    public function sendPhotosToWork()
    {

    /*  $ftpDestination = new CFTPClient($this->app, $this->config->fetch('miscellaneous', 'destFTPClient'));
        if(!$ftpDestination->changeDir('/shootImport/incoming/DellaMartira')){
            throw new RedPandaFTPClientException('Could not change dir');
        };
        $photos = glob($this->app->rootPath().$this->app->cfg()->fetch('paths','image-temp-folder').'/*');
        $got = 0;
        $this->log('REPORT','PhotoUpload','Photos to send: '.count($photos).'',null);
        foreach($photos as $photo){
            if($got % 100 == 0) {
                $this->log('REPORT','PhotoUpload','Got '.$got.' photos till now',null);
            }
            try{
                if($ftpDestination->put($photo,basename($photo))){
                    $got++;
                    unlink($photo);
                }
            }catch (\Throwable $e){ $this->log('WARNING','PhotoUpload','Error while putting/deleting photo',$e); }
        }
        $this->log('REPORT','PhotoUpload','Photos sent: '.$got.'',null);
    */
    }
}