<?php
namespace bamboo\ecommerce\offline\productsync\import;
use bamboo\core\base\CFTPClient;
use bamboo\core\exceptions\RedPandaException;
use bamboo\core\exceptions\RedPandaFTPClientException;
use bamboo\core\utils\amazonPhotoManager\ImageEditor;

/**
 * Class ABlueSealProductImporter
 * @package bamboo\htdocs\pickyshop\import\productsync
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, 03/12/2015
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
abstract class ABlueSealSimpleXMLProductImporter extends AProductImporter
{
	protected $fileName;

	protected $xml;
	/** @var \DOMDocument $doc */
	protected $doc;

	protected $dirtyProducts = [];

	public function run($args = null)
	{
		$this->report("Run", "Import START", "Inizio importazione " . $this->shop->name);

		$this->report("Run", "Fetch Filse", "Carico i files");
		$this->fetchFiles();
		$this->report("Run", "Read Files", "Leggo i files");
		$this->readFiles();
		$this->report("Run", "Read Main", "Leggo il file Main cercando Prodotti");
		$this->readMain();
		$this->report("Run", "Find Zero Skus", "Azzero le quantità dei prodotti non elaborati");
		$this->findZeroSkus();

		$this->report("Run", "Calling Custom Methods", "Chiamo metodi per lo specifico friend");
		$this->customFill();

		$this->report("Run", "Calling Send Photos to Work", "Chiamo il metodo per il dowload delle foto");
		$this->sendPhotosToWork();


		$this->report("Run", "Save Files", "Salvataggio del file elaborato");
		$this->saveFiles();
		$this->report("Run", "Import END", "Inizio importazione " . $this->shop->name);

		echo 'done';
	}

	public abstract function customFill();

	/**
	 * @return bool
	 */
	public function fetchFiles()
	{
		$notLocal = false;
		if ($this->config->fetch('files', 'main')['position'] != 'local') {
			//TODO FETCH FROM FTP OR URL
			return false;
		} else {
			$files = glob($this->app->rootPath().$this->app->cfg()->fetch('paths',  'productSync') . '/'  . $this->config->fetch('files', 'main')['location'] . '/*.[xX][mM][lL]');
			if (!isset($files[0])) return false;
			$xml = $files[0];
			$time = filemtime($xml);
			foreach ($files as $file) {
				if (filemtime($file) > $time) {
					$xml = $file;
					$time = filemtime($file);
				}
			}
			$this->report("fetchFiles", "Files usato = " . $xml, null);
			$size = filesize($xml);
			while ($size != filesize($xml)) {
				sleep(1);
				$size = filesize($xml);
			}
			$this->xml = $xml;

			return true;
		}
	}

	/**
	 *
	 */
	public function readFiles()
	{
		$this->doc = new \DOMDocument();
		$this->doc->load($this->xml);
	}

	/**
	 * @param \DOMElement $element
	 * @param $field
	 * @return null|string
	 */
	public function getUniqueElementNodeValue(\DOMElement $element, $field)
	{
		return $element->getElementsByTagName($field)->item(0) !== null ? $element->getElementsByTagName($field)->item(0)->nodeValue : null;
	}

	/**
	 * @param \DOMElement $item
	 * @param array $dirtyProduct
	 * @return int
	 */
	public function updateDetails(\DOMElement $item, array $dirtyProduct)
	{
		$this->app->dbAdapter->delete('DirtyDetail', ["dirtyProductId" => $dirtyProduct['id']]);
		$details = 0;
		foreach ($item->getElementsByTagName('detail') as $detail) {
			$det = [];
			/** @var \DOMElement $detail */
			if($detail->getElementsByTagName('content')->length == 1 && $detail->getElementsByTagName('content')->item(0)) $det['content'] = trim($detail->getElementsByTagName('content')->item(0)->textContent);
			if(isset($det['content']) && empty($det['content'])) continue;
			$det['label'] = $this->getUniqueElementNodeValue($detail,'label');
			$det['dirtyProductId'] = $dirtyProduct['id'];
			$this->app->dbAdapter->insert('DirtyDetail', $det);
			$details++;
		}
		return $details;
	}

	/**
	 * @param \DOMElement $item
	 * @param array $dirtyProduct
	 * @return int
	 * @throws \bamboo\core\exceptions\RedPandaDBALException
	 */
	public function updatePhotos(\DOMElement $item, array $dirtyProduct)
	{
		$photos = 0;
		$readPhotos = $this->app->dbAdapter->query("  SELECT id, url, worked
													  FROM DirtyPhoto
													  WHERE dirtyProductId = ? and shopId = ?", [$dirtyProduct['id'],$this->getShop()->id])->fetchAll();
		foreach ($item->getElementsByTagName('photo') as $detail) {
			/** @var \DOMElement $detail */
			$url = $detail->getElementsByTagName('url')->length > 0 ? $detail->getElementsByTagName('url')->item(0)->nodeValue : "";
			$path = $detail->getElementsByTagName('path')->length > 0 ? $detail->getElementsByTagName('path')->item(0)->nodeValue : "";
			if (empty($url) && empty($path)) continue;
			$photos++;
			$location = empty($url) ? 'path' : 'url';
			$url = ${$location};
			foreach($readPhotos as $readPhoto){
				if($readPhoto['url'] == $url) continue;
				else {
					$this->app->dbAdapter->insert('DirtyPhoto',['dirtyProductId'=>$dirtyProduct['id'],'location'=>$location,'url'=>$url,'worked'=>0,'shopId'=>$this->getShop()->id]);
				}
			}
		}
		return $photos;
	}

	public function readMain()
	{

		try {
			$rss = $this->doc->getElementsByTagName('rss');
			$xsd = $rss->item(0)->getAttribute('xmlns');
			if ($this->doc->schemaValidateSource(file_get_contents($xsd)) !== true) {
				throw new RedPandaException('File Validation failed');
				// log
			}
			if ($this->doc->getElementsByTagName('shopId')->item(0)->nodeValue != $this->getShop()->id) {
				throw new RedPandaException('Wrong Shop id in file');
			}
			$dateTime = new \DateTime($this->doc->getElementsByTagName('date')->item(0)->nodeValue);
			if ((new \DateTime())->diff($dateTime)->days > 3) {
				//log Warning
				throw new RedPandaException('File too old to be good');
			}
			$this->dirtyProducts = [];
			foreach ($this->doc->getElementsByTagName('item') as $item) {
				try {
					\Monkey::app()->repoFactory->beginTransaction();
					$dirtyProduct= [];
					$dirtyProduct['shopId'] = $this->getShop()->id;
					/** @var \DOMElement $item */
					$dirtyProduct['extId'] = $this->getUniqueElementNodeValue($item, 'id');
					$dirtyProduct['brand'] = $this->getUniqueElementNodeValue($item, 'brand');
					$dirtyProduct['itemno'] = $this->getUniqueElementNodeValue($item, 'cpf');
					$dirtyProduct['var'] = $this->getUniqueElementNodeValue($item, 'var');
					$dirtyProduct['value'] = $this->getUniqueElementNodeValue($item, 'value');
					$dirtyProduct['price'] = $this->getUniqueElementNodeValue($item, 'price');
					$dirtyProduct['salePrice'] = $this->getUniqueElementNodeValue($item, 'salePrice');
					$dirtyProduct['text'] = $this->getUniqueElementNodeValue($item, 'details');
					$dirtyProduct['clipboard'] = $this->getUniqueElementNodeValue($item, 'photos');

					$dirtyProduct['checksum'] = md5(implode($dirtyProduct));

					$storedProduct = $this->app->dbAdapter->select("DirtyProduct", ['shopId' => $this->getShop()->id, 'checksum' => $dirtyProduct['checksum']])->fetchAll();
					if (count($storedProduct) == 1) {
						$dirtyProduct = $storedProduct[0];
					} elseif (count($storedProduct) == 0) {
						//INSERT OR UPDATE
						$existing = $this->app->dbAdapter->query("  SELECT * FROM DirtyProduct WHERE id IN (
                                                        SELECT DISTINCT(id)
														FROM DirtyProduct
														WHERE shopId = ? AND (
																( extId = ifnull(?,FALSE) AND
																  brand = ? AND
																  var = ? ) OR
															    ( itemno = ? AND
														          var = ? AND
														          brand = ? ) ) )", [$this->getShop()->id, $dirtyProduct['extId'],$dirtyProduct['extId'], $dirtyProduct['brand'], $dirtyProduct['var'], $dirtyProduct['itemno'], $dirtyProduct['var'], $dirtyProduct['brand']])->fetchAll();
						if (count($existing) == 1) {
							//UPDATE
							$existing = $existing[0];
							$dirtyProduct['id'] = $existing['id'];
							//CHECK IF PHOTOS AND DETAILS ARE TO BE UPDATED
							$newDetailChecksum = md5($dirtyProduct['text']);
							if ($existing['detailsChecksum'] == $newDetailChecksum) {
								$this->updateDetails($item, $existing);
								$dirtyProduct['detailsChecksum'] = $newDetailChecksum;
							}
							$newPhotosChecksum = md5($dirtyProduct['clipboard']);
							if ($existing['photosChecksum'] == $newPhotosChecksum) {
								$this->updatePhotos($item, $existing);
								$dirtyProduct['photosChecksum'] = $newPhotosChecksum;
							}

							$this->app->dbAdapter->update("DirtyProduct", array_diff($dirtyProduct,$existing),['id'=>$existing['id'],'shopId'=>$this->getShop()->id]);
							$dirtyProduct['sizesChecksum'] = $existing['sizesChecksum'];

						} elseif (count($existing) == 0) {
							//INSERT
							$dirtyProduct['detailsChecksum'] = md5($dirtyProduct['text']);
							$dirtyProduct['photosChecksum'] = md5($dirtyProduct['clipboard']);
							$dirtyProduct['sizesChecksum'] = false;
							$dirtyProduct['dirtyStatus'] = 'E';
							$dirtyProduct['id'] = $this->app->dbAdapter->insert("DirtyProduct", $dirtyProduct);

							$this->updateDetails($item, $dirtyProduct);
							$this->updatePhotos($item, $dirtyProduct);
						} else {
							throw new RedPandaException('FOUND more than one item for DirtyProduct');
						}
					} else {
						throw new RedPandaException('Found more than one item');
					}
					\Monkey::app()->repoFactory->commit();
					\Monkey::app()->repoFactory->beginTransaction();
					$newSizesChecksum = md5($this->getUniqueElementNodeValue($item,'sizes'));
					if($dirtyProduct['sizesChecksum'] != $newSizesChecksum){
						//update sizes since they are different
						foreach ($item->getElementsByTagName('size') as $size) {
							/** @var \DOMElement $size */
							$dirtySku = [];
							$dirtySku['text'] = $size->nodeValue;
							$dirtySku['checksum'] = md5($dirtySku['text']);
							$existingSku = $this->app->dbAdapter->select('DirtySku',['shopId'=>$this->getShop()->id,'dirtyProductId'=>$dirtyProduct['id'],'checksum'=>$dirtySku['checksum']])->fetchAll();
							$found = count($existingSku);
							if(count($existingSku) == 1) {
								//NOT CHANGED
								$dirtySku = $existingSku;
							} elseif(count($existingSku) == 0) {
								$dirtySku['extSizeId'] = $size->getAttribute('id');
								$dirtySku['extSkuId'] = $this->getUniqueElementNodeValue($size,'skuId');
								$dirtySku['value'] = $this->getUniqueElementNodeValue($size,'cost');
								$dirtySku['price'] = $this->getUniqueElementNodeValue($size,'price');
								$dirtySku['salePrice'] = $this->getUniqueElementNodeValue($size,'salePrice');
								$dirtySku['qty'] = $this->getUniqueElementNodeValue($size,'stock');
								$dirtySku['size'] = $this->getUniqueElementNodeValue($size,'name');
								$dirtySku['changed'] = 1;
								$existingSku = $this->app->dbAdapter->query("	SELECT *
																				from DirtySku
																				WHERE shopId = ? and
																					  dirtyProductId = ? and 
																				        size = ?",
																				[$this->getShop()->id,
																				$dirtyProduct['id'],
																				$dirtySku['size']])->fetchAll();
								if(count($existingSku) == 1) {
									$this->app->dbAdapter->update("DirtySku",$dirtySku,['id'=>$existingSku[0]['id'],
									                                                    'shopId'=>$this->getShop()->id,
									                                                    'dirtyProductId'=>$dirtyProduct['id']]);
								} elseif(count($existingSku) == 0) {
									$dirtySku['shopId'] = $this->getShop()->id;
									$dirtySku['dirtyProductId'] = $dirtyProduct['id'];
									$dirtySku['status'] = 'E';
									$this->app->dbAdapter->insert("DirtySku",$dirtySku);
								} else {
									throw new RedPandaException('More than one sku matched');
									//ERROR
								}
							}

						}

						$this->app->dbAdapter->update("DirtyProduct", ['sizesChecksum'=>$newSizesChecksum],['id'=>$dirtyProduct['id']]);
					}
					\Monkey::app()->repoFactory->commit();
					$this->dirtyProducts[] = $dirtyProduct['id'];
				} catch(\Throwable $e) {
					\Monkey::app()->repoFactory->rollback();
					if(isset($dirtyProduct)) $this->error('Working Item','Last Worked Product', $dirtyProduct);
					if(isset($dirtySku)) $this->error('Working Item','Last Worked $sku', $dirtySku);
					$this->error('Working Item',$e->getMessage(),$e);
				}

			}
		} catch (\Throwable $e) {
			\Monkey::app()->repoFactory->rollback();
			$this->error('Working Items',$e->getMessage(),$e);
		}

		return true;

	}

	public function sendPhotosToWork()
	{
		$ftpDestination = new CFTPClient($this->app, $this->config->fetch('miscellaneous', 'destFTPClient'));
		if(!$ftpDestination->changeDir('/shootImport/incoming/'.$this->getShop()->name)){
			throw new RedPandaFTPClientException('Could not change dir');
		};

		$photos = $this->app->dbAdapter->query("SELECT dph.id,
														dirtyProductId,
														dp.productId,
														dp.productVariantId,
														p.dummyPicture,
														url,
														location,
														position
												FROM DirtyPhoto dph,
													DirtyProduct dp,
													Product p
												WHERE p.id = dp.productId and
														p.productVariantId = dp.productVariantId and
														dph.dirtyProductId = dp.id and
														dph.shopId = ? and
														dph.worked = 0",
			[$this->getShop()->id])->fetchAll();

		$imager = new ImageEditor();
		$widht = 500;
		$dummyFolder = $this->app->rootPath().$this->app->cfg()->fetch('paths', 'dummyFolder') . '/';
		$this->report('Sending Photos','Found '.count($photos).' to work');
		foreach($photos as $photo){
			try {
				if (!file_exists($photo['url'])) throw new RedPandaException('File not found when it should be there: %s', [$photo['url']]);
				$fileName = pathinfo($photo['url']);
				$name = $photo['productId'] . '-' . $photo['productVariantId'] . '__' . $fileName['filename'];
				if (!empty($photo['position'])) $name .= "_" . $photo['position'];
				$name .= '.' . $fileName['extension'];

				if ($ftpDestination->put($photo['url'], $name)) {
					$this->report( 'Send Photo', 'Photo ' . $name . ' sent to ftp');
					$this->app->dbAdapter->update('DirtyPhoto', ['worked' => 1], ['id' => $photo['id']]);
				} else {
					$this->error( 'Send Photo', 'Error sending photo ' . $name . ' sent to ftp');
					continue;
				}
				if ($photo['dummyPicture'] == 'bs-dummy-16-9.png') {
					$imager->load($photo['url']);
					$imager->resizeToWidth($widht);
					$dummyName = rand(0, 9999999999) . '.' . $fileName['extension'];
					$imager->save($dummyFolder . '/' . $dummyName);
					$this->app->dbAdapter->update('Product', ['dummyPicture' => $dummyName], ['id' => $photo['productId'], 'productVariantId' => $photo['productVariantId']]);
					$this->report('PhotoDownload', 'Set dummyPicture: ' . $dummyName);
				}
			} catch(\Throwable $e){
				$this->error('Error Sending Photos',' '.count($photo),$e);
			}
		}
	}

	/**
	 *
	 */
	public function findZeroSkus()
	{
		if(count($this->dirtyProducts)  == 0){
			throw new RedPandaException('Seen Products contains 0 elements');
		}
		$res = $this->app->dbAdapter->query("SELECT distinct dp.id as dirtyProductId, dp.shopId as shopId
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

		$this->report("findZeroSkus", "Product to set 0: " . count($res), []);
		$i = 0;
		foreach ($res as $one) {
			if (!in_array($one['dirtyProductId'], $this->dirtyProducts)) {
				$i++;
				$qty = $this->app->dbAdapter->update("DirtySku",["qty"=>0,"changed"=>1,"checksum"=>null],$one);
			}
		}
		$this->report("findZeroSkus", "Product set 0: " . $i, []);
	}

	/**
	 *
	 */
	public function saveFiles()
	{
		$now = new \DateTime();
		$name = $this->app->rootPath().$this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/done/' . $now->format('YmdHis') . '.tar';
		$phar = new \PharData($name);
		$phar->addFile($this->xml);

		if ($phar->count() > 0) {
			$phar->compress(\Phar::GZ);
		}

		unlink($this->xml);
		unlink($name);
	}

}