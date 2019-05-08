<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPostHasPostTag
 * @package bamboo\app\domain\entities
 */
class CPostHasPostTag extends AEntity
{
    protected $entityTable = 'PostHasPostTag';
    protected $primaryKeys = array('postId','postBlogId', 'postTagId');
}