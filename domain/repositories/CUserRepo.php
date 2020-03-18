<?php

namespace bamboo\domain\repositories;

use bamboo\core\exceptions\BambooDBALException;
use bamboo\domain\entities\CUserDetails;
use bamboo\domain\entities\CUserEmail;
use bamboo\ecommerce\business\CForm;
use bamboo\domain\entities\CUser;
use bamboo\core\base\CToken;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\db\pandaorm\repositories\IUserRepo;
use bamboo\core\io\CValidator;

/**
 * Class CUserRepo
 * @package bamboo\app\domain\repositories
 */
class CUserRepo extends ARepo implements IUserRepo
{
    /**
     * @param $username
     * @param bool $byEmail
     * @return mixed
     */
    public function fetchUser($username, $byEmail = false)
    {
        if ($byEmail) {
            $query = 'SELECT id,username,`password` FROM `User` WHERE username=:username';
            $bind = ['username' => $username];
        } else {
            $query = 'SELECT id,username,`password` FROM `User` WHERE email=:email';
            $bind = ['email' => $username];
        }
        $userData = $this->em()->query($query, $bind)->fetch();

        return $userData;
    }

    /**
     * @param $userId
     * @return mixed
     */
    public function fetchRegistrationToken($userId)
    {
        $query = 'SELECT token FROM Token WHERE userId=:userId AND tokenType=:tokenType';
        $bind = ['userId' => $userId, 'tokenType' => 'D'];
        $token = $this->em()->query($query, $bind)->fetch();

        return $token['token'];
    }

    /**
     * @param $userId
     * @return mixed
     */
    public function fetchAuthToken($userId)
    {
        $query = 'SELECT token FROM Token WHERE userId=:userId AND tokenType=:tokenType';
        $bind = ['userId' => $userId, 'tokenType' => 'A'];
        $token = $this->em()->query($query, $bind)->fetch();

        return $token['token'];
    }

    /**
     * @param $userId
     * @return int
     */
    public function deleteAuthTokensForUser($userId)
    {
        $rows = $this->app->dbAdapter->delete("Token", ['userId' => $userId, 'tokenType' => 'A']);

        return $rows;
    }

    /**
     * @param $userId
     * @param $token
     * @return mixed
     */
    public function persistAuthToken($userId, $token)
    {
        $query = 'INSERT INTO Token (userId,token,tokenType) VALUES (:userId,:token,:tokenType)';
        $bind = ['userId' => $userId, 'token' => $token, 'tokenType' => 'A'];
        $rows = $this->em()->query($query, $bind)->countAffectedRows();

        return $rows;
    }

    /**
     * @param $userId
     * @return int
     */
    public function activate($userId)
    {
        try {
            $user = \Monkey::app()->repoFactory->create("User")->findOneBy(['id' => $userId]);
            $user->isActive = 1;
            $user->update();
        } catch (\Throwable $e) {
            $this->app->router->response()->raiseUnauthorized();

            return false;
        }

        return true;
    }

    /**
     * @return null|object
     */
    public function canActivate()
    {
        $token = $this->app->router->getMatchedRoute()->getComputedFilter('token');
        $user = $this->em()->query("SELECT DISTINCT u.id
									FROM `User` u, `Token` ut
									WHERE u.id = ut.userId AND
									u.isActive = 0 AND
									ut.token = ? AND
									ut.tokenType = ?",
            [urldecode($token), 'D'])->fetchAll();
        if (count($user) != 1) return null;
        else return $user[0]['id'];
    }

