<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPostTag
 * @package bamboo\app\domain\entities
 */
class CPostTag extends AEntity
{
    protected $entityTable = 'PostTag';
    protected $primaryKeys = array('id');
}