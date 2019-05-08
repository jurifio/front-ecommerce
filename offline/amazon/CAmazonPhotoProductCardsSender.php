<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 27/07/2015
 * Time: 10:13
 */

namespace bamboo\ecommerce\offline\amazon;

use bamboo\core\base\CFTPClient;
use bamboo\core\db\pandaorm\repositories\CRepo;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooFTPClientException;
use bamboo\core\exceptions\RedPandaAssetException;
use bamboo\core\jobs\ACronJob;
use bamboo\core\utils\amazonPhotoManager\ImageManager;
use bamboo\core\utils\amazonPhotoManager\S3Manager;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductCardPhoto;
use bamboo\domain\entities\CProductPhoto;

/**
 * Class CAmazonPhotoProductCardsSender
 * @package bamboo\ecommerce\offline\amazon
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 14/05/2018
 * @since 1.0
 */
class CAmazonPhotoProductCardsSender extends ACronJob
{
    protected $amazonCredentials = [];
    protected $ftpCredentials = [];

    /** @var CFTPClient */
    protected $ftp;

    /** @var ImageManager */
    protected $imageManager;

    protected $fileList;
    protected $localTempFolder;
    protected $remoteTodo = "/shootImport/newageproductcards/todo";
    protected $remoteDone = "/shootImport/newageproductcards/done";
    protected $remoteErr = "/shootImport/newageproductcards/err";

    /**
     * @param null $args
     * @throws BambooException
     */
    public function run($args = null)
    {
        $this->amazonCredentials = $this->getCredential();
        $this->ftpCredentials = $this->app->cfg()->fetch('miscellaneous', 'photoFTPClient');
        $this->localTempFolder = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'tempFolder').'-productcards' . "/";
        $this->app->vendorLibraries->load("amazon2723");

        $this->imageManager = new ImageManager(new S3Manager($this->amazonCredentials), $this->app, $this->localTempFolder);
        $this->work();
    }

    public function getCredential()
    {
        return (array)$this->app->cfg()->fetch('miscellaneous', 'amazonConfiguration')['credential'];
    }

    /**
     *
     */
    public function work()
    {
        $this->report('Photo Product Cards Sender', 'init Work');
        $files = $this->readList($this->remoteTodo);

        $done = 0;
        $i = 0;
        foreach ($files as $file) {
            try {
                set_time_limit(60);
                $this->debug('Work Cycle', 'Working Photo '.$file);
                if($this->doFile($file)) $done++;
                $this->debug('Work Cycle', 'Done Photo' . $file);
            } catch (\Throwable $e) {
                $this->error('Work Cycle', 'Failed Working Photo' . $file,$e);
            }
            $i++;
            if($i%25 == 0) $this->report('Work Cycle','Doing '. $done.' over '.$i.' running...');
        }
        $this->report('PhotoProductCardsExport', 'Done '.$done.' over '.count($files));
    }

    /**
     * @param $file
     * @return bool|int
     * @throws BambooException
     * @throws BambooFTPClientException
     * @throws \Exception
     */
    public function doFile($file) {
        $match = "";
        preg_match('/([0-9]{1,7}-[0-9]{1,8})( - |_|__)/u', $file, $match);

        $this->debug('doFile','Match: '.json_encode($match).' on file: '.$file);
        $names = pathinfo($file);
        if (!$this->ftp->fileExist($file)) return 0;

        $product = \Monkey::app()->repoFactory->create('Product')->findOneByStringId($match[1]);
        if($product == null) {
            $this->ftp->move($file, $this->calcRemoteErrorFolder());
            throw new BambooException('Product not found for: '.$match[1]);
        }
        $futureName = $this->calculatePhotoNameStandard($product, $file);

        $localName = $this->localTempFolder . $names['basename'];
        if (!$this->ftp->get($localName, $file, false)) {
            throw new BambooFTPClientException('Errore nell\'ottenere il file' . $file);
        }
        $res = $this->imageManager->processProductCardsPhoto($names['basename'], $futureName, 'iwes-fason', 'product-cards');
        $this->debug('doFile','Processed:');


        if($res){

            $url = "https://iwes-fason.s3-eu-west-1.amazonaws.com/product-cards/".$futureName['name'].'.'.$futureName['extension'];

            /** @var CRepo $prodCardPhotoRepo */
            $prodCardPhotoRepo = \Monkey::app()->repoFactory->create('ProductCardPhoto');

            /** @var CProductCardPhoto $prodCardPhoto */
            $prodCardPhoto = $prodCardPhotoRepo->findOneBy([
                'productId'=>$product->id,
                'productVariantId'=>$product->productVariantId
            ]);

            if(is_null($prodCardPhoto)){
                $prodCardPhoto = $prodCardPhotoRepo->getEmptyEntity();
                $prodCardPhoto->productId = $product->id;
                $prodCardPhoto->productVariantId = $product->productVariantId;
                $prodCardPhoto->productCardUrl = $url;
                $prodCardPhoto->smartInsert();
            }
        }

        $this->ftp->move($file, $this->calcRemoteFolder());
        unlink($this->localTempFolder. $names['basename']);

        return true;
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

    public function calcRemoteErrorFolder() {
        if(!isset($this->remoteErrorFull)) {
            $this->debug('yes','Hereeee:');
            $stringDate = (new \DateTime())->format('Y-m');
            if(!$this->ftp->fileExist($this->remoteErr.'/'.$stringDate)) {
                $this->ftp->mkDir($this->remoteErr.'/'.$stringDate);
            };
            $this->remoteErrorFull = $this->remoteErr.'/'.$stringDate;
        }
        $this->debug('yes',$this->remoteErrorFull);
        return $this->remoteErrorFull;

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
     * @throws BambooFTPClientException
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
     * @throws \bamboo\core\exceptions\RedPandaException
     */
    public function calculatePhotoNameStandard(CProduct $product, $origin){
        $futureName = [];
        $futureName['name'] = $product->printId();

        $pieces = explode(".",$origin);
        $futureName['extension'] = $pieces[(count($pieces)-1)];

        return $futureName;

    }
}