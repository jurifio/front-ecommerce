<?php

namespace bamboo\domain\entities;

use bamboo\core\auth\rbac\CRbacManager;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CUser
 * @package bamboo\core\auth
 *
 * @copyright (C) BambooShoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @author Emanuele Serini <emanuele@bambooshoot.agency>, November 2014
 * @property CUserDetails $userDetails
 * @property CLang $lang
 * @property CObjectCollection $userAddress
 * @property CFoison $foison
 */
class CUser extends AEntity
{
	protected $entityTable = 'User';
	protected $primaryKeys = ['id'];
    protected $fields = ['id'=> 0];
	/**
	 * @var CRbacManager $rbac
	 */
	protected $rbac;

	/**
	 * On object construction defaults to guest user. Properties will be
	 * overwritten by setUserData if the user is registered.
	 */
	public function __construct($entityManager = null,$fields = [], array $primaryKeys = ['id'], CRbacManager $rbac = null)
	{
		if($rbac != null){
			$this->rbac = $rbac;
		}
		parent::__construct($entityManager,$fields);
	}

	public function setRbac(CRbacManager $rbac)
	{
		$this->rbac = $rbac;
	}

	/**
	 * @param array $userData
	 */
	public function setUserData(array $userData)
	{
		foreach ($userData as $property => $value) {
			$this->$property = $value;
		}
	}

	/**
	 * @param $roleId (id, title or path)
	 *
	 * @return bool
	 */
	public function hasRole($roleId)
	{
		return $this->rbac->userHasRole($roleId, $this->id);
	}

	/**
	 * @param $permission (id, title or path)
	 *
	 * @return bool
	 */
	public function hasPermission($permission)
	{
		return $this->rbac->userHasPerm($permission, $this->id);
	}

    /**
     * @param $permissions
     * @return bool
     */
	public function hasPermissions($permissions)
    {
        $permission = preg_split('#&&|\|\|#u',$permissions,2);
        switch (count($permission)) {
            case '0': return true;
            case '1': return $this->hasPermission($permission[0]);
            default: {
                switch (trim(substr($permissions,strlen($permission[0]),2))) {
                    case '&&': return trim($this->hasPermission($permission[0])) && $this->hasPermissions($permission[1]);
                    case '||': return trim($this->hasPermission($permission[0])) || $this->hasPermissions($permission[1]);
                    default: return $this->hasPermission($permissions);
                }

            }
        }
    }

	/**
	 * @return mixed
	 */
	public function getId()
	{
		return $this->fields['id'];
	}

	/**
	 * @return mixed
	 */
	public function getName()
	{
		return $this->userDetails->name;
	}

	/**
	 * @return mixed
	 */
	public function getSurname()
	{
		return $this->userDetails->surname;
	}

	/**
	 * @return string
	 */
	public function getFullName()
	{
		return $this->getName()." ".$this->getSurname();
	}

	/**
	 * @return mixed
	 */
	public function getEmail()
	{
		return $this->fields['email'];
	}

	/**
	 * @return bool
	 */
	public function isActivated()
	{
		return (bool) $this->isActive;
	}

	/**
	 * @return bool
	 */
	public function isEmailChanged()
	{
		return (bool) $this->isEmailChanged;
	}

	/**
	 * @return mixed
	 */
	public function getAuthToken()
	{
		if($token = $this->token->findOneByKey('tokenType','A')) {
			return $token->token;
		}
		else {
			return false;
		}
	}

    /**
     * restituisce gli shop autorizzati per l'utente
     * @return CObjectCollection
     */
    public function getAuthorizedShops() {
        return \Monkey::app()->repoFactory->create('Shop')->getAutorizedShopsForUser($this);
    }

	/**
	 * @return mixed
	 */
	public function getRegistrationToken()
	{
		if($token = $this->token->findOneByKey('tokenType','D')) {
			return $token->token;
		}
		else {
			return false;
		}
	}


	/**
	 * @return mixed
	 */
	public function getGender()
	{
		if (!is_null($this->userDetails)) {
			return $this->userDetails->gender;
		}
		return null;
	}

    /**
     * @param int $shopId
     * @return bool
     */
	public function hasShop(int $shopId, $trueIfAllShops = true){
	    if ($trueIfAllShops && $this->hasPermission('allShops')) return true;
        foreach ($this->shop as $v) {
            if ($v->id == $shopId) return true;
        }
        return false;
    }

    /**
     * @param CUser|null $user
     * @return bool
     */
    public function isSystemUser() {
        /** @var CUser $uR */
        $uR = \Monkey::app()->repoFactory->create('User');
        $sysUser = $uR->getSystemUser();
        return ($sysUser->id === $this->id) ? true : false;
    }

    public function getProfileImage(){
        if (!is_null($this->foison) && !is_null($this->foison->profileImageUrl)) return $this->foison->profileImageUrl;

        return '/assets/img/profiles/default.jpg';
    }

    public function inNewsletter(){
       if(\Monkey::app()->repoFactory->create('NewsletterUser')->findOneBy(['email'=>$this->email])) return true;

       return false;
    }
}