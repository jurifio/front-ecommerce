<?php

namespace bamboo\domain\entities;

use bamboo\core\application\AApplication;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\utils\amazonPhotoManager\S3Manager;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CProduct
 *
 * @date 01/11/2014
 * @since 0.0.1
 */
class CProductHasProductCategory extends AEntity
{
	protected $entityTable = 'ProductHasProductCategory';
	protected $primaryKeys = ['productId', 'productVariantId'];
}