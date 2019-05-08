<?php

namespace bamboo\controllers\widget;

use bamboo\core\router\ANodeController;
use bamboo\domain\entities\CNewsletterUser;
use bamboo\domain\repositories\CNewsletterUserRepo;
use bamboo\utils\time\STimeToolbox;


/**
 * Class CAddressFormController
 * @package bamboo\app\controllers
 */
class CUserUnsuscribeController extends ANodeController
{
    public function post()
    {

    }


    public function put()
    {
        $data = \Monkey::app()->router->request()->getRequestData();
        $email = $data['email'];

        /** @var CNewsletterUserRepo $newsletterUserRepo */
        $newsletterUserRepo = \Monkey::app()->repoFactory->create('NewsletterUser');

        /** @var CNewsletterUser $newsletterUserList */
        $newsletterUserList = $newsletterUserRepo->findOneBy(['email' => $email]);
        $now = STimeToolbox::DbFormattedDateTime();
        if (!is_null($newsletterUserList)) {
            $newsletterUserList->isActive = 0;
            $newsletterUserList->unsubscriptionDate = $now;
            $newsletterUserList->update();
            return $this->response;
        } else return false;
    }

    public function delete() {return $this->get();}
}