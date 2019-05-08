<?php
namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooException;

/**
 * Class CEvents
 * @package bamboo\domain\entities
 */
class CEvents extends AEntity {
    protected $entityTable = 'Events';
    protected $primaryKeys = ['id'];
}
