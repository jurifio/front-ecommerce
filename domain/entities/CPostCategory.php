<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPostCategory
 * @package bamboo\app\domain\entities
 */
class CPostCategory extends AEntity
{
    protected $entityTable = 'PostCategory';
    protected $primaryKeys = array('id');
}