<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 27/07/2015
 * Time: 10:13
 */

namespace bamboo\ecommerce\offline\amazon;

use bamboo\core\jobs\ACronJob;
use bamboo\ecommerce\offline\amazon\photosToAmazon\CProductPhotoExport;
use bamboo\ecommerce\offline\amazon\photosToAmazon\CProductPhotoExportOverWrite;
use bamboo\ecommerce\offline\amazon\photosToAmazon\CProductPhotoExportReview;

class CAmazonImportStarter extends ACronJob
{

    /**
     * @param null $args
     */
    public function run($args = null)
    {
        switch ($args) {
            case 'recover':
                $this->recoverWork();
                break;
            case 'overwrite':
                $this->overwrite();
                break;
            default:
                $this->defaultWork();
                break;
        }
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
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaFTPClientException
     */
    public function defaultWork()
    {
        $this->report( 'PhotoExport', 'Started Defaul Work', []);

        $config = $this->app->cfg()->fetch('miscellaneous', 'photoFTPClient');

        $time = time();
        $photoExport = new CProductPhotoExport($this->app, $config, $this->getCredential());
        $todo = $this->app->dbAdapter->query("SELECT DISTINCT p.id, p.productVariantId
                                        FROM Product p
                                        WHERE (p.id,productVariantId) NOT IN 
                                        (SELECT DISTINCT productId, productVariantId FROM ProductHasProductPhoto) order by creationDate desc limit 15000", [])->fetchAll();


        $this->report( 'PhotoExport', 'Export Start', 'Read ' . count($todo) . ' products to read');
        $done = [];
        $count = 0;

        foreach ($todo as $val) {
            set_time_limit(180);
            try{
                $this->debug( 'PhotoExport', 'Locking for product photo'.$val['id'].'-'.$val['productVariantId'], $val);
                $i = $photoExport->importFromAztecCode($val['id'], $val['productVariantId']);
                if ($i > 0) {
                    $count = $count + $i;
                    $this->report( 'PhotoExport', 'Ended locking for product Photos, found: ' . $i, $val);
                    $done[] = $val;
                } else {
                    $this->debug( 'PhotoExport', 'Ended locking for product Photos, found: ' . $i, $val);
                }
            }catch (\Throwable $e){
                $this->error( 'PhotoExport', 'Error while processing: ' . $val['id'].'-'.$val['productVariantId'], $e);
            }
        }
        $this->report( 'PhotoExport', 'Matched ' . $count . ' products list in context');
    }

    /**
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaFTPClientException
     */
    public function recoverWork()
    {

        $this->report( 'PhotoExport', 'Started Recover Work', []);

        $config = $this->app->cfg()->fetch('miscellaneous', 'photoFTPClient');

        $time = time();
        $photoExport = new CProductPhotoExportReview($this->app, $config, $this->getCredential());
        $todo = $this->app->dbAdapter->query("   SELECT DISTINCT p.id, p.productVariantId
                                        FROM Product p
                                        WHERE
                                        (p.id, productVariantId) NOT IN (SELECT DISTINCT productId, productVariantId FROM ProductHasProductPhoto)", [])->fetchAll();


        $this->report( 'PhotoExport', 'Read ' . count($todo) . ' products to read', $todo);
        $done = [];
        $count = 0;
        foreach ($todo as $val) {
            set_time_limit(45);
            $this->report( 'PhotoExport', 'Locking for product photo', $val);
            $i = $photoExport->importFromAztecCode($val['id'], $val['productVariantId']);
            if ($i > 0) {
                $count = $count + $i;
            }
            $this->report( 'PhotoExport', 'Ended locking for product Photos, found: ' . $i, $val);
            $done[] = $val;
        }
        $this->report( 'PhotoExport', 'Matched ' . $count . ' products list in context', $done);
    }

    public function overwrite()
    {
        $this->report( 'PhotoExport', 'Started Overwrite Work', []);

        $config = $this->app->cfg()->fetch('miscellaneous', 'photoFTPClient');

        $time = time();
        $photoExport = new CProductPhotoExportOverWrite($this->app, $config, $this->getCredential());
        $todo = $photoExport->readList();

        $this->report( 'PhotoExport', 'Read ' . count($todo) . ' products to read', $todo);
        $done = [];
        $count = 0;

        foreach ($todo as $val) {
            set_time_limit(30);
            try {
                $this->report( 'PhotoExport', 'Overwriting Photo ', $val);
                $match = "";
                preg_match('/([0-9]{1,6}-[0-9]{1,7})( - |_|__)/u', $val, $match);
                $code = explode('-', $match[1]);
                $i = $photoExport->importFromAztecCode($code[0], $code[1]);
                if ($i > 0) {
                    $count = $count + $i;
                }
                $this->report( 'PhotoExport', 'Ended locking for product Photos, found: ' . $i, $val);
                $done[] = $val;
            } catch (\Throwable $e) {
                $this->error( 'OverwriteFail', 'failed while overwriting file ' . $val, $e);
            }
        }
        $this->report( 'PhotoExport', 'Matched ' . $count . ' products list in context', $done);
    }

}