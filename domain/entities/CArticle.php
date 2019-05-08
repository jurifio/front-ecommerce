<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CArticle
 * @package bamboo\app\domain\entities
 */
class CArticle extends AEntity
{
    protected $entityTable = 'Article';
    protected $primaryKeys = array('id', 'langId');
}