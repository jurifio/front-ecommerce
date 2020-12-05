<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CTagTranslation
 * @package bamboo\app\domain\entities
 */
class CTagExclusiveTranslation extends AEntity
{
    protected $entityTable = 'TagExclusiveTranslation';
    protected $primaryKeys = ['tagExclusiveId','langId'];
}