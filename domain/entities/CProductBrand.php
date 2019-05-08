<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductBrand
 * @package bamboo\app\domain\entities
 *
 *
 * @property CProductBrandHasPrestashopManufacturer $productBrandHasPrestashopManufacturer
 *
 */
class CProductBrand extends AEntity
{
    protected $entityTable = 'ProductBrand';
	protected $primaryKeys = ['id'];
} 