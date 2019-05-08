<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPostCategoryTranslation
 * @package bamboo\app\domain\entities
 */
class CPostCategoryTranslation extends AEntity
{
    protected $entityTable = 'PostCategoryTranslation';
    protected $primaryKeys = array('postCategoryId','langId');
}