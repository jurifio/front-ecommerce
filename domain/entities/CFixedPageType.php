<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CFixedPageType
 * @package bamboo\app\domain\entities
 */
class CFixedPageType extends AEntity
{
    CONST SUPPORT = 1;
    CONST SPECIAL = 2;
    CONST LEAD = 3;

    protected $entityTable = 'FixedPageType';
    protected $primaryKeys = array('id');
}