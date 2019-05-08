<?php


namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;

/**
 * Class COrderLineStatusRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class COrderLineStatusRepo extends ARepo
{
    /**
     * @deprecated
     * @param string $start
     * @return CObjectCollection
     */
    public function listByPossibleStatuses($start = 'CRT')
    {
        $id = $this->app->dbAdapter->select('OrderLineStatus',["code"=>$start])->fetch()['id'];
        return $this->em()->findBySql("select distinct id from (
                          SELECT o1.id, @pv := nextOrderLineStatusId as 'nextOrderLineStatusId', o1.`phase` as ord
                          FROM OrderLineStatus o1 JOIN (SELECT @pv) tmp1
                          WHERE o1.id = @pv or o1.id = ?
                          UNION
                          SELECT o2.id, @ev := errOrderLineStatusId as 'errOrderLineStatusId', o2.`phase` as ord
                          FROM OrderLineStatus o2 JOIN (SELECT @ev) tmp2
                          WHERE o2.id = @ev or o2.id = ? order by ord ) derivati", array($id,$id));
    }

    /**
     * @param $orderLineCode
     * @param $shop
     * @return \bamboo\core\db\pandaorm\entities\IEntity|false
     */
    public function getLastStatusSuitableByFriend($orderLineCode, $shop) {
        $olsR = \Monkey::app()->repoFactory->create('OrderLineStatus');
        $olC = $olsR->findBy(['isManageableForFriend' => 1]);
        $manStatuses = [];
        foreach($olC as $ol) {
            $manStatuses[] = "'" . $ol->code . "'";
        }
        $manStatuses = implode(',', $manStatuses);

        if (!is_object($shop)) $shop = \Monkey::app()->repoFactory->create('Shop')->findOne([$shop]);
        $userIds = [];
        $users = $shop->user;
        foreach($users as $u) {
            $userIds[] = $u->id;
        }
        $userIds = implode(',', $userIds);
        $query =
"SELECT id, eventValue FROM Log 
WHERE entityName = 'OrderLine' AND `eventName` IN (" . $manStatuses . ")
AND stringId = '" . $orderLineCode . "'
ORDER BY time desc LIMIT 1";
        $res = \Monkey::app()->dbAdapter->query($query, [])->fetch();
        //if ($res) return \Monkey::app()->repoFactory->create('Log')->findOneBy(['id' => $res['id']]);
        if ($res) {
            $log = \Monkey::app()->repoFactory->create('Log')->findOneBy(['id' => $res['id']]);
            return \Monkey::app()->repoFactory->create('OrderLineStatus')->findOneBy(['code' => $log->eventValue]);
        }
        return false;
    }




    /* DA CANCELLARE PROBABILMENTE DA QUI IN POI
    public function listUsableStatusesForFriend(){
        $statuses = $this->listManageable('ORD_FRND_SENT');
        return $statuses;
    }

    public function listUsableStatuses($start = 'ORD_WAIT'){
        $statuses = $this->listManageable($start);
        return $statuses;
    }


    public function listManageable($start = 'CRT', $startField = 'code'){
        if ('phase' !== $startField) $phase = \Monkey::app()->dbAdapter->select(
            'OrderLineStatus',
            [$startField => $start]
        )->fetch('phase')[0];
        else $phase = $start;
        $query = "SELECT * FROM OrderLineStatus WHERE phase >= ? AND isManageable = ? ORDER BY phase";
        $ret = \Monkey::app()->dbAdapter->query($query, [$phase, 1], null, 'phase')->fetchAll();
        return $ret;
    }
    */
}