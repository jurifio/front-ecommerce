<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\domain\repositories\CShipmentRepo;

/**
 * Class CShipment
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 *
 * @property CAddressBook $toAddress
 * @property CAddressBook $fromAddress
 * @property CCarrier $carrier
 * @property CObjectCollection $orderLine
 */
class CShipment extends AEntity
{
    protected $entityTable = 'Shipment';
    protected $primaryKeys = ['id'];

    const SCOPE_US_TO_USER = 'usToUser';
    const SCOPE_US_TO_SUPPLIER = 'usToSupplier';
    const SCOPE_SUPPLIER_TO_US = 'supplierToUs';
    const SCOPE_USER_TO_US = 'userToUs';

    /**
     * @return CShipment
     */
    public function sendToCarrier() {
        /** @var CShipmentRepo $repo */
        $repo = \Monkey::app()->repoFactory->create('Shipment');
        return $repo->sendShipmentToCarrier($this);
    }

    /**
     * @return string
     */
    public function printLabel()
    {
        /** @var CShipmentRepo $repo */
        $repo = \Monkey::app()->repoFactory->create('Shipment');
        $res = $repo->printShipmentLabel($this);
        \Monkey::app()->router->response()->setContentType('application/pdf');
        return $res;
    }
}