<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPostTranslation
 * @package bamboo\app\domain\entities
 */
class CPostTranslation extends AEntity
{
    protected $entityTable = 'PostTranslation';
    protected $primaryKeys = array('postId','blogId','langId');
}