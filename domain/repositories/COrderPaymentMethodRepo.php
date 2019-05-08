<?php


namespace bamboo\domain\repositories;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;


/**
 * Class CProductRepo
 * @package bamboo\app\domain\repositories
 */
class COrderPaymentMethodRepo extends ARepo
{
    /**
     * @return CObjectCollection
     */
    public function listByAvailable()
    {
        $sql = "SELECT id FROM OrderPaymentMethod WHERE isActive = 1";
        $payment = $this->em()->findBySql($sql);
        //$payment->reorder('name');
        return $payment;
    }
}
