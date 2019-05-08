<?php


namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;

/**
 * Class COrderLineFriendPaymentStatusRepo
 * @package bamboo\domain\repositories
 */
class COrderLineFriendPaymentStatusRepo extends ARepo
{
    public function getColor($status) {
        if (is_numeric($status)){
            $id = $status;
        } else {
            $id = $this->findOneBy(['name' => $status]);
        }
        switch($id) {
            case 1:
                $color = '#FF0000';
                break;
            case 4:
                $color = '#00FF00';
                break;
            default:
                $color = '#FFD700';
        }
        return $color;
    }

    public function findAllToArray() {
        $options = $this->findAll()->toArray();
        foreach ($options as $k => $v) {
            $options[$k] = $v->toArray();
        }
        return $options;
    }
}
