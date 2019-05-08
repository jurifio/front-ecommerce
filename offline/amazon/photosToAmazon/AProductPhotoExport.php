<?php

namespace bamboo\ecommerce\offline\amazon\photosToAmazon;

use bamboo\domain\entities\CProduct;
use bamboo\core\application\AApplication;
use bamboo\core\base\CFTPClient;
use bamboo\core\exceptions\RedPandaException;
use bamboo\core\exceptions\RedPandaFTPClientException;
use bamboo\core\utils\amazonPhotoManager\ImageManager;
use bamboo\core\utils\amazonPhotoManager\S3Manager;

/**
 * Class S3FileManager
 * @package AwsS3
 * @extends Logger
 * @author Emanuele Serini <e.serini@gmail.com>
 *
 * @api
 */
abstract class AProductPhotoExport {

    /**
     * @var CProduct
     */
    protected $productEdit;
    /**
     * @var $app AApplication;
     */
    protected $app;

    protected $folderSeparator = "/";
    protected $credential;
    protected $list = [];
    protected $ftpConfig;
    /** @var CFTPClient $ftp */
    protected $ftp;
    protected $repo;
    /** @var \bamboo\core\application\AComponent  */
    protected $log;

    protected $tempFolder;
    protected $photoDir = '/shootImport/newage/';

    /**
     * @param AApplication $app
     * @param $ftpConfig
     * @param array $credential
     */
    public function __construct(AApplication $app, $ftpConfig, array $credential){

        $this->credential = $credential;
        $this->app = $app;
        $this->ftpConfig = $ftpConfig;
        $this->repo = \Monkey::app()->repoFactory->create('Product');
        $this->log = $app->logger;
        $this->tempFolder = $this->app->rootPath().$this->app->cfg()->fetch('paths','tempFolder')."/";
        $app->vendorLibraries->load("amazon2723");
        $ftp = $this->getFtp();
        $ftp->changeDir($this->photoDir);
        $this->list = $this->readList();
    }

    /**
     * @param $productId
     * @param $productVariantId
     * @return int
     * @throws RedPandaFTPClientException
     * @throws \Exception
     */
    public function importFromAztecCode($productId,$productVariantId){

        $this->productEdit = $this->repo->findOneBy(['id'=>$productId,'productVariantId'=>$productVariantId]);
        if(!$this->productEdit != null && !$this->productEdit)  throw new \Exception('ProductNotFound: '.$productId.'-'.$productVariantId);

        $files = preg_grep('#'.$this->productEdit->id.'-'.$this->productEdit->productVariantId.'__#', $this->list);
        $futureDummy = "";
        $ids = [];
        echo "trovate ". count($files). " foto<br>";
        if(count($files) == 0 ) return 0;
        $mgr = new ImageManager(new S3Manager($this->credential), $this->app,$this->tempFolder);
        foreach($files as $origin){
            $names = pathinfo($origin);
            if(!$this->ftp->fileExist($origin)) continue;
            $futureName = $this->calculatePhotoNameStandard($names['basename']);
	        try{
		        if(!$this->ftp->get(($this->tempFolder.$names['basename']),$origin,false)) {
			        throw new RedPandaFTPClientException('Errore nell\'ottenere il file'. $origin);
			        break;
		        }
	        } catch(\Throwable $e) {
		        throw new RedPandaFTPClientException('Errore nell\'ottenere il file'. $origin,[],0, $e);
	        }

	        if(($res = $mgr->process($names['basename'],$futureName,'iwes',$this->productEdit->productBrand->slug))<1){
                throw new \Exception('errore nel processo, caricati meno di 3 file');
            }
            foreach($res as $key=>$val){
                if(empty($futureDummy)){
                    $futureDummy = $val;
                }
                $ids[] = $this->app->dbAdapter->insert('ProductPhoto',array('name'=>$val, 'order'=>$futureName['number'],'size'=>$key));
            }
            $this->ftp->move($origin, $this->photoDir."done");
	        unlink($this->tempFolder.$names['basename']);
        }
        $count = 0;
        foreach($ids as $id){
            $this->app->dbAdapter->insert("ProductHasProductPhoto",array("productId"=>$this->productEdit->id,"productVariantId"=>$this->productEdit->productVariantId,"productPhotoId"=>$id));
            $count++;
        }

        if ($count) {
            \Monkey::app()->eventManager->triggerEvent(
                'assignPhotosToProduct',
                [
                    'product' => $this->productEdit,
                    'photoIds' => $ids,
                    'release' => 'release'
                ]
            );
        }

        try{
            $this->setDummyPicture($futureDummy);
        }catch (\Throwable $e){
            $this->log->applicationLog('PhotoExport', 'ERROR', 'Set DummyPicture','dummypicture '.$futureDummy.' for product '.$this->productEdit->id.'-'.$this->productEdit->productVariantId, $e);
        }
        return $count;
    }

    public function getFtp()
    {
        if(empty($this->ftp)){
            $this->ftp = new CFTPClient($this->app,$this->ftpConfig);
        }
        return $this->ftp;
    }

    public function calculatePhotoNameStandard($origin){
        $futureName = [];
        $futureName['name'] = $this->productEdit->id."-".$this->productEdit->productVariantId;

        $pieces = explode(".",$origin);
        $futureName['extension'] = $pieces[(count($pieces)-1)];
        unset($pieces[(count($pieces)-1)]);
        $rePieces = implode(".",$pieces);
        $pieces = explode("_",$rePieces);
        if(!is_numeric($pieces[(count($pieces)-1)])){
            $pieces = explode("-",$rePieces);
        }
        if(!is_numeric($pieces[(count($pieces)-1)])){
            throw new RedPandaException('cant find the nuber of the foto');
        }
        $futureName['number'] = $pieces[(count($pieces)-1)];

        return $futureName;

    }

    /**
     * @return array
     */
    public abstract function readList();

    public function setDummyPicture($futureDummy)
    {
        /** insert DummyPicture */
            if(!empty($futureDummy) && (!isset($this->productEdit->dummyPicture) || empty($this->productEdit->dummyPicture) || $this->productEdit->dummyPicture == 'bs-dummy-16-9.png')){
                $futureDummy = $this->app->cfg()->fetch("general","product-photo-host").''.$this->productEdit->productBrand->slug.'/'.$futureDummy;
                try {

                    $this->productEdit->dummyPicture = $futureDummy;
                    $this->productEdit->update();

                } catch (\Throwable $e) {
                    $this->app->router->response()->raiseUnauthorized();
                }
                //$this->app->dbAdapter->update('Product',['dummyPicture'=>$futureDummy],['id'=>$this->productEdit->id,'productVariantId'=>$this->productEdit->productVariantId]);
            }
    }
}