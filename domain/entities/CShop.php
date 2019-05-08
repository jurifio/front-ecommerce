<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CShop
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
 * @property CAddressBook $billingAddressBook
 * @property CObjectCollection $shippingAddressBook
 * @property CObjectCollection $user
 * @property CObjectCollection $sectional
 */
class CShop extends AEntity
{
    protected $entityTable = 'Shop';

    /**
     * @return string the url to the shop logo
     */
    public function getShopLogo()
    {
        return "/shopLogos/" . $this->name . ".png";
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return json_decode(isset($this->fields['config']) ? $this->fields['config'] : null, true);
    }

    /**
     * @param array|null $config
     */
    public function setConfig($config = null)
    {
        if ($config === null) {
            $this->fields['config'] = null;
        } elseif (is_array($config)) {
            $this->fields['config'] = json_encode($config);
        } elseif (is_string($config)) {
            $this->fields['config'] = $config;
        }
    }

    /**
     * @param string $string
     */
    public function unserialize($string)
    {
        $r = unserialize($string);
        $this->ownersFields = $r['ownersFields'];
        foreach ($r['fields'] as $key => $val) {
            $this->__set($key, $val);
        }
    }

    /**
     * @return int
     */
    public function getActiveProductCount()
    {
        return $this->getEntityRepo()->getActiveProductCountForShop($this->id);
    }

    /**
     * @param null $from
     * @param null $to
     * @return array
     */
    public function getDailyActiveProductStatistics($from = null, $to = null)
    {
        return $this->getEntityRepo()->getDailyActiveProductStatistics($from,$to,$this->id);
    }

    /**
     * @param null $from
     * @param null $to
     * @return mixed
     */
    public function getDailyOrderFriendStatistics($from = null, $to = null) {
        return $this->getEntityRepo()->getDailyOrderFriendStatistics($from,$to,$this->id);
    }
}