    /**
     * @param CForm $form
     * @return CForm
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaInvalidArgumentException
     */
    public function updatePersonalData(CForm $form)
    {
        $entity = $this->em()->getEmptyEntity();

        $form->deleteField('usr');
        $form->deleteField('pwd');
        $form->deleteField('loc');
        $form->deleteField('xcontroller');
        $form->deleteField('widgetAddress');

        foreach ($form->getValues() as $key => $value) {
            $form->setValue($key, urldecode($value));
        }

        foreach ($form->getValues() as $key => $value) {
            $entity->{explode('.', $key)[1]} = $value;
        }

        /**
         * Form validation
         */
        $v = new CValidator();

        $past = new \DateTime();
        $past->sub(new \DateInterval('P100Y'));
        $future = new \DateTime();

        $validationErrors = [];
        $validationErrors['name'] = $v->validate($entity->name, 'name');
        $validationErrors['surname'] = $v->validate($entity->surname, 'name');
        $validationErrors['email'] = $v->validate($entity->email, 'email');
        $validationErrors['emailc'] = $v->validate($entity->emailc, 'email');
        $validationErrors['birthDate'] = $v->validate($entity->birthDate, 'date', false, ['past' => $past, 'future' => $future]);
        $validationErrors['gender'] = $v->validate($entity->gender, 'range', false, ['M', 'F']);
        $validationErrors['emailc'] = $v->validate($entity->email, 'match', false, [$entity->emailc]);

        $errors = [];
        foreach ($validationErrors as $key => $errorValue) {
            if ($errorValue != 'ok') {
                $errors[$key] = $errorValue;
            }
        }
        $form->setErrors($errors);

        try {

            \Monkey::app()->repoFactory->beginTransaction();
            $user = $this->em()->findOne(["id" => $form->getValue('user.id')]);

            if ($this->em()->findCountBySql("SELECT COUNT(id) FROM `User` WHERE email = ? AND email <> ?", [$form->getValue('user.email'), $user->email])) {
                $form->setErrors(["database" => CForm::FORM_EMAIL_EXISTS_IN_DATABASE]);

                return $form;
            }

            if ($user->email != $form->getValue('user.email')) {
                $form->setOutcome("mailchanged", 1);
                $user->isEmailChanged = 1;
                $user->isActive = 0;
            }

            $repoE = \Monkey::app()->repoFactory->create('UserEmail');
            $userEmail = $repoE->findOneBy(["address" => $user->email]);

            $user->email = $form->getValue('user.email');
            $user->update();

            $userEmail->address = $form->getValue('user.email');
            $userEmail->isPrimary = true;
            $userEmail->update();

            $repoD = \Monkey::app()->repoFactory->create('UserDetails');
            $userDetails = $repoD->em()->findOneBy(["userId" => $form->getValue('user.id')]);
            $userDetails->name = $form->getValue('userDetails.name');
            $userDetails->surname = $form->getValue('userDetails.surname');
            $userDetails->birthDate = $form->getValue('userDetails.birthDate');
            $userDetails->gender = $form->getValue('userDetails.gender');
            $userDetails->update();

            \Monkey::app()->repoFactory->commit();

            return $form;

        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            $form->setErrors(["database" => CForm::FORM_DATABASE_FAIL]);

            return $form;
        }
    }

    /**
     * @param array $data
     * @return \bamboo\core\db\pandaorm\entities\IEntity
     * @throws BambooDBALException
     */
    public function insertNewUserFromFacebook(array $data)
    {
        \Monkey::app()->repoFactory->beginTransaction();
        try {
            $newUser = $this->em()->getEmptyEntity();

            try {
                $langString = explode('_', $data['locale'])[0];
                $lang = \Monkey::app()->repoFactory->create('Lang')->findOneBy(['isActive' => 1, 'lang' => $langString]);
            } catch (\Throwable $e) {
                $lang = null;
            }

            $newUser->email = $data['email'];
            $newUser->registrationEntryPoint = 'Facebook';
            $newUser->isActive = 1;
            $newUser->isDeleted = 0;
            $newUser->langId = is_null($lang) ? 1 : $lang->id;
            $newUser->id = $newUser->insert();
            $newUserDetails = \Monkey::app()->repoFactory->create('UserDetails')->getEmptyEntity();
            $newUserDetails->userId = $newUser->id;
            $newUserDetails->name = $data['first_name'];
            $newUserDetails->surname = $data['last_name'];
            if (isset($data['birthday'])) {
                $newUserDetails->birthDate = $data['birthday']->format('Y-m-d H:i:s');
            }

            $gend = null;
            $gend = isset($data['gender']) && $data['gender'] == 'male' ? 'M' : 'F';
            $newUserDetails->gender = $gend;
            $newUserDetails->insert();

            $userEmail = \Monkey::app()->repoFactory->create('UserEmail')->getEmptyEntity();
            $userEmail->userId = $newUser->id;
            $userEmail->address = $newUser->email;
            $userEmail->isPrimary = 1;
            $userEmail->insert();

            \Monkey::app()->repoFactory->commit();
            /** @var CNewsletterUserRepo $nuRepo */
            $nuRepo = \Monkey::app()->repoFactory->create('NewsletterUser');
            $nuRepo->insertNewEmail($newUser->email, $newUser->id, $newUser->langId, $data['first_name'], $data['last_name'], $gend);
            return $newUser;
        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            throw new BambooDBALException("could not add user", [], [], $e);
        }
    }



