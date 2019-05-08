<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CInvoice
 * @package bamboo\app\domain\entities
 */
class CInvoice extends AEntity
{
    const FAT_ORDER = 11;
    const REC_ORDER = 12;
    protected $entityTable = 'Invoice';
    protected $primaryKeys = array('id');
}