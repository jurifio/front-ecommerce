<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPostTagTranslation
 * @package bamboo\app\domain\entities
 */
class CPostTagTranslation extends AEntity
{
    protected $entityTable = 'PostTagTranslation';
    protected $primaryKeys = array('postTagId','langId');
}