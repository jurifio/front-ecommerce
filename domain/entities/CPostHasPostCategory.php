<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPostHasPostCategory
 * @package bamboo\app\domain\entities
 */
class CPostHasPostCategory extends AEntity
{
    protected $entityTable = 'PostHasPostCategory';
    protected $primaryKeys = array('postId','postBlogId', 'postCategoryId');
}