<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CProductBrandTranslation
 * @package bamboo\app\domain\entities
 *
 *
 * @property CProductBrand $productBrand
 * @property CShop $shop
 * @property CLang $lang
 */
class CProductBrandTranslation extends AEntity
{
    protected $entityTable = 'ProductBrandTranslation';
	protected $primaryKeys = ['id'];
} 