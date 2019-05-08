<?php

namespace bamboo\domain\entities;

use bamboo\core\application\AApplication;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\utils\amazonPhotoManager\ImageManager;
use bamboo\core\utils\amazonPhotoManager\S3Manager;
use bamboo\core\utils\slugify\CSlugify;

/**
 * Class CRbacRole
 * @package bamboo\app\domain\entities
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CRbacRole extends AEntity
{
    const WORKER = 28;

    protected $entityTable = 'RbacRole';
    protected $primaryKeys = array('id');
}