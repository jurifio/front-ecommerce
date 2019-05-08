<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CDirtyProduct;


/**
 * Class CArticleRepo
 * @package bamboo\app\domain\repositories
 */
class CDirtyProductRepo extends ARepo
{

    /**
     * delete a dirty product entarely
     * @param CDirtyProduct $dirtyProduct
     * @param bool $forReal
     * @return bool
     */
    public function deleteDirtyProductTotalCascade(CDirtyProduct $dirtyProduct, $forReal = false) {
        foreach ($dirtyProduct->dirtyDetail as $dirtyDetail) {
            $dirtyDetail->delete();
        }
        foreach ($dirtyProduct->dirtyPhoto as $dirtyPhoto) {
            $dirtyPhoto->delete();
        }
        foreach ($dirtyProduct->dirtySku As $dirtySku){
            $dirtySku->delete();
        }
        $dirtyProduct->extend->delete();
        $dirtyProduct->delete();
        return true;
    }
}