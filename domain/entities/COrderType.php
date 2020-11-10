<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class COrderStatusTranslation
 * @package bamboo\app\domain\entities
 */
class COrderType extends AEntity
{
    protected $entityTable = 'OrderType';
    protected $primaryKeys = ['id'];
}