<?php

namespace bamboo\offline\productsync\import\standard;

use bamboo\core\utils\amazonPhotoManager\ImageEditor;
use bamboo\domain\entities\CDirtyProduct;
use bamboo\domain\entities\CImporterConnector;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CShop;
use bamboo\core\base\CConfig;
use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\exceptions\BambooConfigException;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooFileException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\jobs\ACronJob;
use bamboo\core\utils\slugify\CSlugify;
use bamboo\domain\repositories\CProductNameTranslationRepo;
use bamboo\ecommerce\offline\productsync\import\IBluesealProductImporter;
use bamboo\core\base\CFTPClient;

/**
 * Class ABluesealProductImporter
 * @package bamboo\htdocs\pickyshop\import\blueseal
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, 13/01/2016
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
abstract class ABluesealProductImporter extends ACronJob implements IBluesealProductImporter
{
    /** @var CConfig $genericConfig */
    protected $genericConfig;
    /** @var CShop $shop */
    protected $shop = null;
    /** @var CConfig $config */
    protected $config;

    protected $main;
    /** @var array $mainFilenames */
    protected $mainFilenames = [];
    /** @var string $action */
    protected $action;

    /**
     * @param null $args
     * @return bool
     * @throws \Throwable
     */
    public function run($args = null)
    {
        try {
            $this->report('Runner', 'Job Starting with args: ' . $args);

            $this->initImporter($args);

            $this->importDataIntoDirty($args);

            $this->workDirtyData($args);

            $this->report('Runner', 'Job Done with args: ' . $args);

            return true;
        } catch (\Throwable $e) {
            iwesMail('it@iwes.it',
                'Importer Error for ' . $this->getShop()->name,
                "Unknown GENERAL error!\n\n\n" . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * init the configuration of the current importer
     * @param null $args
     */
    protected function initImporter($args = null) {
        $this->report('initImporter', 'fetchShop launch');
        // use the id passed by the job scheduler to create the $this->shop
        $this->fetchShop($args);
        $this->report('initImporter', 'fetchShop end');

        $this->report('initImporter', 'readConfig launch');
        // get the configuration from file, inside the "config" dir, using the [$this->shop->name].json as filename
        $this->readConfig();
        $this->report('initImporter', 'readConfig end',$this->config);
    }

    /**
     * do all the work to import data into Dirty Tables (fetch - work - save files)
     * @param null $args
     */
    protected function importDataIntoDirty($args = null) {

        $this->report('importDataIntoDirty', 'fetchFiles launch');
        // get files containing data we need to import
        $this->fetchFiles();
        $this->report('importDataIntoDirty', 'fetchFiles end');

        // $this->mainFilenames is populated by $this->fetchFiles()
        foreach ($this->mainFilenames as $file) {
            try {
                $this->report('importDataIntoDirty', 'readFile launch', $file);
                $this->readFile($file);
                $this->report('importDataIntoDirty', 'readFile end');

                $this->report('importDataIntoDirty', 'processFile launch');
                $this->processFile($file);
                $this->report('importDataIntoDirty', 'processFile end');

                $this->report('importDataIntoDirty', 'saveFiles launch');
                $this->saveFile($file, true);
                $this->report('importDataIntoDirty', 'saveFiles end');

            } catch (BambooFileException $e) {
                $this->error('importDataIntoDirty', 'File ' . $file . ' not valid or corrupted, refused!', $e);

                $this->error('importDataIntoDirty', 'Saving file as error', $file);
                iwesMail('it@iwes.it',
                    'Importer Error for ' . $this->getShop()->name,
                    "File " . $file . " not valid or corrupted, refused!\n\n\n" . $e->getMessage());
                $tar = $this->saveFile($file, false);
                $this->error('importDataIntoDirty', 'Saved file as error', $tar);

            } catch (BambooOutOfBoundException $e) {
                $this->error('importDataIntoDirty', 'File ' . $file . ' not valid, refused!', $e);
                //stopping importer
                iwesMail('it@iwes.it',
                    'Importer Error for ' . $this->getShop()->name,
                    "File " . $file . " not valid or corrupted, File Validation Issues, stopping importer!\n\n\n" . var_export($e, true));
                $this->error('importDataIntoDirty', 'File Validation Issues, stopping importer', $file);
                $this->jobExecution->job->isActive = 0;
                $this->jobExecution->job->update();

                $this->error('importDataIntoDirty', 'Saving file as error', $file);
                $tar = $this->saveFile($file, false);
                $this->error('importDataIntoDirty', 'Saved file as error', $tar);

            } catch (\Throwable $e) {
                //now what?
                $this->error('importDataIntoDirty', 'File processing error for ' . $file, $e);
                iwesMail('it@iwes.it',
                    'Importer Error for ' . $this->getShop()->name,
                    "File " . $file . " working error, unknown error!\n\n\n" . var_export($e, true));
                $tar = $this->saveFile($file, false);
            }
        }
    }

    /**
     * work and clean the dirty data
     * @param null $args
     */
    protected function workDirtyData($args = null) {
        $this->report('Runner', 'updateDictionaries launch');
        $this->updateDictionaries();
        $this->report('Runner', 'updateDictionaries end');

        $this->report('Runner', 'createProducts launch');
        $this->createProducts();
        $this->report('Runner', 'createProducts end');

        $this->report('Runner', 'checkForNewDetails launch');
        $this->checkForNewDetails();
        $this->report('Runner', 'checkForNewDetails end');

        $this->report('Runner', 'fetchPhotos launch');
        $this->fetchPhotos();
        $this->report('Runner', 'fetchPhotos end');

        $this->report('Runner', 'sendPhotos launch');
        $this->sendPhotos();
        $this->report('Runner', 'sendPhotos end');
    }

    /**
     * fetch the shop
     *
     * @param $args
     * @return CShop|\bamboo\core\db\pandaorm\entities\AEntity
     * @throws BambooConfigException
     */
    public function fetchShop($args)
    {
        if ($args === null) throw new BambooConfigException('No Shop Selected in args, you need to set up the jobs with the shop id in "args" field');
        if (!$this->shop instanceof CShop) {
            if (is_numeric($args)) $this->shop = \Monkey::app()->repoFactory->create('Shop')->findOne(['id' => $args]);
            else  $this->shop = \Monkey::app()->repoFactory->create('Shop')->findOne(['name' => $args]);

            if (!$this->shop instanceof CShop) throw new BambooConfigException('Shop could not be fetched with arg: %s ', [json_encode($args)]);
        }

        return $this->shop;
    }

    /**
     * @throws BambooConfigException
     */
    public function readConfig()
    {
        $filePath = __DIR__ . '/config';
        $filePath .= '/' . $this->getShop()->name . '.json';

        if (!file_exists($filePath)) throw new BambooConfigException('Configuration not found for Importer: ' . $filePath);

        $this->config = new CConfig($filePath);
        $this->config->load();
    }

    /**
     * @return CShop
     */
    public function getShop()
    {
        return $this->shop;
    }

    /** find files anywhere */
    public function fetchFiles()
    {
        switch ($this->config->fetch('filesConfig', 'location')) {
            case "local":
                $this->fetchLocalFiles();
                break;
            case "ftp":
                $this->fetchFTPFiles();
                break;
            case "url":
                $this->fetchWebFiles();
                break;
            default:
                throw new BambooConfigException('Wrong configuration for fetching files');
        }
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
            $this->mainFilenames = $files;
            $this->report("fetchFiles", "Files usato: " . implode(', ', $files), null);
        }
    }

    /**
     * Fetch file from ftp, copy it and set path on $this->mainF
     */
    protected function fetchFTPFiles()
    {
        $localDir = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->getShop()->name;

        $istructions = $this->config->fetch('filesConfig', 'istructions');

        $ftp = new CFTPClient($this->app, $this->config->fetchAll('filesFtpConfig'));
        $remoteDir = $istructions['remoteFolder'];
        $regex = $istructions['regex'];
        $ftp->changeDir($remoteDir);
        $list = $ftp->nList();

        foreach ($list as $one) {
            if (preg_match($regex, $one) == 1) {
                try {
                    if ($ftp->get($one, $localDir . '/' . $one)) {
                        $ftp->move($one, "done");
                    }
                } catch (\Throwable $e) {
                    $this->error('fetchFTPFiles', 'Could not get file', $one);
                }
            }

        }

        $this->fetchLocalFiles();
    }

    /**
     * Fetch file from WebUrl and save it locally, set path on $this->mainF
     */
    protected function fetchWebFiles()
    {
        $localDir = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->getShop()->name;

        $url = $this->config->fetch('filesConfig', 'istructions')['url'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        $retValue = curl_exec($ch);
        curl_close($ch);

        $filename = $localDir . '//' . time() . ($this->config->fetch('filesConfig', 'extension') ?? '.xml');
        if (empty($retValue)) {
            $this->warning('fetchWebFiles', 'Got Empty File!');
        } else {
            file_put_contents($filename, trim($retValue));
            $this->report("fetchWebFiles", "filename: " . $filename, null);
        }

        $this->fetchLocalFiles();
    }

    /**
     *  read files and validate them
     * @param $file
     * @return mixed
     */
    public abstract function readFile($file);

    /** read main file and insert/update items in DirtyColumns */
    public abstract function processFile($file);

    /**
     * Retrive assoc map values by matching a scalar map array to an assoc values array
     * @param array $values
     * @param array $map
     * @return array
     */
    protected function mapKeys(array $values, array $map)
    {
        $newKeys = [];
        foreach ($map as $val) {
            $newKeys[$val] = $values[$val];
        }

        return $newKeys;
    }

    /**
     * @param array $values
     * @param array $map
     * @return array
     */
    protected function mapDirectKeys(array $values, array $map)
    {
        $newKeys = [];
        foreach ($map as $key => $val) {
            $newKeys[$key] = $values[$val];
        }

        return $newKeys;
    }

    /**
     * Join multiple associative array in one (passed: array in array)
     * @param $arrays
     * @return array
     */
    protected function joinMultipleAssociativeArray($arrays){
        $fullArray = [];
        foreach ($arrays as $array){
            foreach ($array as $key => $val ){
                $fullArray[$key] = $val;
            }
        }
        return $fullArray;
    }

    /**
     *
     */
    protected function findZeroSkus($seenSkus)
    {
        if (count($seenSkus) == 0) {
            throw new BambooLogicException('seenSkus contains 0 elements');
        }
        $res = $this->app->dbAdapter->query("SELECT ds.id
                                                      FROM DirtySku ds 
                                                        JOIN DirtyProduct dp on ds.dirtyProductId = dp.id
                                                        JOIN ProductSku ps on ps.productId = dp.productId AND
                                                          ps.productVariantId = dp.productVariantId AND
                                                          ps.shopId = ds.shopId AND
                                                          ds.productSizeId = ps.productSizeId
                                                      WHERE
                                                          dp.fullMatch = 1 AND
                                                          ds.qty != 0 and
                                                          ps.shopId = ?", [$this->shop->id])->fetchAll();

        $this->report("findZeroSkus", "Seen Skus: " . count($seenSkus), []);
        $this->report("findZeroSkus", "Product not at 0: " . count($res), []);
        $i = 0;

        foreach ($res as $one) {
            if (!in_array($one['id'], $seenSkus)) {
                $qty = $this->app->dbAdapter->update("DirtySku", ["qty" => 0, "changed" => 1, "checksum" => null], $one);
                $i++;
            }
        }
        $this->report("findZeroSkus", "Product set 0: " . $i, []);
    }

    /**
     * save files
     * @param $file
     * @param $isGood
     * @return string
     */
    public function saveFile($file, $isGood)
    {
        $error = $isGood ? 'done' : 'err';
        $now = new \DateTime();
        $zipName = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $this->shop->name . '/import/' . $error . '/' . $now->format('YmdHis') . '_' . pathinfo($file)['filename'] . '.tar';
        $phar = new \PharData($zipName);

        $phar->addFile($file, pathinfo($file)['basename']);

        if ($phar->count() > 0) {
            /** @var \PharData $compressed */
            $compressed = $phar->compress(\Phar::GZ);
            if (file_exists($compressed->getPath())) {
                unlink($file);
                unlink($zipName);
            }
        }


        return $zipName;
    }

    /** update dictionaries */
    public function updateDictionaries()
    {
        $i = $this->updateBrandDictionary();
        $this->report('updateDictionaries', 'Brand terms inserted: ' . $i);

        $i = $this->updateSeasonDictionary();
        $this->report('updateDictionaries', 'Season terms inserted: ' . $i);

        $i = $this->updateCategoryDictionary();
        $this->report('updateDictionaries', 'Category terms inserted: ' . $i);

        $i = $this->updateTagDictionary();
        $this->report('updateDictionaries', 'Tag terms inserted: ' . $i);

        $i = $this->updateGereralColorDictionary();
        $this->report('updateDictionaries', 'Color terms inserted: ' . $i);

        $i = $this->updateSizeDictionary();
        $this->report('updateDictionaries', 'Size terms inserted: ' . $i);

        $i = $this->updateDetailDictionary();
        $this->report('updateDictionaries', 'Detail terms inserted: ' . $i);
    }

    /**
     * Fetch keys for dictionary
     */
    protected function updateBrandDictionary()
    {
        $this->app->dbAdapter->query("INSERT IGNORE INTO DictionaryBrand (shopId, term)
										SELECT DISTINCT shopId, brand
										FROM DirtyProduct
										WHERE shopId = ? AND dirtyStatus != 'C'", [$this->getShop()->id]);

        return $this->app->dbAdapter->countAffectedRows();
    }

    protected function updateSeasonDictionary()
    {
        $this->app->dbAdapter->query("INSERT IGNORE INTO DictionarySeason (shopId, term) 
                                        SELECT DISTINCT dpe.shopId, dpe.season 
                                        FROM DirtyProductExtend  dpe, DirtyProduct dp 
                                        WHERE dpe.dirtyProductId = dp.id AND dpe.shopId = ? AND dp.dirtyStatus != 'C'", [$this->getShop()->id]);

        return $this->app->dbAdapter->countAffectedRows();
    }

    protected function updateCategoryDictionary()
    {
        $this->app->dbAdapter->query("INSERT IGNORE INTO DictionaryCategory (shopId, term) 
                                        SELECT DISTINCT dpe.shopId, concat(ifnull(audience,''),'-',ifnull(cat1,''),'-',ifnull(cat2,''),'-',ifnull(cat3,''),'-',ifnull(cat4,''),'-',ifnull(cat5,'')) 
                                        FROM DirtyProductExtend  dpe, DirtyProduct dp 
                                        WHERE dpe.dirtyProductId = dp.id AND dpe.shopId = ? AND dp.dirtyStatus != 'C'", [$this->getShop()->id]);

        return $this->app->dbAdapter->countAffectedRows();
    }

    protected function updateTagDictionary()
    {
        $this->app->dbAdapter->query("INSERT IGNORE INTO DictionaryTag (shopId, term) 
                                        SELECT DISTINCT dpe.shopId, dpe.tag1 
                                        FROM DirtyProductExtend  dpe, DirtyProduct dp 
                                        WHERE dpe.dirtyProductId = dp.id AND dpe.shopId = ? AND dp.dirtyStatus != 'C' AND trim(tag1) != ''", [$this->getShop()->id]);
        $i = $this->app->dbAdapter->countAffectedRows();
        $this->app->dbAdapter->query("INSERT IGNORE INTO DictionaryTag (shopId, term) 
                                        SELECT DISTINCT dpe.shopId, dpe.tag2 
                                        FROM DirtyProductExtend  dpe, DirtyProduct dp 
                                        WHERE dpe.dirtyProductId = dp.id AND dpe.shopId = ? AND dp.dirtyStatus != 'C' AND trim(tag2) != ''", [$this->getShop()->id]);
        $i += $this->app->dbAdapter->countAffectedRows();
        $this->app->dbAdapter->query("INSERT IGNORE INTO DictionaryTag (shopId, term) 
                                        SELECT DISTINCT dpe.shopId, dpe.tag3 
                                        FROM DirtyProductExtend  dpe, DirtyProduct dp 
                                        WHERE dpe.dirtyProductId = dp.id AND dpe.shopId = ? AND dp.dirtyStatus != 'C' AND trim(tag3) != ''", [$this->getShop()->id]);
        $i += $this->app->dbAdapter->countAffectedRows();

        return $i;
    }

    protected function updateGereralColorDictionary()
    {
        $this->app->dbAdapter->query("INSERT IGNORE INTO DictionaryColorGroup (shopId, term) 
                                        SELECT DISTINCT dpe.shopId, generalColor 
                                        FROM DirtyProductExtend  dpe, DirtyProduct dp 
                                        WHERE dpe.dirtyProductId = dp.id AND dpe.shopId = ? AND dp.dirtyStatus != 'C'", [$this->getShop()->id]);

        return $this->app->dbAdapter->countAffectedRows();
    }

    protected function updateSizeDictionary()
    {
        $this->app->dbAdapter->query("INSERT  INTO DictionarySize (shopId, term, categoryFriend) 
                                        SELECT DISTINCT ds.shopId, size , concat(ifnull(audience,''),'-',ifnull(cat1,''),'-',ifnull(cat2,''),'-',ifnull(cat3,''),'-',ifnull(cat4,''),'-',ifnull(cat5,''))
                                        FROM DirtySku ds, DirtyProduct dp JOIN 
                                        DirtyProductExtend dpe on dp.id= dpe.dirtyProductId
                                        WHERE dp.id = ds.dirtyProductId AND ds.shopId = ? AND trim(size) != '' AND dp.dirtyStatus != 'C'
                                        ON DUPLICATE KEY UPDATE 
                                        DictionarySize.shopId=ds.shopId,
                                        DictionarySize.term=size,
                                        DictionarySize.categoryFriend=concat(ifnull(audience,''),'-',ifnull(cat1,''),'-',ifnull(cat2,''),'-',ifnull(cat3,''),'-',ifnull(cat4,''),'-',ifnull(cat5,''))",[$this->getShop()->id]);

        return $this->app->dbAdapter->countAffectedRows();
    }

    protected function updateDetailDictionary()
    {
        /*$this->app->dbAdapter->query("INSERT IGNORE INTO DictionaryDetail (shopId, term)
                                        SELECT DISTINCT dp.shopId, content 
                                        FROM DirtyDetail dd, DirtyProduct dp 
                                        WHERE dd.dirtyProductId = dp.id AND dp.shopId = ? AND dp.dirtyStatus != 'C'", [$this->getShop()->id]);

        return $this->app->dbAdapter->countAffectedRows();
        */
        return 0;
    }

    /** create products from dirtyProduct */
    public function createProducts()
    {
        /** fetch dictionaries */
        try {
            $brandDic = $this->mapDictionary("Brand", $this->app->dbAdapter->query("SELECT term, productBrandId AS foreignKey FROM DictionaryBrand WHERE shopId = ? AND productBrandId IS NOT NULL", [$this->getShop()->id])->fetchAll());
            $categoryDic = $this->mapDictionary("Category", $this->app->dbAdapter->query("SELECT term, productCategoryId AS foreignKey FROM DictionaryCategory WHERE shopId = ? AND productCategoryId IS NOT NULL", [$this->getShop()->id])->fetchAll());
            $colorGroupDic = $this->mapDictionary("ColorGroup", $this->app->dbAdapter->query("SELECT term, productColorGroupId AS foreignKey FROM DictionaryColorGroup WHERE shopId = ? AND productColorGroupId IS NOT NULL", [$this->getShop()->id])->fetchAll());
            $seasonDic = $this->mapDictionary("Season", $this->app->dbAdapter->query("SELECT term, productSeasonId AS foreignKey FROM DictionarySeason WHERE shopId = ? AND productSeasonId IS NOT NULL", [$this->getShop()->id])->fetchAll());

            $detailSet = $this->mapDictionary("Detail", $this->app->dbAdapter->query("SELECT slug AS term, id AS foreignKey FROM ProductDetail WHERE slug != ''", [])->fetchAll());

            $sizeConnector = \Monkey::app()->repoFactory->create('ImporterConnector')->em()->findBySql("SELECT id FROM ImporterConnector WHERE shopId = ? AND scope = ?", [$this->getShop()->id, 'sizeGroupId']);
            if ($sizeConnector->isEmpty()) throw new BambooOutOfBoundException('Could not find connector for sizes');
            /** @var \bamboo\domain\entities\CImporterConnector $sizeConnector */
            $sizeConnector = $sizeConnector->getFirst();

            //$productSheetConnector = \Monkey::app()->repoFactory->create('ImporterConnector')->em()->findBySql("SELECT id FROM ImporterConnector where shopId = ? and scope = ?",[$this->getShop()->id,'sheetName']);
            //if($productSheetConnector->isEmpty()) throw new BambooOutOfBoundException('Could not find connector for sizes');
            //$productSheetConnector = $productSheetConnector->getFirst();
            try {
                $tagDic = $this->mapDictionary("Tag", $this->app->dbAdapter->query("SELECT term, tagId AS foreignKey FROM DictionaryTag WHERE shopId = ? AND tagId IS NOT NULL", [$this->getShop()->id])->fetchAll());
            } catch (BambooOutOfBoundException $e) {
            }

        } catch (BambooOutOfBoundException $e) {
            $this->warning('Create Products', 'Found emptyDictionary for: ' . $e->getMessage());
            return false;
        }

        $dictionaryProblem = false;
        /** fetch empty dirtyProduct */
        $dps = $this->app->dbAdapter->query("	SELECT dp.id
												FROM DirtyProduct dp JOIN DirtySku ds on dp.id = ds.dirtyProductId
												WHERE dp.shopId = ? AND
													  productId IS NULL AND
													  productVariantId IS NULL AND
													  dirtyStatus = 'F' GROUP BY dp.id HAVING sum(ds.qty) > 0", [$this->getShop()->id])->fetchAll();

        $dpEm = \Monkey::app()->repoFactory->create('DirtyProduct');
        $productFactory = \Monkey::app()->repoFactory->create('Product');
        /** @var CProductNameTranslationRepo $nameFactory */
        $nameFactory = \Monkey::app()->repoFactory->create('ProductNameTranslation');
        $descriptionFactory = \Monkey::app()->repoFactory->create('ProductDescriptionTranslation');
        $variantFactory = \Monkey::app()->repoFactory->create('ProductVariant');
        $shopProduct = \Monkey::app()->repoFactory->create('ShopHasProduct');
        $done = 0;

        $sheetPrototype = \Monkey::app()->repoFactory->create('ProductSheetPrototype')->findOneBy(["name" => "Generica"]);

        $slugify = new CSlugify();
        $this->report('createProducts', 'working ' . count($dps) . ' dirtyProducts');
        foreach ($dps as $dpId) {
            $this->report('createProducts', 'working for ' . $dpId['id']);
            try {
                $dirtyProduct = $dpEm->findOne($dpId);
                $sheetPrototype->productDetailLabel->rewind();

                \Monkey::app()->repoFactory->beginTransaction();
                /** Inserisco la variante*/
                $variant = $variantFactory->getEmptyEntity();
                $variant->name = $dirtyProduct->var;
                $variant->description = $dirtyProduct->extend->colorDescription;


                /** Inserisco il prodotto */
                $product = $productFactory->getEmptyEntity();
                $product->productStatusId = 11;
                $product->itemno = $dirtyProduct->itemno;

                if (!isset($brandDic[$slugify->slugify($dirtyProduct->brand)])) throw new BambooOutOfBoundException('Product Brand not found in Dictionary: %s', [$dirtyProduct->brand]);
                $product->productBrandId = $brandDic[$slugify->slugify($dirtyProduct->brand)];

                $existingProduct = $this->app->dbAdapter->select('Product',
                    ['itemno' => $product->itemno,
                    'productBrandId' => $product->productBrandId])->fetchAll();
                if (count($existingProduct)) {
                    $product->id = $existingProduct[0]['id'];
                } else if (!is_null($dirtyProduct->relationship)) {
                    if (!is_null($dirtyProduct->relationship->product)) {
                        $product->id = $dirtyProduct->relationship->product->id;
                    } else {
                        throw new BambooLogicException('Cannot find valid product relationship');
                    }
                } else {
                    $product->id = $this->app->dbAdapter->query("SELECT id FROM Product ORDER BY id DESC LIMIT 0,1", [])->fetch()['id'] + 1;
                }


                /** fixme excludo i prodotti già fusi ma... non so se è sufficiente */
                $conto = $this->app->dbAdapter->query(" SELECT count(*) AS conto
													FROM Product, ProductVariant
													WHERE Product.productVariantId = ProductVariant.id AND
														  Product.itemno LIKE ? AND
														  Product.productBrandId = ? AND
														  ProductVariant.name LIKE ? AND
														  Product.productStatusId NOT IN (8,13)", [$dirtyProduct->itemno, $product->productBrandId, $variant->name])->fetch()['conto'];
                if ($conto > 0) {
                    /** CHANGE EXECUTION; FUSE AND CONTINUE; END TRANSACTION */
                    if (!isset($seasonDic[$slugify->slugify($dirtyProduct->extend->season)])) throw new BambooOutOfBoundException('Product Season not found in Dictionary: %s', [$dirtyProduct->extend->season]);
                    $newSeason = $seasonDic[$slugify->slugify($dirtyProduct->extend->season)];
                    $this->fuseProduct($product, $variant, $dirtyProduct,$newSeason,$sizeConnector);
                    \Monkey::app()->repoFactory->commit();
                    continue;
                } else {
                    $variant->id = $variant->insert();
                    $product->productVariantId = $variant->id;
                }

                if (!isset($seasonDic[$slugify->slugify($dirtyProduct->extend->season)])) throw new BambooOutOfBoundException('Product Season not found in Dictionary: %s', [$dirtyProduct->extend->season]);
                $product->productSeasonId = $seasonDic[$slugify->slugify($dirtyProduct->extend->season)];
                $product->sortingPriorityId = 99;
                $product->dummyPicture = "bs-dummy-16-9.png";
                /** aggiungo il colore */
                if (!isset($colorGroupDic[$slugify->slugify($dirtyProduct->extend->generalColor)])) throw new BambooOutOfBoundException('Product Color not found in Dictionary: %s', [$dirtyProduct->extend->generalColor]);
                $product->productColorGroupId = $colorGroupDic[$slugify->slugify($dirtyProduct->extend->generalColor)];
                $product->insert();
                $product = $productFactory->findOne($product->getIds());

                /** aggiungo i tag */
                $tags = [];
                $tags[] = $dirtyProduct->extend->tag1;
                $tags[] = $dirtyProduct->extend->tag2;
                $tags[] = $dirtyProduct->extend->tag3;
                foreach ($tags as $tag) {
                    if (empty($tag)) continue;
                    if (!isset($tagDic[$slugify->slugify($tag)])) throw new BambooOutOfBoundException('Product Tag not found in Dictionary: %s', [$tag]);
                    $this->app->dbAdapter->insert('ProductHasTag',
                        ['productId' => $product->id,
                            'productVariantId' => $product->productVariantId,
                            'tagId' => $tagDic[$slugify->slugify($tag)]], false, true);
                }
                $mandatoryTags = [1, 6];
                foreach ($mandatoryTags as $oneTag) {
                    try {
                        $this->app->dbAdapter->insert('ProductHasTag', ['productId' => $product->id,
                            'productVariantId' => $product->productVariantId,
                            'tagId' => $oneTag]);
                    } catch (\Throwable $e) {
                    }
                }

                $term = [];
                /** aggiungo la categoria */
                $term[] = $dirtyProduct->extend->audience;
                $term[] = $dirtyProduct->extend->cat1;
                $term[] = $dirtyProduct->extend->cat2;
                $term[] = $dirtyProduct->extend->cat3;
                $term[] = $dirtyProduct->extend->cat4;
                $term[] = $dirtyProduct->extend->cat5;
                $term = implode('-', $term);
                if (!isset($categoryDic[$slugify->slugify($term)])) throw new BambooOutOfBoundException('Product Category not found in Dictionary: %s', [$term]);
                $this->app->dbAdapter->insert('ProductHasProductCategory', ['productId' => $product->id,
                    'productVariantId' => $product->productVariantId,
                    'productCategoryId' => $categoryDic[$slugify->slugify($term)]]);

                $product->productSizeGroupId = $sizeConnector->findConnectionForProduct($product, $dirtyProduct); //FIXME Will it work?

                if (!is_numeric($product->productSizeGroupId)) throw new BambooOutOfBoundException('Product Size group not found');

                $product->productSheetPrototypeId = 33;
                $product->update();

                $shopHasProduct = $shopProduct->getEmptyEntity();
                $shopHasProduct->productId = $product->id;
                $shopHasProduct->productVariantId = $product->productVariantId;
                $shopHasProduct->shopId = $this->getShop()->id;
                $shopHasProduct->extId = $dirtyProduct->extId;
                $shopHasProduct->productSizeGroupId = $product->productSizeGroupId;
                $shopHasProduct->price = $dirtyProduct->getDirtyPrice();
                $shopHasProduct->salePrice = $dirtyProduct->getDirtySalePrice();
                $shopHasProduct->value = $dirtyProduct->getDirtyValue();
                $shopHasProduct->insert();

                $name = $nameFactory->insertName(trim($dirtyProduct->extend->name));
                try {
                    $nameFactory->saveNameForNewProduct($product->id, $product->productVariantId, $name);
                } catch (\Throwable $e) {
                }


                $productDescriptionTranslation = $descriptionFactory->getEmptyEntity();
                $productDescriptionTranslation->productId = $product->id;
                $productDescriptionTranslation->productVariantId = $product->productVariantId;
                $productDescriptionTranslation->langId = 1;
                $productDescriptionTranslation->marketplaceId = 1;
                $productDescriptionTranslation->description = is_null($dirtyProduct->extend->description) ? "" : $dirtyProduct->extend->description;
                $productDescriptionTranslation->insert();

                foreach ($dirtyProduct->dirtyDetail as $detail) {
                    if (!$sheetPrototype->productDetailLabel->valid()) break;

                    $detailSlug = $slugify->slugify($detail->content);

                    if (empty($detailSlug)) {
                        $sheetPrototype->productDetailLabel->next();
                        continue;
                    }
                    /** insert new detail into table */
                    if (!isset($detailSet[$detailSlug])) {
                        $detailSet[$detailSlug] = $this->app->dbAdapter->insert('ProductDetail', ['slug' => $detailSlug]);
                        $this->app->dbAdapter->insert('ProductDetailTranslation', ["productDetailId" => $detailSet[$detailSlug], "langId" => 1, "name" => strip_tags($detail->content) . " !"]);
                    }
                    $this->app->dbAdapter->insert('ProductSheetActual', [
                        "productId" => $product->id,
                        "productVariantId" => $product->productVariantId,
                        "productDetailLabelId" => $sheetPrototype->productDetailLabel->current()->id,
                        "productDetailId" => $detailSet[$detailSlug]
                    ]);

                    $sheetPrototype->productDetailLabel->next();
                }

                $dirtyProduct->productId = $product->id;
                $dirtyProduct->productVariantId = $product->productVariantId;
                $dirtyProduct->dirtyStatus = 'K';
                $dirtyProduct->update();

                \Monkey::app()->repoFactory->commit();
                $done++;
                $this->report('createProducts', 'Created new Product: ' . $product->id . '-' . $product->productVariantId);
            } catch (BambooOutOfBoundException $e) {
                \Monkey::app()->repoFactory->rollback();
                $this->error('createProducts', 'Errore in crezione, gestito per ' . $dpId['id'], $e);
                $dictionaryProblem = true;
            } catch (BambooException $e) {
                \Monkey::app()->repoFactory->rollback();
                $this->error('createProducts', 'Errore in crezione, gestito per ' . $dpId['id'], $e);
            } catch (\ErrorException $e) {
                \Monkey::app()->repoFactory->rollback();
                $this->error('createProducts', 'Errore ErrorException in crezione generico . '.$dpId['id'], $e);
            } catch (\Throwable $e) {
                \Monkey::app()->repoFactory->rollback();
                $this->error('createProducts', 'Errore Exception in crezione generico '.$dpId['id'], $e);
            }
        }
        if ($dictionaryProblem) {
            iwesMail('it@iwes.it',
                'Importer: Errore nella creazione prodotti di ' . $this->getShop()->name,
                'Verificare i dizionari per la creazione dei prodotti');
        }

        return $done;
    }

    /**
     * @param array $rowDictionary
     * @return array
     * @throws BambooOutOfBoundException
     */
    protected function mapDictionary($name, array $rowDictionary)
    {
        $s = new CSlugify();
        $dic = [];
        $ok = false;
        if (empty($rowDictionary)) {
            throw new BambooOutOfBoundException('Empty Dictionary ' . $name);
        }
        foreach ($rowDictionary as $val) {
            if (!$ok && isset($val['foreignKey']) && !is_null($val['foreignKey']) && !empty($val['foreignKey'])) $ok = true;
            $dic[$s->slugify($val['term'])] = $val['foreignKey'];
        }
        if (!$ok) throw new BambooOutOfBoundException('Empty Simple Dictionary' . $name);

        return $dic;
    }

    /**
     * @param $product
     * @param $variant
     * @param CDirtyProduct $dirtyProduct
     * @param $newSeasonId
     * @param $sizeConnector CImporterConnector
     * @throws BambooLogicException
     */
    protected function fuseProduct($product, $variant, $dirtyProduct, $newSeasonId,$sizeConnector)
    {
        $existing = \Monkey::app()->repoFactory->create('Product')->findOneBySql("SELECT Product.id, Product.productVariantId
													FROM Product, ProductVariant
													WHERE Product.productVariantId = ProductVariant.id AND
														  Product.itemno LIKE ? AND
														  Product.productBrandId = ? AND
														  ProductVariant.name LIKE ? AND
														  Product.productStatusId NOT IN (8,13)", [$dirtyProduct->itemno, $product->productBrandId, $variant->name]);

        if ($existing) {
            $shp = \Monkey::app()->repoFactory->create('ShopHasProduct')->getEmptyEntity();
            $shp->shopId = $this->getShop()->id;
            $shp->productId = $existing->id;
            $shp->productVariantId = $existing->productVariantId;

            $shp2 = \Monkey::app()->repoFactory->create('ShopHasProduct')->findOne($shp->getIds());
            if (is_null($shp2)) {
                $shp->price = $dirtyProduct->getDirtyPrice();
                $shp->salePrice = $dirtyProduct->getDirtySalePrice();
                $shp->value = $dirtyProduct->getDirtyValue();
                $shp->productSizeGroupId = $sizeConnector->findConnectionForProduct($product, $dirtyProduct);
                if(!is_numeric($shp->productSizeGroupId)) $shp->productSizeGroupId = $product->productSizeGroupId;
                $shp->insert();
            } else {
                $shp2->price = $dirtyProduct->getDirtyPrice();
                $shp2->salePrice = $dirtyProduct->getDirtySalePrice();
                $shp2->value = $dirtyProduct->getDirtyValue();
                $shp2->update();
            }

            $dirtyProduct->productId = $existing->id;
            $dirtyProduct->productVariantId = $existing->productVariantId;
            $dirtyProduct->dirtyStatus = 'K';
            $dirtyProduct->update();

            $this->warning('fuseProduct', 'Fusing DirtyProduct: ' . $dirtyProduct->id . ' with Product: ' . $existing->printId());

            $product = \Monkey::app()->repoFactory->create('Product')->findOneBy([
               'id'=> $dirtyProduct->productId,
                'productVariantId' =>$dirtyProduct->productVariantId
            ]);

            if($product->productSeasonId != $newSeasonId) {
                $this->warning('fuseProduct', 'Season Change for product:'.$product->printId().' from '.$product->productSeasonId.' to '.$newSeasonId);

                $productSeason = \Monkey::app()->repoFactory->create('ProductSeason')->findOneBy(['id'=>$newSeasonId]);
                if($productSeason->order > $product->productSeason->order) {
                    $this->warning('fuseProduct', 'Season Change, the new season is newer, CHANGE!');
                    $product->productSeasonId = $newSeasonId;
                    $product->isOnSale = false;
                    $product->update();
                } else {
                    $this->warning('fuseProduct', 'Season Change, the new season NOT newer no need for update');
                }
            }
        } else {
            $this->error('fuseProduct', 'Error Fusing DirtyProduct: ' . $dirtyProduct->id . ' existing in context...', $existing);
            throw new BambooLogicException("Product already extisting");
        }

    }

    /**
     * Insert details in empty products
     */
    public function checkForNewDetails()
    {
        $detailSet = $this->mapDictionary("Detail", $this->app->dbAdapter->query("SELECT slug AS term, id AS foreignKey FROM ProductDetail WHERE slug != ''", [])->fetchAll());

        $sql = "SELECT DISTINCT(dp.id) AS id
                FROM Product p
                  JOIN ShopHasProduct shp ON (p.id, p.productVariantId) = (shp.productId, shp.productVariantId)
                  JOIN DirtyProduct dp ON shp.productId = dp.productId AND shp.productVariantId = dp.productVariantId AND shp.shopId = dp.shopId
                  JOIN DirtyDetail dd ON dp.id = dd.dirtyProductId
                  LEFT JOIN ProductSheetActual psa ON (p.id,p.productVariantId) = (psa.productId, psa.productVariantId)
                  WHERE shp.shopId = ? AND trim(dd.content) != '' AND CHAR_LENGTH(dd.content) > 2
                GROUP BY p.id, p.productVariantId
                HAVING count(psa.productDetailLabelId) = 0 AND count(dd.id) > 0";

        $dirtyProducts = \Monkey::app()->repoFactory->create('DirtyProduct')->findBySql($sql, [$this->getShop()->id]);
        $this->report('checkForNewDetails', 'Found ' . $dirtyProducts->count() . ' to work');
        $slugify = new CSlugify();
        /** @var CDirtyProduct $dirtyProduct */
        foreach ($dirtyProducts as $dirtyProduct) {
            try {

                $dirtyProduct->product->productSheetPrototypeId = 33;
                $dirtyProduct->product->update();
                $this->report('checkForNewDetails', 'DirtyProduct ' . $dirtyProduct->id . ' has ' . $dirtyProduct->dirtyDetail->count() . ' details', $dirtyProduct->dirtyDetail);
                $dirtyProduct->product->productSheetPrototype->productDetailLabel->rewind();
                foreach ($dirtyProduct->dirtyDetail as $detail) {
                    if (!$dirtyProduct->product->productSheetPrototype->productDetailLabel->valid()) break;

                    $detailSlug = $slugify->slugify($detail->content);

                    if (empty($detailSlug)) {
                        $dirtyProduct->product->productSheetPrototype->productDetailLabel->next();
                        continue;
                    }
                    /** insert new detail into table */
                    if (!isset($detailSet[$detailSlug])) {
                        $detailSet[$detailSlug] = $this->app->dbAdapter->insert('ProductDetail', ['slug' => $detailSlug]);
                        $this->app->dbAdapter->insert('ProductDetailTranslation', ["productDetailId" => $detailSet[$detailSlug], "langId" => 1, "name" => strip_tags($detail->content) . " !"]);
                    }
                    $this->app->dbAdapter->insert('ProductSheetActual', [
                        "productId" => $dirtyProduct->product->id,
                        "productVariantId" => $dirtyProduct->product->productVariantId,
                        "productDetailLabelId" => $dirtyProduct->product->productSheetPrototype->productDetailLabel->current()->id,
                        "productDetailId" => $detailSet[$detailSlug]
                    ]);

                    $dirtyProduct->product->productSheetPrototype->productDetailLabel->next();
                }
            } catch (\Throwable $e) {
                $this->error('checkForNewDetails', 'Error writing new detail for ' . $dirtyProduct->id, $e);
            }
        }

    }

    /** fetch photos from remote and renaming them */
    public function fetchPhotos()
    {
        //TODO Fetch photos
    }


    /**
     * take photos from file
     */
    public function sendPhotos()
    {
        $slugify = new CSlugify();
        $this->report('sendPhotos', 'Starting');

        $destFTPClient = $this->config->fetchAll('destFTPClient');

        $ftpDestination = new CFTPClient($this->app, $destFTPClient);
        $ftpDestDir = '/shootImport/incoming/' . $this->getShop()->name;
        $ftpDestination->changeDir($ftpDestDir);

        $res = $this->app->dbAdapter->query(
            "SELECT dpp.id AS id, dpp.dirtyProductId AS dirtyProductID, url, location, position, worked, dpp.shopId AS shopId, p.id AS productId, p.productVariantId FROM DirtyPhoto dpp, DirtyProduct dp, Product p WHERE dpp.dirtyProductId = dp.id AND dp.productId = p.id AND dp.productVariantId = p.productVariantId AND dpp.shopId = ? AND ( dpp.worked = 0 OR dpp.worked IS NULL ) ORDER BY dpp.creationDate DESC",
            //debug "SELECT * FROM DirtyPhoto WHERE shopId = ? AND (worked = 0 OR worked IS NULL)",
            [$this->getShop()->id]
        )->fetchAll();
        $this->report("download immagini", "inizio");

        //creo la cartella
        $destDir = $this->app->rootPath() . "/temp/tempImgs/";
        if (!is_dir(rtrim($destDir, "/"))) mkdir($destDir, 0777, true);
        $newMethod = true;
        $i = 0;
        foreach ($res as $k => $v) {
            try {
                if ($i % 50 == 0) $this->report('download immagini', 'tentate ' . $k . ' immagini');
                if (2000 < $i) break;
                $this->debug('Download Immagine', $v['url']);
                /** @var CProduct $p */
                $p = \Monkey::app()->repoFactory->create("Product")->findOneBy(['id' => $v['productId'], 'productVariantId' => $v['productVariantId']]);

                $path = pathinfo($v['url']);
                $imgBody = null;

                if ($newMethod) {
                    $this->debug('Download Immagine', 'going to file_get_contents');
                    $imgBody = file_get_contents(htmlspecialchars_decode($v['url']));
                    $imgBody = str_replace(" ", "%20", $imgBody);
                    $urlImage=str_replace(" ", "%20", $v['url']);
                    $this->debug('Download Immagine', 'got content');
                } else {
                    $this->debug('Download Immagine', 'going to curl');
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $urlImage);
                    curl_setopt($ch, CURLOPT_HEADER, FALSE);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                    $imgBody = curl_exec($ch);
                    $this->debug('Download Immagine', 'got to curl');
                    if (false === $imgBody) {
                        $this->error("download immagini", "Immagine non scaricata: File: " . $v['url'] . " - CURL Error: " . curl_error($ch));
                        curl_close($ch);
                        continue;
                    }
                    if (empty($imgBody)) {
                        $this->error("download immagini", "Immagine vuota, Url corrispondente: " . $v['url']);
                        curl_close($ch);
                        continue;
                    }

                    curl_close($ch);
                }

                $imgN = str_pad($v['position'], 3, "0", STR_PAD_LEFT);
                $destFileName = $p->getAztecCode() . " - " . $imgN . "." . $path['extension'];
                if ($p->productPhoto->count()) $existing = true;
                else $existing = false;

                $putRes = NULL;
                try {
                    $this->debug('Download Immagine', 'going to file_put_contents in: ' . $destDir . $destFileName);
                    $putRes = file_put_contents($destDir . $destFileName, $imgBody);
                    $this->debug('Download Immagine', 'put content');
                    if ($imgN == 1 && !$existing) {
                        $this->debug('Download Immagine', 'saving dummyPicture');
                        $this->saveDummyPicture($p, $destDir . $destFileName);
                    }
                    $this->debug('Download Immagine', 'Putting Res? ' . $putRes);
                    if ($putRes) {
                        if ($existing) $ftpDestination->changeDir($ftpDestDir . '/existing');
                        else $ftpDestination->changeDir($ftpDestDir);
                        $this->debug('Download Immagine', 'changed dir');

                        chmod($destDir . $destFileName, 0777);
                        if ($ftpDestination->put($destDir . $destFileName, $destFileName)) {
                            //segno come "worked" le immagini importate
                            $this->debug('Download Immagine', 'ftp put done');
                            $this->app->dbAdapter->update("DirtyPhoto", ['worked' => 1], ['id' => $v['id']]);
                        } else {
                            $this->error("ftp-upload", "file non uploadato sul NAS: " . $ftpDestDir . $destFileName);
                        }
                        unlink($destDir . $destFileName);
                    }
                } catch (\Throwable $e) {
                    $this->error("download immagini", $destFileName . "non salvato. File scaricato, ma impossibile salvarlo su disco. Url corrispondente: " . $v['url'], $e);
                    if (!is_dir(rtrim($destDir, "/"))) mkdir($destDir, 0777, true);
                }

            } catch (\Throwable $e) {
                $this->error('Downloading Photo', 'generic error: ' . $e->getMessage(), $e);
            }
            $i++;
        }
        try {
            $files = glob($destDir . '*');
            foreach ($files as $file) {
                if (is_file($file))
                    unlink($file);
            }
            rmdir($destDir);
        } catch (\Throwable $e) {
            $this->error('SendPhotos', 'error while deleting photos', $e);
        }
        $this->report("download immagini", "fine");

        return true;
    }

    /**
     * @param CProduct $p
     * @param $photoPath
     * @throws BambooException
     */
    public function saveDummyPicture(CProduct $p, $photoPath)
    {
        if (empty($p->dummyPicture) || $p->dummyPicture == 'bs-dummy-16-9.png') {
            $this->debug('Download Immagine', 'Going to Save DummyPicture');
            $dummyFolder = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'dummyFolder') . '/';
            $this->app->vendorLibraries->load("amazon2723");
            $width = 500;
            $imager = new ImageEditor();
            $fileName = pathinfo($photoPath);
            $dummyName = rand(0, 9999999999) . '.' . $fileName['extension'];
            try {
                $this->debug('Download Immagine', 'Going to load dummy: ' . $photoPath);
                if (!$imager->load($photoPath)) throw new BambooException('Could not load image. Photopath: '.$photoPath);
                $this->debug('Download Immagine', 'going to resize: ' . $width);
                $this->debug('Download Immagine', 'going to resize: ' . $imager->getWidth());
                $imager->resizeToWidth($width);
                $this->debug('Download Immagine', 'goijng to save: ' . $dummyFolder . '/' . $dummyName);
                $imager->save($dummyFolder . '/' . $dummyName);
                $this->debug('Download Immagine', 'Saved Dummy');
                $p->dummyPicture = $dummyName;
                $p->update();
                $this->report('PhotoDownload', 'Set dummyPicture: ' . $dummyName . ' for: ' . $p->printId());
            } catch (\Throwable $e) {
                $this->warning('PhotoDownload', 'Failed setting dummyPicture: ' . $dummyName . ' for ' . $p->printId());
                throw $e;
            }

        } else $this->debug('Download Immagine', 'Wont Save DummyPictures');
    }
}