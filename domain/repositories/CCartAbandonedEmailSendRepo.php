<?php

namespace bamboo\domain\repositories;

use bamboo\blueseal\jobs\CCartAbandonedEmailSendJobs;
use bamboo\core\exceptions\BambooDBALException;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\core\email\CMailService;
use bamboo\domain\entities\CEmailAddress;
use bamboo\domain\entities\CNewsletter;
use bamboo\domain\entities\CUser;
use bamboo\domain\entities\CCartLine;
use bamboo\domain\entities\CCoupon;
use bamboo\domain\entities\CCart;
use bamboo\domain\entities\CCartAbandonedEmailSend;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\exceptions\BambooMailException;
use bamboo\core\theming\CMailerHelper;


/**
 * Class CCartAbandonedSendEmailRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 16/07/2018
 * @since 1.0
 */
class CCartAbandonedEmailSendRepo extends ARepo
{
    use TMySQLTimestamp;


    /**
     * @param CCartAbandonedEmailSendJobs $cartAbandonedEmailSend ;
     * @param bool $isTest
     * @param bool $withEvents
     * @return bool|string
     * @throws BambooDBALException
     */
    public function cartAbandonedEmailSend(CCartAbandonedEmailSendJobs $cartAbandonedEmailSend, $isTest = true, $withEvents = true)
    {
        $cartAbandonedEmailSend;

        $test = $isTest ? ' è test ' : 'non test';

        if ($cartAbandonedEmailSend === null) {
            return "<p style='color:red'>I Carrelli Abbandonati  che stai cercando di segnalare ai clienti  non esistono</p>";

        }
        // ottengo i valori dalla tabella CCartAbandonedEmailSend
        $idCartAbandonedEmailSend = $cartAbandonedEmailSend->id;
        $firstTemplateId = $cartAbandonedEmailSend->firstTemplateId;
        $firstEmailTemplate = $cartAbandonedEmailSend->firstEmailTemplate;
        $firstSentCheck = $cartAbandonedEmailSend->firstSendCheck;
        $secondTemplateId = $cartAbandonedEmailSend->secondTemplateId;
        $secondEmailTemplate = $cartAbandonedEmailSend->secondEmailTemplate;
        $secondSentCheck = $cartAbandonedEmailSend->secondSendCheck;
        $thirdTemplateId = $cartAbandonedEmailSend->thirdTemplateId;
        $thirdEmailTemplate = $cartAbandonedEmailSend->thirdEmailTemplate;
        $thirdSentCheck = $cartAbandonedEmailSend->ThirdSentCheck;
        $userId = $cartAbandonedEmailSend->userId;
        $couponId = $cartAbandonedEmailSend->couponId;
        $couponTypeId = $cartAbandonedEmailSend->couponTypeId;
        $cartId = $cartAbandonedEmailSend->cartId;
        $selectMailCouponSend = $cartAbandonedEmailSend->selectMailCouponSend;
        $emailUserFind = \Monkey::app()->repoFactory->create('EmailAddress')->findOneBy(['id' => $userId]);
        $emailUser = $emailUserFind->address;
        $emailUserDetails =\Monkey::app()->repoFactory->create('UserDetails')->findOneBY(['userId'=>$userId]);
        $userDetail=$emailUserDetails->name." ".$emailUserDetails->surname;
        $from = 'no-reply@pickyshop.com';
        $subject = 'Completa il tuo Ordine';
        $cartLineFind = \Monkey::app()->repoFactory->create('CartLine')->findby(['cartId' => $cartId]);
        $cartRow = "";
        foreach ($cartLineFind as $cartLine) {
            $productId = $cartLine->productId;
            $productVariantId = $cartLine->productVariantId;
            $productSizeId = $cartLine->productSizeId;
            $productPublicSkuFind = \Monkey::app()->repoFactory->create('productPublicSku')->findOneBy(['productId' => $productId, 'productVariantId' => $productVariantId, 'productSizeId' => $productSizeId]);
            $isOnSaleFind = \Monkey::app()->repoFactory->create('Product')->findOneBy(['id' => $productId, 'productVariantId' => $productVariantId]);
            $isOnSale = $isOnSaleFind->isOnSale;
            if ($isOnSale == "1") {
                $price = $productPublicSkuFind->salePrice;
            } else {
                $salePrice = $productPublicSkuFind->price;
            }
            $dummyPicture = $isOnSaleFind->dummyPicture;
            $productBrandFind = \Monkey::app()->repoFactory->create('ProductBrand')->findOneBy(['id' => $isOnSale->productBrandId]);
            $productBrand = $productBrandFind->name;
            $cartRowLine = "<!--riga carrello-->
<tr>
<td class=\"lh-3\" style=\"padding: 0px 40px; margin: 0px;\" align=\"center\" valign=\"top\"><hr /></td>
</tr>
<tr>
<td style=\"padding: 0 20px; margin: 0;\" align=\"center\" valign=\"top\">
<table border=\"0\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\">
<tbody>
<tr>
<td style=\"padding: 0 10px; margin: 0;\" align=\"center\" valign=\"top\">
<table border=\"0\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\">
<tbody>
<tr>
<td class=\"\" style=\"margin: 0px; padding: 10px 0;\" align=\"left\" valign=\"top\" width=\"11%\">
<table border=\"0\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\">
<tbody>
<tr>
<td style=\"padding: 0; margin: 0;\" align=\"center\" valign=\"top\">
<table border=\"0\" width=\"64\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\" data-editable=\"image\" data-mobile-width=\"0\">
<tbody>
<tr>
<td class=\"tdBlock\" style=\"display: inline-block; padding: 0px 0px 0px 40px; margin: 0px;\" align=\"left\" valign=\"top\"><img style=\"font-size: 12px; display: block; border: 0px none transparent;\" src=\"" . $dummyPicture . "\" height=\"100\" border=\"0\" /></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
<td class=\"\" style=\"padding: 0px; margin: 0px;\" align=\"left\" valign=\"top\" width=\"50%\">
<table border=\"0\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\" data-editable=\"text\">
<tbody>
<tr>
<td class=\"lh-1\" style=\"padding: 50px 0px; margin: 0px; line-height: 1.15; font-size: 16px; font-family: Times New Roman, Times, serif;\" align=\"center\" valign=\"top\"><span style=\"font-family: Helvetica,Arial,sans-serif; font-size: 14px; font-weight: 300; color: #000000; line-height: 0.5;\">" . $productBrand . " Prodotto:" . $productId . "-" . $productVariantId . "</span></td>
</tr>
</tbody>
</table>
</td>
<td class=\"\" style=\"padding: 0px; margin: 0px;\" align=\"left\" valign=\"top\" width=\"30%\">
<table border=\"0\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\" data-editable=\"text\">
<tbody>
<tr>
<td class=\"lh-3\" style=\"padding: 50px 20px 5px 30px; margin: 0px; line-height: 1.35; font-size: 16px; font-family: Times New Roman, Times, serif;\" align=\"right\" valign=\"top\"><span style=\"font-family: Helvetica,Arial,sans-serif; font-size: 14px; font-weight: 300; color: #000000; line-height: 0.5;\"> <span style=\"font-weight: bold;\">" . $price . "</span> </span></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<!-- fine Riga Carrello-->";
            $cartRow = $cartRow + $cartRowLine;


        }
        if ($couponTypeId != "0") {
            $couponTypeFind = \Monkey::app()->repoFactory->create('CouponType')->findOneBy(['id' => $couponTypeId]);
            $amountType = $couponTypeFind->amountType;
            $amount = $couponTypeFind->amount;
            $couponFind = \Monkey::app()->repoFactory->create('Coupon')->findOneBy(['id' => $couponId]);
            $code = $couponFind->code;
            if ($amountType == "P") {
                $couponFirstRow = "Abbiamo riservato Per TE un Coupon del " . $amount . "% di sconto che potrai utilizzare per completare l'ordine!";
            } else {
                $couponFirstRow = "Abbiamo riservato Per TE un Coupon del valore di " . $amount . "€ di  sconto che potrai utilizzare per completare l'ordine!";
            }

            $cartRowCoupon = "<!--inizio sezione coupon-->
							<tr>
                                <td valign=\"top\" align=\"left\" class=\"lh-3\"
                                    style=\"padding: 5px 20px; margin: 0px; line-height: 1; font-size: 16px; font-family: Times New Roman, Times, serif;\">
                                    <span style=\"font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:300;color:#000000; line-height:0.5;\">
                                      " . $couponFirstRow . "
                                    </span>
                                </td>
                            </tr>
							<tr>
                                <td valign=\"top\" align=\"left\" class=\"lh-3\"
                                    style=\"padding: 5px 20px; margin: 0px; line-height: 1; font-size: 16px; font-family: Times New Roman, Times, serif;\">
                                    <span style=\"font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:300;color:#000000; line-height:0.5;\">
                                     " . $code . "
                                    </span>
                                </td>
                            </tr>
							<tr>
                                <td valign=\"top\" align=\"left\" class=\"lh-3\"
                                    style=\"padding: 5px 20px; margin: 0px; line-height: 1; font-size: 16px; font-family: Times New Roman, Times, serif;\">
                                    <span style=\"font-family:Helvetica,Arial,sans-serif;font-size:14px;font-weight:300;color:#000000; line-height:0.5;\">
                                       Inserisci il coupon nell'area riservata del tuo carrello.
                                    </span>
                                </td>
                            </tr>
							<!--fine sezione Coupon-->";
            if ($firstSentCheck == '0') {
                try {
                    $message = str_replace('name', $userDetail, $firstEmailTemplate);
                    $message = str_replace('emailunsuscriber', $emailUser, $firstEmailTemplate);
                    $message = str_replace('cartRow', $cartRow, $firstEmailTemplate);
                    if ($selectMailCouponSend == "1" || $selectMailCouponSend == "4") {
                        $message = str_replace('cartRowCoupon', $cartRowCoupon, $firstEmailTemplate);
                    }
                    if ($withEvents) {
                        $args = [$from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false];
                        \Monkey::app()->eventManager->triggerEvent('newEmail', $args);
                        $res = true;
                    } else {
                        $emailRepo = \Monkey::app()->repoFactory->create('Email');
                        $res = $emailRepo->newMail($from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false);
                        /* @var CCartAbandonedEmailSend $cartAbandonedEmailSentUpdate */
                        $cartAbandonedEmailSentUpdate = \Monkey::app()->repoFactory->create('CartAbandonedEmailSend')->findOneBy(['id' => $idCartAbandonedEmailSend]);
                        $cartAbandonedEmailSentUpdate->firstSentCheck = "1";
                    }

                } catch (\Throwable $e) {
                    $res = false;
                }
            } elseif ($secondSentCheck == '0') {
                try {
                    $message =str_replace('name', $userDetail, $secondEmailTemplate);
                    $message = str_replace('emailunsuscriber', $emailUser, $secondEmailTemplate);
                    $message = str_replace('cartRow', $cartRow, $secondEmailTemplate);
                    if ($selectMailCouponSend == "2" || $selectMailCouponSend == "4") {
                        $message = str_replace('cartRowCoupon', $cartRowCoupon, $secondEmailTemplate);
                    }

                    if ($withEvents) {
                        $args = [$from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false];
                        \Monkey::app()->eventManager->triggerEvent('newEmail', $args);
                        $res = true;
                    } else {
                        $emailRepo = \Monkey::app()->repoFactory->create('Email');
                        $res = $emailRepo->newMail($from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false);

                        /* @var CCartAbandonedEmailSend $cartAbandonedEmailSentUpdate */
                        $cartAbandonedEmailSentUpdate = \Monkey::app()->repoFactory->create('CartAbandonedEmailSend')->findOneBy(['id' => $idCartAbandonedEmailSend]);
                        $cartAbandonedEmailSentUpdate->secondSentCheck = "1";
                    }

                } catch (\Throwable $e) {
                    $res = false;
                }

            } elseif ($thirdSentCheck == '0') {
                try {
                    $message =str_replace('name', $userDetail, $thirdEmailTemplate);
                    $message = str_replace('emailunsuscriber', $emailUser, $thirdEmailTemplate);
                    $message = str_replace('cartRow', $cartRow, $thirdEmailTemplate);
                    if ($selectMailCouponSend == "3" || $selectMailCouponSend == "4") {
                        $message = str_replace('cartRowCoupon', $cartRowCoupon, $thirdEmailTemplate);
                    }
                    if ($withEvents) {
                        $args = [$from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false];
                        \Monkey::app()->eventManager->triggerEvent('newEmail', $args);
                        $res = true;
                    } else {
                        $emailRepo = \Monkey::app()->repoFactory->create('Email');
                        $res = $emailRepo->newMail($from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false);

                        /* @var CCartAbandonedEmailSend $cartAbandonedEmailSentUpdate */
                        $cartAbandonedEmailSentUpdate = \Monkey::app()->repoFactory->create('CartAbandonedEmailSend')->findOneBy(['id' => $idCartAbandonedEmailSend]);
                        $cartAbandonedEmailSentUpdate->thirdSentCheck = "1";
                    }

                } catch (\Throwable $e) {
                    $res = false;
                }
            } else {
                $res = "Tutte le email per i carrelli abbandonati sono stati inviati per questo utente";
            }
        } else {
            if ($firstSentCheck == '0') {
                try {
                    $message =str_replace('name', $userDetail, $firstEmailTemplate);
                    $message = str_replace('emailunsuscriber', $emailUser, $firstEmailTemplate);
                    $message = str_replace('cartRow', $cartRow, $firstEmailTemplate);

                    if ($withEvents) {
                        $args = [$from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false];
                        \Monkey::app()->eventManager->triggerEvent('newEmail', $args);
                        $res = true;
                    } else {
                        $emailRepo = \Monkey::app()->repoFactory->create('Email');
                        $res = $emailRepo->newMail($from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false);
                        /* @var CCartAbandonedEmailSend $cartAbandonedEmailSentUpdate */
                        $cartAbandonedEmailSentUpdate = \Monkey::app()->repoFactory->create('CartAbandonedEmailSend')->findOneBy(['id' => $idCartAbandonedEmailSend]);
                        $cartAbandonedEmailSentUpdate->firstSentCheck = "1";
                    }

                } catch (\Throwable $e) {
                    $res = false;
                }
            } elseif ($secondSentCheck == '0') {
                try {
                    $message =str_replace('name', $userDetail, $secondEmailTemplate);
                    $message = str_replace('emailunsuscriber', $emailUser, $secondEmailTemplate);
                    $message = str_replace('cartRow', $cartRow, $secondEmailTemplate);


                    if ($withEvents) {
                        $args = [$from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false];
                        \Monkey::app()->eventManager->triggerEvent('newEmail', $args);
                        $res = true;
                    } else {
                        $emailRepo = \Monkey::app()->repoFactory->create('Email');
                        $res = $emailRepo->newMail($from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false);

                        /* @var CCartAbandonedEmailSend $cartAbandonedEmailSentUpdate */
                        $cartAbandonedEmailSentUpdate = \Monkey::app()->repoFactory->create('CartAbandonedEmailSend')->findOneBy(['id' => $idCartAbandonedEmailSend]);
                        $cartAbandonedEmailSentUpdate->secondSentCheck = "1";
                    }

                } catch (\Throwable $e) {
                    $res = false;
                }

            } elseif ($thirdSentCheck == '0') {
                try {
                    $message = str_replace('name', $userDetail, $thirdEmailTemplate);
                    $message = str_replace('emailunsuscriber', $emailUser, $thirdEmailTemplate);
                    $message = str_replace('cartRow', $cartRow, $thirdEmailTemplate);

                    if ($withEvents) {
                        $args = [$from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false];
                        \Monkey::app()->eventManager->triggerEvent('newEmail', $args);
                        $res = true;
                    } else {
                        $emailRepo = \Monkey::app()->repoFactory->create('Email');
                        $res = $emailRepo->newMail($from, [$emailUser], [], [], $subject, $message, null, null, null, 'mailGun', false);

                        /* @var CCartAbandonedEmailSend $cartAbandonedEmailSentUpdate */
                        $cartAbandonedEmailSentUpdate = \Monkey::app()->repoFactory->create('CartAbandonedEmailSend')->findOneBy(['id' => $idCartAbandonedEmailSend]);
                        $cartAbandonedEmailSentUpdate->thirdSentCheck = "1";
                    }

                } catch (\Throwable $e) {
                    $res = false;
                }
            } else {
                $res = "Tutte le email per i carrelli abbandonati sono stati inviati per questo utente";
            }
        }

return $res;
    }
}
