<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CFixedPage
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 18/02/2019
 * @since 1.0
 *
 * @property CLang $lang
 * @property CFixedPageType $fixedPageType
 * @property CObjectCollection $fixedPagePopup
 * @property CFixedPageTemplate $fixedPageTemplateId
 *
 *
 */
class CFixedPage extends AEntity
{
    protected $entityTable = 'FixedPage';
    protected $primaryKeys = array('id','langId', 'fixedPageTypeId');

    /**
     * @return bool
     */
    public function getActivePopup()
    {
        return $this->fixedPagePopup->findOneByKey('isActive', 1);
    }

    /**
     * @return bool
     */
    public function havePopup(): bool
    {
        if($this->getActivePopup()) return true;

        return false;
    }
}