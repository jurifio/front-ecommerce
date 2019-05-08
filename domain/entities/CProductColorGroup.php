<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\ILocalizedEntity;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CProductColorGroup
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
 *
 *
 * @property CObjectCollection $productColorGroupTranslation
 * @property CProductColorGroupHasPrestashopColorOption $productColorGroupHasPrestashopColorOption
 *
 *
 */
class CProductColorGroup extends AEntity implements ILocalizedEntity
{
    protected $entityTable = 'ProductColorGroup';
    protected $primaryKeys = ['id'];

    /**
     * @return mixed|string
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function getLocalizedName() {
        if(!is_null($this->productColorGroupTranslation->getFirst())) return $this->productColorGroupTranslation->getFirst()->name;
        else return $this->em()->findChild('productColorGroupTranslation',$this,true)->getFirst()->name;
    }

    /**
     * @return string
     * @throws \bamboo\core\exceptions\RedPandaORMException
     */
    public function getLocalizedSlug()
    {
        $s = new CSlugify();
        return $s->slugify($this->getLocalizedName());
    }

}