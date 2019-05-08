<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductColor
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CProductVariant extends AEntity
{
    protected $entityTable = 'ProductVariant';
	protected $primaryKeys = ['id'];
}