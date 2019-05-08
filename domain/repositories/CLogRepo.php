<?php
namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;

/**
 * Class CLogRepo
 * @package bamboo\domain\repositories
 */
class CLogRepo extends ARepo
{
    /**
     * @param array $params
     * @return \bamboo\core\db\pandaorm\entities\IEntity|null
     */
    public function getLastEntry(array $params) {
        $where = [];
        $conds = [];
        foreach($params as $k => $p) {
            $operator = (is_numeric($p)) ? '=' : 'LIKE';
            $where[] = $k . ' ' . $operator . ' ?';
            $conds[] = $p;
        }
        $where = implode(' AND ', $where);
        $sql = "SELECT max(id) as id FROM Log WHERE " . $where;
        $res = \Monkey::app()->dbAdapter->query($sql, $conds)->fetch();
        if ($res) return \Monkey::app()->repoFactory->create('Log')->findOneBy(['id' => $res['id']]);
        return false;
    }
}