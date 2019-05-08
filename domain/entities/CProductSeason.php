<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductSeason
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CProductSeason extends AEntity
{

    protected $entityTable = 'ProductSeason';
    protected $primaryKeys = ['id'];
}