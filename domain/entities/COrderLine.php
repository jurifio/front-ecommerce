<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\domain\repositories\CProductSkuRepo;

/**
 * Class COrderLine
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
 * @property CProductSku $productSku
 * @property COrder $order
 * @property CProduct $product
 * @property COrderLineStatus $orderLineStatus
 * @property CWarehouseShelfPosition $warehouseShelfPosition
 */
class COrderLine extends AEntity
{
    const INIT_STATUS = 'ORD_PENDING';
    protected $entityTable = 'OrderLine';
    protected $primaryKeys = ['id', 'orderId'];
    protected $isCacheable = false;

    /**
     * @return string
     */
    public function printLineId()
    {
        return $this->orderId . '-' . $this->id;
    }

    /**
     * @param null $statusCode
     * @return CObjectCollection|AEntity|null
     */
    public function getStatusLog($statusCode = null)
    {
        /** @var CObjectCollection $res */
        $res = \Monkey::app()->repoFactory->create('Order')->getStatusHistory($this, $statusCode);
        if ($statusCode) {
            if ($res->count()) return $res->getFirst();
            else return null;
        } else {
            return $res;
        }
    }

    /**
     * @return COrderLineStatus|null
     */
    public function getNextOkLineStatus()
    {
        return $this->orderLineStatus->nextOrderLineStatus;
    }

    /**
     * @return COrderLineStatus|null
     */
    public function getNextErrLineStatus()
    {
        return $this->orderLineStatus->errOrderLineStatus;
    }

    /**
     * @return bool
     */
    public function isFriendChangable()
    {
        if ($this->orderLineStatus->phase <= 4 &&
            !$this->getAlternativesSkus()->isEmpty()) return true;
        return false;
    }

    /**
     * @return \bamboo\core\base\CObjectCollection
     */
    public function getAlternativesSkus()
    {
        if (!isset($this->fields['alternativeSkus'])) {
            /** @var CProductSkuRepo $productSkuRepo */
            $productSkuRepo = \Monkey::app()->repoFactory->create('ProductSku');
            $this->fields['alternativeSkus'] = $productSkuRepo->findDisposableSkusFromSku($this->productSku);
        }
        return $this->fields['alternativeSkus'];
    }

    /**
     * @return bool
     */
    public function isStatusManageable()
    {
        return (bool)$this->orderLineStatus->isManageable ?? false;
    }

}