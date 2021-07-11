<?php

namespace bamboo\domain\repositories;

use bamboo\core\base\CObjectCollection;
use bamboo\core\exceptions\BambooDBALException;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\domain\entities\CEmail;
use bamboo\domain\entities\CEmailAddress;
use bamboo\domain\entities\CEmailExternalRecipient;
use bamboo\domain\entities\CEmailRecipient;
use bamboo\domain\entities\CExternalEmail;
use bamboo\domain\entities\CNewsletter;
use bamboo\domain\entities\CNewsletterGroup;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\repositories\CEmailRepo;


/**
 * Class CNewsletterRepo
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
class CNewsletterRepo extends ARepo
{
    use TMySQLTimestamp;

    /**
     * insert an email to the newsletter or reactivate it, connecting also it to the user
     * @param $email
     * @param null $userId
     * @param null $langId
     * @return int
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    /* public function insertNewEmail($email, $userId = null, $langId = null)
    {
        $newsl = $this->findOneBy(['email'=>$email]);

        if(!is_null($newsl)) {
            $newsl->isActive = 1;
            $newsl->userId = $newsl->userId ?? $userId;
            $newsl->update();
        } else {
            $newsl = $this->getEmptyEntity();
            $newsl->email = $email;
            $newsl->userId = $userId;
            $newsl->isActive = 1;
            $newsl->langId = $langId ?? $this->app->getLang()->getId();
            $newsl->id = $newsl->insert();
        }

        return $newsl->id;
    }*/

    /**
     * Unsubscribe a newsletter email
     * @param $email
     * @throws \bamboo\core\exceptions\BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    public function unsubscribe($email)
    {
        $newsl = $this->findOneBy(['email' => $email]);
        $newsl->isActive = 0;
        $newsl->unsubscriptionDate = $this->time();
        $newsl->update();
    }

    /**
     * @param CNewsletter $newsletter
     * @param bool $isTest
     * @return bool|string
     */
    public function sendNewsletterEmails(CNewsletter $newsletter, $isTest = true)
    {
        $newsletterUser = $newsletter;

        $test = $isTest ? ' è test ' : 'non test';

        if ($newsletter === null) {
            return "<p style='color:red'>la Newsletter  che stai cercando di inviare non esiste</p>";

        }
        // ottengo i valori dalla tabella newsletter

        $fromEmailAddressId = $newsletterUser->fromEmailAddressId;
        $newsletterEmailListId = $newsletterUser->newsletterEmailListId;
        $subject = $newsletterUser->subject;
        $preCompiledTemplate = $newsletterUser->preCompiledTemplate;
        $newsletterId = $newsletterUser->id;
        $newsletterCloneId = $newsletterUser->newsletterCloneId;

        //  ottengo le informazioni del sender;

        /** @var CEmailAddress $emailAddress */

        $emailAddress = \Monkey::app()->repoFactory->create('EmailAddress')->findOneBy(['id' => $fromEmailAddressId]);
        \Monkey::app()->applicationReport('NewsletterRepo', 'Newsletter Send', 'Sending Newsletter - from, isTest  = ' . $test, $emailAddress);
        $fromEmailAddress = $emailAddress->id;
        $checkEmailAddress = $emailAddress;

        if (empty($checkEmailAddress)) {
            $res = "<p style='color:red'>il sender che stai cercando di selezionare non esiste</p>";
        } else if (!empty($fromEmailAddress)) {
            $from = $emailAddress->id;
        }


        //Se è una newsletter madre
       /* if (!$newsletter->isChild()) {
            //se mamma allora piglio gli indirizzi come ho sempre fatto
            $indirizzi = $this->getAddressFromSql($isTest, $newsletterEmailListId);
        } else {
            if (!$newsletter->haveResend()) {
                //FIGLIO --> SE è SELEZIONATA UNA LISTA E NON UNA SUB-QUERY
                $indirizzi = $this->getAddressFromSql($isTest, $newsletterEmailListId);
            } else {
                //FIGLIO --> SE è SUB-QUERY
                $indirizzi = $this->getAddressFromCriterion($newsletter);
            }
        }*/
        $indirizzi = $this->getAddressFromSql($isTest, $newsletterEmailListId);

        //\Monkey::app()->applicationReport('NewsletterRepo', 'Newsletter Send', 'Sending Newsletter - sql, isTest  = '.$test,$sql);

        //\Monkey::app()->applicationReport('NewsletterRepo', 'Newsletter Send', 'Sending Newsletter - result, isTest  = '.$test,$indirizzi);
        /** @var CEmailRepo $emailRepo */
        $emailRepo = \Monkey::app()->repoFactory->create('Email');
        $verificafineciclo = 0;

       // $allEmailAddress = array_chunk($indirizzi, 900);
        $i=0;
        foreach ($indirizzi as $subTo) {
            try {
                \Monkey::app()->applicationLog('NewsletterRepo','log','send Newsletter n.'.$newsletterId,$sutoto,'' );
               // $this->sendBatchFromNewsletter($from, $subTo, $subject, $preCompiledTemplate, $newsletterId, $newsletterCloneId);
                $res = true;

                /** @var CEmailRepo $emailRepo */
                $emailRepo = \Monkey::app()->repoFactory->create('Email');
                $emailRepo->newMail($from, [$subTo], [], [], $subject, $preCompiledTemplate, null, $newsletterId, $newsletterCloneId, 'mailGun', false,null);
                $res=true;
                $i++;
                if($i==1){
                    break;
                }

            } catch (\Throwable $e) {
                $res = false;
            }

            if ($res) $verificafineciclo = $verificafineciclo + count($subTo);
        }

        if (count($indirizzi) === $verificafineciclo) {
            $res = "Email Generate ".$i;
            return $res;
        } else return 'errore, numero email sbagliato';

    }

    public function getAddressFromSql($isTest, $newsletterEmailListId)
    {

        /** @var  $CNewsletterEmailList $newsletterEmailList */
        $newsletterEmailList = \Monkey::app()->repoFactory->create('NewsletterEmailList')->findOneBy(['id' => $newsletterEmailListId]);

        if ($newsletterEmailList === null) {
            return "<p style='color:red'>il filtro per il gruppo selezionato  che stai cercando non esiste</p>";
        }

        $filterSql = $newsletterEmailList->sql;
        $newsletterGroupId = $newsletterEmailList->newsletterGroupId;

        /** @var CNewsletterGroup $newsletterGroup */
        $newsletterGroup = \Monkey::app()->repoFactory->create('NewsletterGroup')->findOneBy(['id' => $newsletterGroupId]);
        $checkNewsletterGroup = $newsletterGroup;

        if (empty($checkNewsletterGroup)) {
            return "<p style='color:red'>il  gruppo selezionato  che stai cercando non esiste</p>";
        }

        $sqlDefault = $newsletterGroup->sql;
        $sql = $sqlDefault . " " . $filterSql;

      /*  if ($isTest) {
            $indirizzi = [];
            $indirizzi[] = ['email' => \Monkey::app()->getUser()->getEmail()];
        } else {
            $indirizzi = \Monkey::app()->dbAdapter->query($sql, [])->fetchAll();
        }*/
        $indirizzi = \Monkey::app()->dbAdapter->query($sql, [])->fetchAll();
        return $indirizzi;
    }

    public function getAddressFromCriterion(CNewsletter $newsletter)
    {

        /** @var CNewsletterRepo $nRepo */
        $nRepo = \Monkey::app()->repoFactory->create('Newsletter');

        /** @var CNewsletter $parentNewsletter */
        $parentNewsletter = $nRepo->findOneBy(['id' => $newsletter->newsletterCloneId]);

        $criterionId = $newsletter->newsletterResendCriterionId;

        $indirizzi = [];
        switch ($criterionId) {
            case 1:
                //se picky
                if (!$newsletter->isExternal()) {
                    /** @var CObjectCollection $emails */
                    $emails = \Monkey::app()->repoFactory->create('Email')->findBy(['newsletterId' => $parentNewsletter->id]);
                    /** @var CEmail $email */
                    foreach ($emails as $email) {
                        /** @var CEmailRecipient $dest */
                        $dests = $email->emailRecipient->findByKey('typeTo', 'TO');

                        foreach ($dests as $dest){
                            if (is_null($dest->firstOpenTime)) {
                                $indirizzi[]['email'] = $dest->emailAddress->address;
                            }
                        }
                    }
                } else {
                    /** @var CObjectCollection $externalEmails */
                    $externalEmails = \Monkey::app()->repoFactory->create('ExternalEmail')->findBy(['newsletterId' => $parentNewsletter->id]);
                    /** @var CExternalEmail $externalEmail */
                    foreach ($externalEmails as $externalEmail) {
                        /** @var CEmailExternalRecipient $dest */
                        $dests = $externalEmail->emailExternalRecipient->findByKey('typeTo', 'TO');

                        foreach ($dests as $dest){
                            if (is_null($dest->firstOpenTime)) {
                                $indirizzi[]['email'] = $dest->newsletterExternalUser->email;
                            }
                        }
                    }
                }
                break;
            case 2:

                if (!$newsletter->isExternal()) {
                    /** @var CObjectCollection $emails */
                    $emails = \Monkey::app()->repoFactory->create('Email')->findBy(['newsletterId' => $parentNewsletter->id]);
                    /** @var CEmail $email */
                    foreach ($emails as $email) {
                        /** @var CEmailRecipient $dest */
                        $dests = $email->emailRecipient->findByKey('typeTo', 'TO');

                        foreach ($dests as $dest){
                            if (!is_null($dest->firstOpenTime) && is_null($dest->firstClickTime)) {
                                $indirizzi[]['email'] = $dest->emailAddress->address;
                            }
                        }
                    }
                } else {
                    /** @var CObjectCollection $externalEmails */
                    $externalEmails = \Monkey::app()->repoFactory->create('ExternalEmail')->findBy(['newsletterId' => $parentNewsletter->id]);
                    /** @var CExternalEmail $externalEmail */
                    foreach ($externalEmails as $externalEmail) {
                        /** @var CEmailExternalRecipient $dest */
                        $dests = $externalEmail->emailExternalRecipient->findByKey('typeTo', 'TO');

                        foreach ($dests as $dest){
                            if (!is_null($dest->firstOpenTime) && is_null($dest->firstClickTime)) {
                                $indirizzi[]['email'] = $dest->newsletterExternalUser->email;
                            }
                        }

                    }
                }
                break;
        }
        return $indirizzi;

    }

    public function sendBatchFromNewsletter($from, $to, $subject, $body, $newsletterId, $newsletterCloneId)
    {

        try {

            //vedo se è una newsletter esterna o interna
            /** @var CNewsletter $newsletter */
            $newsletter = \Monkey::app()->repoFactory->create('Newsletter')->findOneBy(['id' => $newsletterId]);

            $isExternal = $newsletter->isExternal();

         /*   if (!$isExternal) {
                /** @var CEmailRepo $emailRepo */
            /*
                $emailRepo = \Monkey::app()->repoFactory->create('Email');
                $emailRepo->newBatchMail($from, $to, $subject, $body, $newsletterId, $newsletterCloneId,'MailGun',false,null);
            } else {
                /** @var CExternalEmailRepo $externalEmailRepo */
            /*
                $externalEmailRepo = \Monkey::app()->repoFactory->create('ExternalEmail');
                $externalEmailRepo->newExternalMail($from, $to, $subject, $body, $newsletterId, $newsletterCloneId,'MailGun',false,null);
            }*/
            $tos=[$to];
            /** @var CEmailRepo $emailRepo */
            $emailRepo = \Monkey::app()->repoFactory->create('Email');
            $emailRepo->newBatchMail($from, $tos, $subject, $body, $newsletterId, $newsletterCloneId,'MailGun',false,null);

        } catch (\Throwable $e) {
            $this->report('Error while sending', $e->getMessage(), $e);
        }

        return true;
    }

}