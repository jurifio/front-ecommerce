<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPageTranslation
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CPageTranslation extends AEntity
{
    protected $entityTable = 'PageTranslation';
    protected $primaryKeys = array('pageId','langId');
}