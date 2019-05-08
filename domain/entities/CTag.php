<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\ILocalizedEntity;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CTag
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 18/06/2018
 * @since 1.0
 *
 * @property CObjectCollection $tagTranslation;
 * @property CProductHasTag $productHasTag
 *
 */
class CTag extends AEntity implements ILocalizedEntity
{

    const NEW_SEASON = 93;

    protected $entityTable = 'Tag';
    protected $primaryKeys = ['id'];

    /**
     * @return string
     */
    public function getLocalizedName()
    {
        if (!is_null($this->tagTranslation->getFirst())) return $this->tagTranslation->getFirst()->name;
        else return $this->em()->findChild('tagTranslation', $this, true)->getFirst()->name;
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