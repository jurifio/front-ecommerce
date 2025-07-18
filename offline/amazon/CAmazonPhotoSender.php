<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 27/07/2015
 * Time: 10:13
 */

namespace bamboo\ecommerce\offline\amazon;

use bamboo\core\base\CFTPClient;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooFTPClientException;
use bamboo\core\jobs\ACronJob;
use bamboo\core\utils\amazonPhotoManager\ImageManager;
use bamboo\core\utils\amazonPhotoManager\S3Manager;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductPhoto;

/**
 * Class CAmazonPhotoSender
 * @package bamboo\ecommerce\offline\amazon
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
class CAmazonPhotoSender extends ACronJob
{
    protected $amazonCredentials = [];
    protected $ftpCredentials = [];

    /** @var CFTPClient */
    protected $ftp;

    /** @var ImageManager */
    protected $imageManager;

    protected $fileList;
    protected $localTempFolder;
    protected $remoteTodo = "/shootImport/newage2/todo";
    protected $remoteDone = "/shootImport/newage2/done";

    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $this->amazonCredentials = $this->getCredential();
        $this->ftpCredentials = $this->app->cfg()->fetch('miscellaneous', 'photoFTPClient');
        $this->localTempFolder = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'tempFolder') . "/";
        $this->app->vendorLibraries->load("amazon2723");

        $this->imageManager = new ImageManager(new S3Manager($this->amazonCredentials), $this->app, $this->localTempFolder);
        $this->work();
    }

    public function getCredential()
    {
        return (array)$this->app->cfg()->fetch('miscellaneous', 'amazonConfiguration')['credential'];
        /*return array(
            'key'   => 'AKIAJAT27PGJ6XWXBY6A',
            'secret'=> '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'
        );*/
    }

    /**
     *
     */
    public function work()
    {
        $this->report('Photo Sender', 'init Work');
        $files = $this->readList($this->remoteTodo);

        $done = 0;
        $i = 0;
        foreach ($files as $file) {
            try {
                set_time_limit(120);
                $this->debug('Work Cycle', 'Working Photo '.$file);
                if($this->doFile($file)>0) $done++;
                $this->debug('Work Cycle', 'Done Photo' . $file);
            } catch (\Throwable $e) {
                $this->error('Work Cycle', 'Failed Working Photo' . $file,$e);
            }
            $i++;
            if($i%25 == 0) $this->report('Work Cycle','Doing '. $done.' over '.$i.' running...');
        }
        $this->report('PhotoExport', 'Done '.$done.' over '.count($files));
    }

    /**
     * @param $file
     * @return int
     * @throws BambooException
     * @throws BambooFTPClientException
     */
    public function doFile($file) {
        $match = "";
        preg_match('/([0-9]{1,7}-[0-9]{1,8})( - |_|__)/u', $file, $match);
        $this->debug('doFile','Match: '.json_encode($match).' on file: '.$file);
        $names = pathinfo($file);
        if (!$this->ftp->fileExist($file)) return 0;
        $product = \Monkey::app()->repoFactory->create('Product')->findOneByStringId($match[1]);
        if($product == null) throw new BambooException('Product not found for: '.$match[1]);
        $futureName = $this->calculatePhotoNameStandard($product, $file);
        $localName = $this->localTempFolder . $names['basename'];
        if (!$this->ftp->get($localName, $file, false)) {
            throw new BambooFTPClientException('Errore nell\'ottenere il file' . $file);
        }
        $res = $this->imageManager->process($names['basename'], $futureName, 'iwes', $product->productBrand->slug);
        $this->debug('doFile','Processed: '.count($res),$res);
        if ($res < 1) {
            throw new BambooException('Errore nel processo di upload, caricati meno di 3 file');
        }

        $ids = [];

        $photoRepo = \Monkey::app()->repoFactory->create('ProductPhoto');
        foreach ($res as $key => $val) {
            $insertData = ['name' => $val, 'size' => $key];
            $photoExist = $photoRepo->findOneBy($insertData);
            if($photoExist == null) {
                $insertData['isPublic'] = 1;
                $insertData['order'] = $futureName['number'];
                $insertData['mime'] = 'image/jpeg';
                $id = $this->app->dbAdapter->insert('ProductPhoto', $insertData);
            } else {
                if($photoExist->order != $futureName['number']) {
                    $photoExist->order = $futureName;
                    $photoExist->update();
                    $this->debug('doFile','updated Position for existing photo');
                }
                $id = $photoExist->id;
            }
            $this->app->dbAdapter->insert("ProductHasProductPhoto", ["productId" => $product->id,
                "productVariantId" => $product->productVariantId,
                "productPhotoId" => $id],false,true);

            if($futureName['number'] == 1 && $key == CProductPhoto::SIZE_MEDIUM) {
                $this->setDummyPicture($product,$val);
            }
            $ids[] = $id;
        }

        $this->ftp->move($file, $this->calcRemoteFolder());
        unlink($this->localTempFolder. $names['basename']);

        return count($res);
    }

    /**
     * @return string
     */
    public function calcRemoteFolder() {
        if(!isset($this->remoteDoneFull)) {
            $stringDate = (new \DateTime())->format('Y-m');
            if(!$this->ftp->fileExist($this->remoteDone.'/'.$stringDate)) {
                $this->ftp->mkDir($this->remoteDone.'/'.$stringDate);
            };
            $this->remoteDoneFull = $this->remoteDone.'/'.$stringDate;
        }
        return $this->remoteDoneFull;

    }

    /**
     * @param CProduct $product
     * @param $futureDummy
     */
    public function setDummyPicture(CProduct $product, $futureDummy)
    {
        /** insert DummyPicture */
        if(!empty($futureDummy)){
            $futureDummy = $this->app->cfg()->fetch("general","product-photo-host").''.$product->productBrand->slug.'/'.$futureDummy;
            try {
                $oldDummy = $product->dummyPicture;
                $product->dummyPicture = $futureDummy;
                $product->update();
                //TODO delete old dummy
            } catch (\Throwable $e) {
                $this->error('setDummyPicture', 'Can\'t set dummy picture for product '.$product->printId());
            }
            try {
                $fullPath = $this->app->rootPath().$this->app->cfg()->fetch('paths','dummyFolder').'/'.$oldDummy;
                if(file_exists($fullPath))
                    unlink($fullPath);
                //TODO delete old dummy
            } catch (\Throwable $e) {
                $this->warning('setDummyPicture', 'Can\'t delete old dummyPicture '.$fullPath);
            }
        }
    }

    /**
     * @return CFTPClient
     */
    public function getFtp()
    {
        if (!isset($this->ftp)) {
            $this->ftp = new CFTPClient($this->app, $this->ftpCredentials);
        }
        return $this->ftp;
    }

    /**
     * @param $folder
     * @return array
     */
    public function readList($folder)
    {
        if (empty($this->fileList)) {
            $dirs = $this->getFtp()->nList($folder);
            $dirsN = 0;
            $merge = [];
            foreach ($dirs as $dir) {
                $aap = $this->getFtp()->nList($dir);
                $merge = array_merge($merge, $aap);
                $dirsN++;
            }
            $this->report('readList', $dirsN . ' dirs found', $dirs);
            $this->fileList = $merge;
            $this->report('readList', count($this->fileList) . ' files found', $this->fileList);
        }
        return $this->fileList;
    }

    /**
     * @param CProduct $product
     * @param $origin
     * @return array
     * @throws BambooException
     */
    public function calculatePhotoNameStandard(CProduct $product, $origin){
        $futureName = [];
        $futureName['name'] = $product->printId();

        $pieces = explode(".",$origin);
        $futureName['extension'] = $pieces[(count($pieces)-1)];
        unset($pieces[(count($pieces)-1)]);
        $rePieces = implode(".",$pieces);
        $pieces = explode("_",$rePieces);
        if(!is_numeric($pieces[(count($pieces)-1)])){
            $pieces = explode("-",$rePieces);
        }
        if(!is_numeric($pieces[(count($pieces)-1)])){
            throw new BambooException('Can\'t find the number of the foto');
        }
        $futureName['number'] = $pieces[(count($pieces)-1)];

        return $futureName;

    }
}