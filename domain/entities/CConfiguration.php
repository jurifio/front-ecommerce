<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductSku
 * @package bamboo\app\domain\entities
 */
class CConfiguration extends AEntity
{
    protected $entityTable = 'Configuration';
    protected $primaryKeys = ['id'];
}