    /**
     * @param CForm $form
     * @return bool|CForm
     */
    public function insertNewUserFromSite(CForm $form)
    {
        $entity = $this->em()->getEmptyEntity();

        $form->deleteField('usr');
        $form->deleteField('pwd');

        foreach ($form->getValues() as $key => $value) {
            $entity->{explode('_', $key)[1]} = $value;
        }

        /**
         * Form validation
         */
        $v = new CValidator();

        $past = new \DateTime();
        $past->sub(new \DateInterval('P100Y'));

        $future = new \DateTime();

        $validationErrors = [];
        $validationErrors['name'] = $v->validate($form->getValue('userDetails_name'), 'name');
        $validationErrors['surname'] = $v->validate($form->getValue('userDetails_surname'), 'name');
        $validationErrors['email'] = $v->validate($form->getValue('user_email'), 'email');
        $validationErrors['emailc'] = $v->validate($form->getValue('user_emailc'), 'email');
        $validationErrors['birthDate'] = $v->validate($form->getValue('userDetails_birthDate'), 'date', false, ['past' => $past, 'future' => $future]);
        $validationErrors['password'] = $v->validate($form->getValue('user_password'), 'password', false, ['maxlen' => 20, 'minlen' => 8, 'regexp' => '/^(?=.*\d)(?=.*[\w]).{8,20}$/u']);
        $validationErrors['gender'] = $v->validate($form->getValue('userDetails_gender'), 'range', false, ['M', 'F']);
        $validationErrors['emailc'] = $v->validate($form->getValue('user_emailc'), 'match', false, [$form->getValue('user_email')]);

        $errors = [];
        foreach ($validationErrors as $key => $errorValue) {
            if ($errorValue != 'ok') {
                $errors[$key] = $errorValue;
            }
        }
        $form->setErrors($errors);

        if ($form->hasErrors()) {
            $form->setValue('user_password', '');

            return $form;
        }

        if ($this->em()->findCountBySql("SELECT COUNT(id) FROM `User` WHERE email = ?", [$form->getValue('user_email')])) {
            $form->setErrors(["database" => CForm::FORM_EMAIL_EXISTS_IN_DATABASE]);
            $form->setValue('user_password', '');

            return $form;
        }

        try {

            \Monkey::app()->repoFactory->beginTransaction();
            $user = $this->em()->getEmptyEntity();
            $user->email = $form->getValue('user_email');
            $user->password = password_hash($form->getValue('user_password'), PASSWORD_BCRYPT);

            $user->isActive = 0;
            $user->isDeleted = false;
            $user->isEmailChanged = false;
            $user->registrationEntryPoint = 'website';
            $user->langId = $this->app->getLang()->getId();
            $user->id = $this->em()->insert($user);

            $userPreferences = \Monkey::app()->repoFactory->create('UserPreferences')->getEmptyEntity();
            $userPreferences->userId = $user->id;
            $userPreferences->langId = $this->app->getLang()->getId();

            $repoD = \Monkey::app()->repoFactory->create('UserDetails');
            $userDetails = $repoD->getEmptyEntity();
            $userDetails->userId = $user->id;
            $userDetails->name = $form->getValue('userDetails_name');
            $userDetails->surname = $form->getValue('userDetails_surname');
            $userDetails->birthDate = $form->getValue('userDetails_birthDate');
            $userDetails->gender = $form->getValue('userDetails_gender');
            $repoD->insert($userDetails);

            $repoE = \Monkey::app()->repoFactory->create('UserEmail');
            $userEmail = $repoE->getEmptyEntity();
            $userEmail->userId = $user->id;
            $userEmail->address = $user->email;
            $userEmail->isPrimary = true;
            $repoE->insert($userEmail);

            $this->persistRegistrationToken($user->id, (new CToken(64))->getToken(), time() + $this->app->cfg()->fetch('miscellaneous', 'confirmExpiration'));

            \Monkey::app()->repoFactory->commit();
            $this->app->authManager->register($user->id);

            \Monkey::app()->repoFactory->create('NewsletterUser')
                ->insertNewEmail($user->email, $user->id, null, $form->getValue('userDetails_name'), $form->getValue('userDetails_surname'), $form->getValue('userDetails_gender'));
            return $form;

        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            $form->setValue('user_password', '');
            $form->setErrors(["database" => CForm::FORM_DATABASE_FAIL]);

            return $form;
        }
    }

