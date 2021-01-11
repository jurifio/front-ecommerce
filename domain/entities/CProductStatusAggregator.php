<?php

namespace bamboo\domain\entities;

use bamboo\core\application\AApplication;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\utils\amazonPhotoManager\ImageManager;
use bamboo\core\utils\amazonPhotoManager\S3Manager;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CProductStatusAggregator
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CProductStatusAggregator extends AEntity
{
    protected $entityTable = 'ProductStatusAggregator';
}