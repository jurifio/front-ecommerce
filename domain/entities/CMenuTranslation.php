<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CMenuTranslation
 * @package bamboo\app\domain\entities
 */
class CMenuTranslation extends AEntity
{
    protected $entityTable = 'MenuTranslation';
    protected $primaryKeys = ['menuTranslationId','langId'];
}