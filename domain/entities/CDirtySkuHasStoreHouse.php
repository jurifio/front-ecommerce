<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CDirtySku
 * @package bamboo\app\domain\entities
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 *
 *
 */
class CDirtySkuHasStoreHouse extends AEntity
{
    protected $entityTable = 'DirtySkuHasStoreHouse';
    protected $primaryKeys = ['id','storeHouseId'];
	protected $isCacheable = false;

	CONST EXCLUDED_STATUS = 'exclude';
}