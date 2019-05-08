<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPost
 * @package bamboo\app\domain\entities
 */
class CPost extends AEntity
{
    protected $entityTable = 'Post';
    protected $primaryKeys = array('id','blogId');
}