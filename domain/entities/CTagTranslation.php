<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CTagTranslation
 * @package bamboo\app\domain\entities
 */
class CTagTranslation extends AEntity
{
    protected $entityTable = 'TagTranslation';
    protected $primaryKeys = ['tagId','langId'];
}