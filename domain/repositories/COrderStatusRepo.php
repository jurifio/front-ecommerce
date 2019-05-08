<?php


namespace bamboo\domain\repositories;
use bamboo\domain\entities\CUserAddress;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;

/**
 * Class COrderStatusRepo
 * @package bamboo\app\domain\repositories
 */
class COrderStatusRepo extends ARepo
{
    public function listByPossibleStatuses($start = 'CRT')
    {
        $id = $this->app->dbAdapter->select('OrderStatus',["code"=>$start])->fetch()['id'];

        return $this->em()->findBySql("select id from (
                                        SELECT id, @pv := nextOrderStatusId as 'nextOrderStatusId', OrderStatus.order as ord
                                        FROM OrderStatus JOIN (SELECT @pv) tmp
                                        WHERE id = @pv OR id = @ev or id = ?
                                        UNION
                                        SELECT id, @ev := errOrderStatusId as 'errOrderStatusId', OrderStatus.order as ord
                                        FROM OrderStatus JOIN (SELECT @ev) tmp
                                        WHERE id = @pv OR id = @ev or id = ? order by ord) derivati", array($id,$id));
    }

    public function findOrderStatus($status){
        $os = false;
        if (is_numeric($status)) {
            $os = $this->findOne([$status]);
        } elseif (is_string($status)) {
            $os = $this->findOneBy(['code' => $status]);
        }
        if (!$os) throw new \Exception('Can\'t find the requested order status');
        return $os;
    }

}