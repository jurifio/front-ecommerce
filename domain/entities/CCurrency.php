<?php

namespace bamboo\domain\entities;

use bamboo\core\application\AApplication;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\utils\amazonPhotoManager\ImageManager;
use bamboo\core\utils\amazonPhotoManager\S3Manager;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CCurrency
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 08/02/2019
 * @since 1.0
 */
class CCurrency extends AEntity
{
    protected $entityTable = 'Currency';
    protected $primaryKeys = ['id'];
}