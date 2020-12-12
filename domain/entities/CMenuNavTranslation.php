<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CMenuNavTranslation
 * @package bamboo\app\domain\entities
 */
class CMenuNavTranslation extends AEntity
{
    protected $entityTable = 'MenuNavTranslation';
    protected $primaryKeys = ['menuNavTranslationId','langId'];
}