<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\ILocalizedEntity;
use bamboo\core\utils\slugify\CSlugify;
use bamboo\domain\repositories\CProductDetailRepo;


/**
 * Class CProductDetailLabel
 * @package bamboo\app\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 *
 * @property CObjectCollection $productDetailLabelTranslation
 *
 */
class CProductDetailLabel extends AEntity implements ILocalizedEntity
{
    protected $entityTable = 'ProductDetailLabel';
    protected $primaryKeys = ['id'];

    /**
     * @return string
     */
    public function getLocalizedName()
    {
        if (!is_null($this->productDetailLabelTranslation->getFirst())) return $this->productDetailLabelTranslation->getFirst()->name;
        else return $this->em()->findChild('productDetailLabelTranslation', $this, true)->getFirst()->name;
    }

    /**
     * @return string
     */
    public function getLocalizedSlug()
    {
        $s = new CSlugify();
        return $s->slugify($this->getLocalizedName());
    }

    public function getAssociatedDetails(bool $returnObject)
    {
        $associatedDetailsCol = \Monkey::app()->dbAdapter->query('
                                SELECT psa.productDetailLabelId AS labelId, group_concat(DISTINCT psa.productDetailId) AS details
                                FROM ProductSheetActual psa
                                WHERE psa.productDetailLabelId = ?
                                GROUP BY psa.productDetailLabelId', [$this->id])->fetch();

        if(!$associatedDetailsCol) return false;

        $associatedDetailsColArr = explode(',', $associatedDetailsCol['details']);

        if(!$returnObject) return $associatedDetailsColArr;

        $productDetails = new CObjectCollection();
        /** @var CProductDetailRepo $productDetailRepo */
        $productDetailRepo = \Monkey::app()->repoFactory->create('ProductDetail');

        foreach ($associatedDetailsColArr as $productDetailId){
            $productDetail = $productDetailRepo->findOneBy(['id'=>$productDetailId]);
            if(!is_null($productDetail)) $productDetails->add($productDetailRepo->findOneBy(['id'=>$productDetailId]));
        }

        return $productDetails;
    }
}