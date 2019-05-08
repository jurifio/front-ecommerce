<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
/**
 * Class COrderLine
 * @package bamboo\app\domain\entities
 * @property CProductSku $productSku
 * @property COrder $order
 */
class COrderLineHasShipment extends AEntity
{
    protected $entityTable = 'OrderLineHasShipment';
    protected $primaryKeys = ['orderLineId','orderId','shipmentId'];
}