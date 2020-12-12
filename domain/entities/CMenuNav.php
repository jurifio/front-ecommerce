<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\ILocalizedEntity;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CMenuNav
 * @package bamboo\app\domain\entities
 */
class CMenuNav extends AEntity implements ILocalizedEntity
{



    protected $entityTable = 'MenuNav';
    protected $primaryKeys = ['id'];

    /**
     * @return string
     */
    public function getLocalizedName() {
        if(!is_null($this->MenuNavTranslation->getFirst())) return $this->MenuNavTranslation->getFirst()->captionTitle;
        else return $this->em()->findChild('menuNavTranslation',$this,true)->getFirst()->captionTitle;
    }

    /**
     * @return string
     */
    public function getLocalizedSlug()
    {
        $s = new CSlugify();
        return $s->slugify($this->getLocalizedName());
    }

}