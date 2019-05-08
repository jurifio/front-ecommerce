<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CPage
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CPage extends AEntity
{
    protected $entityTable = 'Page';
    protected $primaryKeys = array('id');
}