    /**
     * @param $userId
     * @param $token
     * @return mixed
     */
    public function persistRegistrationToken($userId, $token, $exipirationDate = null)
    {
        $tokenEntity = \Monkey::app()->repoFactory->create('Token')->getEmptyEntity();
        $tokenEntity->userId = $userId;
        $tokenEntity->token = $token;
        $tokenEntity->tokenType = 'D';
        $tokenEntity->expirationDate = $exipirationDate;
        $tokenEntity->insert();

        return true;
    }

    /**
     * @param array $parameters
     * @param int $billing
     * @param int $default
     * @return int|bool
     */
    public function insertNewAddressFromCheckout(array $parameters, $billing = 0, $default = 0)
    {
        $repoA = \Monkey::app()->repoFactory->create('UserAddress');

        try {
            $userAddress = $repoA->getEmptyEntity();
            $userAddress->userId = $this->app->getUser()->getId();
            $userAddress->isBilling = $billing;
            $userAddress->isDefault = $default;
            $userAddress->name = trim($parameters['name']);
            $userAddress->surname = trim($parameters['surname']);
            $userAddress->company = trim($parameters['company']);
            $userAddress->address = trim($parameters['address'] . " " . $parameters['address2']);
            $userAddress->city = trim($parameters['city']);
            $userAddress->province = trim($parameters['province']);
            $userAddress->postcode = trim($parameters['postcode']);
            $userAddress->phone = trim($parameters['phone']);
            $userAddress->extra = trim($parameters['extra']);
            $userAddress->countryId = $parameters['country'];

            $id = $repoA->insert($userAddress);
        } catch (\Throwable $e) {
            return false;
        }

        return $id;
    }

    /**
     * @param $id
     * @return bool
     * @throws \bamboo\core\exceptions\RedPandaORMException
     * @throws \bamboo\core\exceptions\RedPandaORMInvalidEntityException
     */
    public function deleteUserTotalCascade($id, $safety = false)
    {
        if (!$safety) return false;
        $user = $this->findOne(['id' => $id]);
        if ($user == null) return true;
        $this->app->dbAdapter->delete('Token', ["userId" => $user->id]);
        //$this->app->dbAdapter->delete('UserPrivacy', ["userId" => $user->id]);
        //$this->app->dbAdapter->delete('UserCompany', ["userId" => $user->id]);
        $this->app->dbAdapter->delete('ActivityLog', ["userId" => $user->id]);
        $this->app->dbAdapter->delete('UserEmail', ["userId" => $user->id]);
        $this->app->dbAdapter->delete('UserDetails', ["userId" => $user->id]);
        //$this->app->dbAdapter->delete('UserRegistrationToken', ["userId" => $user->id]);
        //$this->app->dbAdapter->delete('UserToken', ["userId" => $user->id]);

        $this->app->dbAdapter->delete('UserHasRbacRole', ["userId" => $user->id]);
        $this->app->dbAdapter->delete('UserHasShop', ["userId" => $user->id]);

        $this->app->dbAdapter->query("DELETE usho
                                      FROM UserSessionHasCart usho, 
                                           UserSession us 
                                      WHERE usho.userSessionId = us.id AND us.userId = ?",
            [$user->id]);
        $this->app->dbAdapter->delete("UserSession", ["userId" => $user->id]);

        $orders = \Monkey::app()->repoFactory->create('Order')->findBy(['userId' => $user->id]);
        foreach ($orders as $order) {
            foreach ($order->orderHistory as $orderHistry) {
                $orderHistry->delete();
            }
            foreach ($order->orderLine as $orderLine) {
                $orderLine->delete();
            }
            $order->delete();
        }

        foreach ($user->userOAuth as $userOAuth) {
            $userOAuth->delete();
        }

        $carts = \Monkey::app()->repoFactory->create('Cart')->findBy(['userId' => $user->id]);
        foreach ($carts as $cart) {
            foreach ($cart->cartLine as $cartLine) {
                $cartLine->delete();
            }
            $this->app->dbAdapter->delete("CartHistory", ["cartId" => $cart->id]);
            $this->app->dbAdapter->delete("UserSessionHasCart", ["cartId" => $cart->id]);
            $cart->delete();
        }

        $this->app->dbAdapter->delete("Coupon", ["userId" => $user->id]);
        $this->app->dbAdapter->delete('UserAddress', ["userId" => $user->id]);
        $this->app->dbAdapter->delete('NewsletterUser', ['userId' => $user->id]);

        $this->em()->delete($user);

        return true;
    }

