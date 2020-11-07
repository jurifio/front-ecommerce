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
class CAmazonVideoSender extends ACronJob
{
    protected $amazonCredentials = [];
    protected $ftpCredentials = [];

    /** @var CFTPClient */
    protected $ftp;

    /** @var ImageManager */
    protected $imageManager;

    protected $fileList;
    protected $localTempFolder;
    protected $remoteTodo = "/shootImport/newage2/todoVideo";
    protected $remoteDone = "/shootImport/newage2/done";

    /**
     * @param null $args
     */
    public function run($args = null)
    {
        $this->amazonCredentials = $this->getCredential();
        $this->ftpCredentials = $this->app->cfg()->fetch('miscellaneous', 'photoFTPClient');
        $this->localTempFolder = $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'tempFolder') . "-remaster/";
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
        $this->report('Video Sender', 'init Work');
        $files = $this->readList($this->remoteTodo);

        $done = 0;
        $i = 0;
        foreach ($files as $file) {
            try {
                set_time_limit(120);
                $this->debug('Work Cycle', 'Working Video '.$file);
                if($this->doFile($file)==1) $done++;
                $this->debug('Work Cycle', 'Done Video' . $file);
            } catch (\Throwable $e) {
                $this->error('Work Cycle', 'Failed Working Video' . $file,$e);
            }
            $i++;
            if($i%25 == 0) $this->report('Work Cycle','Doing '. $done.' over '.$i.' running...');
        }
        $this->report('VideoExport', 'Done '.$done.' over '.count($files));
    }

    /**
     * @param $file
     * @return int
     * @throws BambooException
     * @throws BambooFTPClientException
     */
    public function doFile($file) {
        try {
            $match = "";
            preg_match('/([0-9]{1,7}-[0-9]{1,8})( - |_|__)/u',$file,$match);
            $this->debug('doFile','Match: ' . json_encode($match) . ' on file: ' . $file);
            $names = pathinfo($file);
            $this->report('nome file','name ' . $file);
            $fileProduct = $names['basename'];
            $this->report('fileProduct','name ' . $fileProduct);
            $position = substr($fileProduct,-5,1);
            $this->report('position','number ' . $position);
            if (!$this->ftp->fileExist($file)) return 0;
            $product = \Monkey::app()->repoFactory->create('Product')->findOneByStringId($match[1]);
            if ($product == null) throw new BambooException('Product not found for: ' . $match[1]);
            $futureName = $this->calculatePhotoNameStandard($product,$file);
            $this->report('codeProduct',$futureName['code']);
            $this->report('extension',$futureName['extension']);
            $findpr=implode('-',$futureName['code']);

            $localName = $this->localTempFolder . $names['basename'];
            $this->report('localname',$localName);

            $insertVideo=\Monkey::app()->repoFactory->create('Product')->findOneBy(['id'=>$findpr[0],'productVariantId'=>$findpr[0]]);
            switch ($position) {
                case "1":
                    $insertVideo->dummyVideo = 'https://cdn.iwes.it/' . $product->productBrand->slug . '/' . $futureName['fileName'] . '.' . $futureName['extension'];
                    break;
                case "2":
                    $insertVideo->dummyVideo2 = 'https://cdn.iwes.it/' . $product->productBrand->slug . '/' . $futureName['fileName'] . '.' . $futureName['extension'];
                    break;
                case "3":
                    $insertVideo->dummyVideo3 = 'https://cdn.iwes.it/' . $product->productBrand->slug . '/' . $futureName['fileName'] . '.' . $futureName['extension'];
                    break;
                case "4":
                    $insertVideo->dummyVideo4 = 'https://cdn.iwes.it/' . $product->productBrand->slug . '/' . $futureName['fileName'] . '.' . $futureName['extension'];
                    break;
            }
            $this->report('videoUrl','https://cdn.iwes.it/' . $product->productBrand->slug . '/' . $futureName['fileName'] . '.' . $futureName['extension']);
            $insertVideo->update();
            if (!$this->ftp->get($localName,$file,false)) {
                throw new BambooFTPClientException('Errore nell\'ottenere il file' . $file);
            }
            $res=$this->imageManager->processVideoUploadProduct($localName,$futureName,'iwes',$product->productBrand->slug);
            $this->report('slug',$product->productBrand->slug);





            $this->ftp->move($file,$this->calcRemoteFolder());
            unlink($this->localTempFolder . $names['basename']);

            return 1;
        }catch(\Throwable $e){
            return 2;

        }
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
        $names = pathinfo($origin);
        $futureName['code'] = $product->printId();
        $futureName['name'] = $names['basename'];

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