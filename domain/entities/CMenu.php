<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\ILocalizedEntity;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CMenu
 * @package bamboo\app\domain\entities
 */
class CMenu extends AEntity implements ILocalizedEntity
{



    protected $entityTable = 'Menu';
    protected $primaryKeys = ['id'];

    /**
     * @return string
     */
    public function getLocalizedName() {
        if(!is_null($this->MenuTranslation->getFirst())) return $this->MenuTranslation->getFirst()->name;
        else return $this->em()->findChild('menuTranslation',$this,true)->getFirst()->name;
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