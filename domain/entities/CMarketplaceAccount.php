<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CMarketplaceAccount
 * @package bamboo\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 08/07/2016
 * @since 1.0
 * @property CMarketplace $marketplace
 */
class CMarketplaceAccount extends AEntity
{
    protected $entityTable = 'MarketplaceAccount';
	protected $primaryKeys = ['id','marketplaceId'];
	
	/**
	 * @return mixed
	 */
	public function getConfig() {
		return json_decode(isset($this->fields['config']) ? $this->fields['config'] : null,true);
	}

	/**
	 * @param array|null $config
	 */
	public function setConfig($config = null) {
		if($config === null) {
			$this->fields['config'] = null;
		}elseif(is_array($config)) {
			$this->fields['config'] = json_encode($config);
		} elseif(is_string($config)) {
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
		foreach($r['fields'] as $key => $val){
			$this->__set($key,$val);
		}
	}

	public function getCampaignName()
    {
	    return $this->marketplace->name.' - '.$this->name;
    }

    public function getCampaignCode()
    {
	    return "MarketplaceAccount".$this->printId();
    }
}