    /**
     * @param $id
     */
    public function safeDeleteUser($id)
    {
        //todo impement a method to delete an user along with email, sessions details but keeping the record itself and the orders
    }

    /**
     * @param CForm $form
     * @return CForm
     */
    public function loginFakeAuth(CForm $form)
    {
        if ($this->app->getUser()->getId() == 0) {
            $form->setErrors(["badlogin" => true]);
        }

        return $form;
    }

    /**
     *
     */
    public function recoverPassword(CForm $form)
    {
        $v = new CValidator();

        $validationErrors = [];
        $validationErrors[''] = $v->validate($form->getValue('user_email'), 'email');
        //$user = $this->em()->findBySql("SELECT id from User WHERE email = ?",[$form->getValue('user_email')]);
        $userId = $this->em()->query("SELECT id FROM User WHERE email = ?", [$form->getValue('user_email')])->fetch();
        $user = $this->em()->findOne(['id' => $userId['id']]);
        if ($user != false && $user instanceof CUser) {
            $token = new CToken(32);
            $this->persistRegistrationToken($user->id, $token->getToken(), (time() + $this->app->cfg()->fetch('miscellaneous', 'confirmExpiration')));
            $to = [];
            $to[] = $user->email;


            /*$this->app->mailer->prepare('recoverpassword', 'no-reply', $to, [], [], ['name' => $user->userDetails->name, 'token' => urlencode($user->getRegistrationToken())]);
            $this->app->mailer->send();*/

            /** @var CEmailRepo $emailRepo */
            $emailRepo = \Monkey::app()->repoFactory->create('Email');
            $emailRepo->newPackagedTemplateMail('recoverpassword', 'no-reply@pickyshop.com', $to, [], [], ['name' => $user->userDetails->name, 'token' => urlencode($user->getRegistrationToken())],'MailGun',null);
        }

        return $form;
    }

    /**
     * @param CForm $form
     * @return CForm
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaInvalidArgumentException
     */
    public function changePassword(CForm $form)
    {
        $token = $form->getValue('userRegistrationToken_token');
        $userId = $this->em()->query("SELECT userId FROM Token WHERE token = ? AND tokenType = ?", [$token, 'D'])->fetch()['userId'];
        $errors = [];
        if ($userId == false) {
            $errors['userRegistrationToken_token'] = CForm::FORM_TOKEN_ERROR;
        }
        /**
         * Form validation
         */
        $v = new CValidator();

        $validationErrors = [];
        $validationErrors['user_password'] = $v->validate($form->getValue('user_password'), 'password', false, ['maxlen' => 20, 'minlen' => 8, 'regexp' => '/^(?=.*\d)(?=.*[\w]).{8,20}$/u']);
        $validationErrors['user_passwordc'] = $v->validate($form->getValue('user_passwordc'), 'password', false, ['maxlen' => 20, 'minlen' => 8, 'regexp' => '/^(?=.*\d)(?=.*[\w]).{8,20}$/u']);
        $validationErrors['user_passwordc'] = $v->validate($form->getValue('user_password'), 'match', false, [$form->getValue('user_passwordc')]);
        foreach ($validationErrors as $key => $errorValue) {
            if ($errorValue != 'ok') {
                $errors[$key] = $errorValue;
            }
        }
        $form->setErrors($errors);
        if ($form->hasErrors()) {
            $form->setValue('user_password', '');
        } else {
            $password = password_hash($form->getValue('user_password'), PASSWORD_BCRYPT);
            try {

                $user = \Monkey::app()->repoFactory->create("User")->findOneBy(['id' => $userId]);
                $user->password = $password;
                $change = $user->update();

            } catch (\Throwable $e) {
                $change = 0;
                $this->app->router->response()->raiseUnauthorized();
            }
            //$change = $this->app->dbAdapter->update('User', ['password'=>$password], ['id'=>$userId]);
            if ($change != 1) {
                $errors['database'] = CForm::FORM_DATABASE_FAIL;
                $form->setErrors($errors);
            }
        }

        return $form;
    }

