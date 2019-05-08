<?php

namespace bamboo\ecommerce\offline\amazon\photosToAmazon;

/**
 * Class S3FileManager
 * @package AwsS3
 * @extends Logger
 * @author Emanuele Serini <e.serini@gmail.com>
 *
 * @api
 */
class CProductPhotoExport extends AProductPhotoExport {

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
                if($dir != "done" && $dir != 'overwrite') {
                    $aap = $this->ftp->nList($dir);
                    $merge = array_merge($merge,$aap);
                    $dirsN++;
                }
            }
            $this->log->applicationLog('PhotoExport', 'Report', 'Found Dirs', $dirsN.' dirs found',$dirs);
            $this->list = $merge;
            $this->log->applicationLog('PhotoExport', 'Report', 'Found Files', count($this->list).' files found',$this->list);
        }
        return $this->list;
    }
}