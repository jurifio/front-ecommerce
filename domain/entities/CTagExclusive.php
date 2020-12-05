<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\ILocalizedEntity;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CTag
 * @package bamboo\app\domain\entities
 */
class CTagExclusive extends AEntity implements ILocalizedEntity
{

    const NEW_SEASON = 97;

    protected $entityTable = 'TagExclusive';
    protected $primaryKeys = ['id'];

    /**
     * @return string
     */
    public function getLocalizedName() {
        if(!is_null($this->tagExclusiveTranslation->getFirst())) return $this->tagExclusiveTranslation->getFirst()->name;
        else return $this->em()->findChild('tagExclusiveTranslation',$this,true)->getFirst()->name;
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