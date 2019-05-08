<?php


namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\exceptions\BambooException;

/**
 * Class CConfigurationRepo
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
class CConfigurationRepo extends ARepo
{
    /**
     * @param $name
     * @return array|mixed|null
     */
    public function fetchConfigurationValue($name) {
        $res = $this->findBy(['name'=>$name]);
        if($res->count() == 0) return null;
        if($res->count() == 1) return $res->getFirst()->value;
        $conf = [];
        foreach ($res as $re) {
            $conf[] = $re->value;
        }
        return $conf;
    }

    /**
     * @param $context
     * @param $name
     * @return array|mixed|null
     */
    public function getConfiguration($context, $name) {
        $res = $this->findBy(['context' => $context, 'name'=>$name]);
        if(0 == $res->count()) return null;
        if(1 < $res->count()) throw new BambooException('Sono presenti più righe con la stess configurazione (e questo è male)');
        return $res->getFirst()->value;
    }
}
