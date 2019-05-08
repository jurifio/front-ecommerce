<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CSerialNumber;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\entities\ILocalizedEntity;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class COrderStatus
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
 */
class COrderStatus extends AEntity implements ILocalizedEntity
{
    protected $entityTable = 'OrderStatus';
	protected $primaryKeys = ['id'];

    /**
     * @return string
     */
    public function getLocalizedName() {
        if(!is_null($this->orderStatusTranslation->getFirst())) return $this->orderStatusTranslation->getFirst()->title;
        else return $this->em()->findChild('orderStatusTranslation',$this,true)->getFirst()->title;
    }

    /**
     * @return string
     */
    public function getLocalizedSlug()
    {
        return (new CSlugify())->slugify($this->getLocalizedName());
    }
}