    /**
     *
     */
    public function readToken($dataAddress)
    {
        $form = new CForm($dataAddress, []);
        $form->setValue('userRegistrationToken_token', $this->app->router->getMatchedRoute()->getComputedFilter('token'));

        return $form;
    }

    /**
     * @param $formDataAddress
     * @return CForm
     */
    public function extractPersonalData($formDataAddress)
    {
        $userId = $this->app->getUser()->getId();
        $repo = \Monkey::app()->repoFactory->create("User");
        $user = $repo->findOne(["id" => $userId]);
        $formFields = $this->createForm($user->fullTreeToArray(), mb_strtolower($user->getEntityTable()));

        return new CForm($formDataAddress, $formFields);
    }

    /**
     * @param $data
     * @param $tableName
     * @return array
     */
    private function createForm($data, $tableName)
    {
        $formFields = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $key2 => $value2) {
                    if (is_array($value2)) {
                        foreach ($value2 as $key3 => $value3) {
                            $formFields[$key . '.' . $key2 . '.' . $key3] = $value3;
                        }
                    } else {
                        $formFields[$key . '.' . $key2] = $value2;
                    }
                }
            } else {
                if (mb_stristr('password', $key)) continue;
                $formFields[$tableName . '.' . $key] = $value;
            }
        }

        return $formFields;
    }

    /**
     * @return \bamboo\core\db\pandaorm\entities\AEntity|null
     */
    public function getSystemUser()
    {
        return $this->findOne([
            \Monkey::app()->repoFactory->create('Configuration')->fetchConfigurationValue('system-user-id')
        ]);
    }

    /**
     * @param string $email
     * @param string $name
     * @param string $surname
     * @param $birthDate
     * @param $gender
     * @param $phone
     * @param $registrationEntryPoint
     * @param null $fiscalCode
     * @param null $note
     * @param int $isActive
     * @param bool $newsletter
     * @return array
     * @throws \Exception
     * @throws \bamboo\core\exceptions\BambooException
     */
    public function insertUserFromData(string $email, string $name, string $surname, $birthDate, $gender, $phone, $registrationEntryPoint, $fiscalCode = null, $note = null, $isActive = 1, bool $newsletter = true)
    {
        /** @var CUser $user */
        $user = \Monkey::app()->repoFactory->create('User')->getEmptyEntity();
        $user->email = $email;
        $newPw = bin2hex(random_bytes(6));
        $user->password = password_hash($newPw, PASSWORD_BCRYPT);
        $user->registrationEntryPoint = $registrationEntryPoint;
        $user->isActive = $isActive;
        $user->isDeleted = 0;
        $user->langId = 1;
        $user->id = $user->insert();

        /** @var CUserDetails $userD */
        $userD = \Monkey::app()->repoFactory->create('UserDetails')->getEmptyEntity();
        $userD->userId = $user->id;
        $userD->name = $name;
        $userD->surname = $surname;
        $userD->birthDate = $birthDate;
        $userD->gender = $gender;
        $userD->phone = $phone;
        $userD->fiscalCode = $fiscalCode;
        $userD->note = $note;
        $userD->insert();

        /** @var CUserEmail $userEmail */
        $userEmail = \Monkey::app()->repoFactory->create('UserEmail')->getEmptyEntity();
        $userEmail->userId = $user->id;
        $userEmail->address = $user->email;
        $userEmail->isPrimary = true;
        $userEmail->insert();

        \Monkey::app()->repoFactory->create('User')->persistRegistrationToken($user->id, (new CToken(64))->getToken(), time() + $this->app->cfg()->fetch('miscellaneous', 'confirmExpiration'));

        if($newsletter) {
            if ($this->app->router->request()->getRequestData('user_newsletter')) {
                \Monkey::app()->repoFactory->create('NewsletterUser')->insertNewEmail($user->email, $user->id, $user->langId);
            }
        }

        $data = [];
        $data['user'] = $user;
        $data['pw'] = $newPw;
        return $data;
    }
}