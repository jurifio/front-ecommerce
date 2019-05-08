<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\ILocalizedEntity;
use bamboo\core\utils\slugify\CSlugify;


/**
 * Class CProductCategory
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 * @property CObjectCollection $productCategoryTranslation
 * @property CObjectCollection $marketplaceAccountCategory
 * @property CObjectCollection $dictionaryCategory
 * @property CObjectCollection $product
 * @property CObjectCollection $productSheetModelPrototype
 * @property CObjectCollection $productCategoryHasMarketplaceAccountCategory
 * @property CObjectCollection $productHasProductCategory
 * @property CObjectCollection $productSheetModelPrototypeHasProductCategory
 * @property CProductCategoryHasPrestashopCategory $productCategoryHasPrestashopCategory
 *
 */
class CProductCategory extends AEntity implements ILocalizedEntity
{
    protected $entityTable = 'ProductCategory';

    /**
     * @param string $separator
     * @param \bamboo\core\intl\CLang|null $lang
     * @return string
     */
    public function getLocalizedPath($separator = ", ",\bamboo\core\intl\CLang $lang = null)
    {
        return implode($separator, $this->getLocalizedPathArray($lang));
    }

    /**
     * @param \bamboo\core\intl\CLang|null $lang
     * @return array
     */
    public function getLocalizedPathArray(\bamboo\core\intl\CLang $lang = null)
    {
        $cats = \Monkey::app()->categoryManager->categories()->getPath($this->id);
        $type = [];
        foreach ($cats as $cat) {
            if ($cat['id'] == 1) continue;
            if($lang instanceof \bamboo\core\intl\CLang) {
                $type[] = \Monkey::app()->repoFactory->create('ProductCategory',$lang)->findOne([$cat['id']])->getLocalizedName();
            } else {
                $type[] = \Monkey::app()->repoFactory->create('ProductCategory')->findOne([$cat['id']])->getLocalizedName();
            }
        }
        return $type;
    }

    /**
     * @return array
     */
    public function getObjectPathArray()
    {
        $ret = [];
        foreach(\Monkey::app()->categoryManager->categories()->getPath($this->id) as $cat)
            $ret[] = \Monkey::app()->repoFactory->create('ProductCategory')->findOne([$cat['id']]);
        return $ret;
    }

    /**
     * @return bool|AEntity
     */
    public function getGenderCategory()
    {
        $cats = \Monkey::app()->categoryManager->categories()->getPath($this->id);
        if(isset($cats[1])) return \Monkey::app()->repoFactory->create('ProductCategory')->findOne([$cats[1]['id']]);
        return false;
    }

    /**
     * @return string
     */
    public function getLocalizedName()
    {
        if($this->id == 1) return "";
	    elseif(!is_null($this->productCategoryTranslation->getFirst())) return $this->productCategoryTranslation->getFirst()->name;
        else return $this->em()->findChild('productCategoryTranslation',$this,true)->getFirst()->name;
    }

    /**
     * @return string
     */
    public function getLocalizedSlug()
    {
        $s = new CSlugify();
        return $s->slugify($this->getLocalizedName());
    }

    /**
     * @param bool $localized
     * @return mixed
     */
    public function getSlug($localized = true) {
        if($localized &&
            !$this->productCategoryTranslation->isEmpty() &&
            !empty($this->productCategoryTranslation->getFirst()->slug)
                ) return $this->productCategoryTranslation->getFirst()->slug;
        return $this->fields['slug'];
    }
}