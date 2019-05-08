<?php

namespace bamboo\domain\repositories;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CProductDetailRepo
 * @package bamboo\domain\repositories
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
class CProductDetailRepo extends ARepo
{
    /**
     * @param $string
     * @return \bamboo\core\db\pandaorm\entities\AEntity|null
     */
    public function fetchOrInsert($string)
    {
        $s = new CSlugify();
        $detail = $this->findOneBy(['slug'=>$s->slugify($string)]);
        if(is_null($detail)) $detail = $this->insertRawDetail($string);
        return $detail;
    }

    /**
     * @param $string
     * @param int $langId
     * @return \bamboo\core\db\pandaorm\entities\AEntity
     */
    public function insertRawDetail($string,$langId = 1) {
        $s = new CSlugify();
        $detail = $this->getEmptyEntity();
        $detail->slug = $s->slugify($string);
        $detail->smartInsert();

        $detailTranslation = \Monkey::app()->repoFactory->create('ProductDetailTranslation')->getEmptyEntity();
        $detailTranslation->langId = $langId;
        $detailTranslation->name = trim($string).' !';
        $detailTranslation->productDetailId = $detail->id;
        $detailTranslation->insert();

        return $detail;
    }
}