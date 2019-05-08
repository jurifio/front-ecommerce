<?php

namespace bamboo\ecommerce\offline\amazon\photosToAmazon;

use Aws\CloudFront\Exception\Exception;
use bamboo\domain\entities\CProduct;
use bamboo\core\application\AApplication;
use bamboo\core\base\CFTPClient;
use bamboo\core\base\CLogger;
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
class CProductPhotoExportOverWrite extends AProductPhotoExport {

    /**
     * @return array
     */
    public function readList()
    {
        if(empty($this->list)) {
            $dirs = $this->ftp->nList();

            $dirsN = 0;
            $merge = [];
            foreach($dirs as $dir){
                if($dir == "overwrite") {
                    $aap = $this->ftp->nList($dir);
                    $merge = array_merge($merge,$aap);
                    $dirsN++;
                }
            }
            $this->log->applicationLog('PhotoExport', 'Report', 'Found Dirs', $dirsN.' files found',$dirs);
            $this->list = $merge;
            $this->log->applicationLog('PhotoExport', 'Report', 'Found Files', count($this->list).' files found',$this->list);
        }
        return $this->list;
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
        if($this->productEdit == null)  throw new \Exception('ProductNotFound: '.$productId.' - '.$productVariantId);
        if($this->productEdit->productSku->isEmpty()) throw new \Exception('sku non presenti');

        //$filesCount = preg_match_all('^('.$this->productEdit->id.')-('.$this->productEdit->productVariantId.')__(.*)_([\d]{1,4})\.([a-zA-Z0-9]{2,4})$\w',$list,$files);
        $files = preg_grep('^'.$this->productEdit->id.'-'.$this->productEdit->productVariantId.'^', $this->list);
        $futureDummy = "";
        $ids = [];
        echo "trovate ". count($files). " foto<br>";
        if(count($files) == 0 ) return 0;
        $count = 0;
        var_dump($files);
        $mgr = new ImageManager(new S3Manager($this->credential),$this->app,$this->tempFolder);
        foreach($files as $origin){
            $names = pathinfo($origin);
            $futureName = $this->calculatePhotoNameStandard($names['basename']);
            try {
                if(!$this->ftp->fileExist($origin)) continue;
                if(!$this->ftp->get(($this->tempFolder.$names['basename']),$origin)) {
                    throw new RedPandaFTPClientException('Errore nell\'ottenere il file1');
                }
                $this->ftp->move($origin, $this->photoDir."done/overwrite");
            } catch (\Exception $e) {
                throw new RedPandaFTPClientException('Errore nell\'ottenere il file2 '. $origin. ' | '.$this->tempFolder.$names['basename'],[],0, $e);
            }

            if(($res = $mgr->process($names['basename'],$futureName,'iwes',$this->productEdit->productBrand->slug))<1){
                throw new \Exception('errore nel processo, caricati meno di 3 file');
            }

            $count++;
            unlink($this->tempFolder.$names['basename']);
        }

        return $count;
    }
}