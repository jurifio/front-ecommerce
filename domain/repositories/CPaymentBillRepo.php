<?php


namespace bamboo\domain\repositories;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\domain\entities\CDocument;
use bamboo\domain\entities\CPaymentBill;
use bamboo\utils\time\SDateToolbox;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CPaymentBillRepo
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
class CPaymentBillRepo extends ARepo
{

    /**
     * @param int $maxHeight
     * @return mixed
     */
    public function defaultPaymentBillsCreation($maxHeight = 2000) {
        $paymentDate = $this->app->dbAdapter->query("SELECT max(paymentDate) AS data FROM PaymentBill", [])->fetch()['data'];
        $paymentDate = SDateToolbox::GetNextWorkingDay(STimeToolbox::GetDateTime($paymentDate));
        $invoices = \Monkey::app()->repoFactory->create('Document')->fetchUnboundedExpiringInvoices($paymentDate);
        $elements = $this->prepareElements($invoices);
        $bins = $this->bestFitWithNegatives($elements,$maxHeight,[])['bins'];
        return $this->createPaymentBillsFromBins($bins,$paymentDate);
    }

    /**
     * @param int $maxHeight
     * @return mixed
     */
    public function createFillingBill($maxHeight = 2000) {
        $lastPaymentBill = $this->findOneBySql("Select id from PaymentBill where paymentDate >= current_date order by paymentDate desc");
        if(is_null($lastPaymentBill)) return [];
        $newHeight = $maxHeight - $lastPaymentBill->amount;
        $invoices = \Monkey::app()->repoFactory->create('Document')->fetchUnboundedExpiringInvoices($lastPaymentBill->paymentDate);
        $elements = $this->prepareElements($invoices);
        $bins = $this->bestFitWithNegatives($elements,$newHeight)['bins'];
        return $this->createPaymentBillsFromBins(empty($bins) ? $bins : [$bins[0]],STimeToolbox::GetDateTime($lastPaymentBill->paymentDate));

    }

    /**
     * @param $bins
     * @param \DateTime|null $paymentDate
     * @return mixed
     * @throws \Throwable
     */
    protected function createPaymentBillsFromBins(array $bins, \DateTime $paymentDate = null)
    {
        \Monkey::app()->repoFactory->beginTransaction();
        try {
            foreach ($bins as $bin) {

                $paymentBill = $this->getEmptyEntity();
                $paymentBill->amount = $bin['height'];
                $paymentBill->paymentDate = STimeToolbox::DbFormattedDate($paymentDate);
                $paymentBill->id = $paymentBill->insert();

                foreach ($bin['elements'] as $element) {

                    $paymentBillHasInvoiceNew = \Monkey::app()->repoFactory->create('PaymentBillHasInvoiceNew')->getEmptyEntity();
                    $paymentBillHasInvoiceNew->paymentBillId = $paymentBill->id;
                    $paymentBillHasInvoiceNew->invoiceNewId = $element['id'];
                    $paymentBillHasInvoiceNew->insert();

                    foreach ($element['contained'] as $contained) {
                        $paymentBillHasInvoiceNew = \Monkey::app()->repoFactory->create('PaymentBillHasInvoiceNew')->getEmptyEntity();
                        $paymentBillHasInvoiceNew->paymentBillId = $paymentBill->id;
                        $paymentBillHasInvoiceNew->invoiceNewId = $contained['id'];
                        $paymentBillHasInvoiceNew->insert();
                    }
                }
                $paymentDate = SDateToolbox::GetNextWorkingDay($paymentDate);
            }
            \Monkey::app()->repoFactory->commit();

        } catch (\Throwable $e) {
            \Monkey::app()->repoFactory->rollback();
            throw $e;
        }
        return $bins;
    }

    protected function prepareElements(CObjectCollection $invoices, $boundNegatives = true)
    {
        $elements = [];
        $elementsToBound = [];
        foreach ($invoices as $invoice) {
            /** @var CDocument $invoice */
            $element = [];
            $element['id'] = $invoice->id;
            $element['payTo'] = $invoice->shopAddressBook->id;
            $element['height'] = $invoice->getSignedValueWithVat();
            $element['contained'] = [];
            if ($element['height'] < 0) $elementsToBound[] = $element;
            else $elements[] = $element;
        }

        if ($boundNegatives) {
            foreach ($elementsToBound as $keyN => $negativeElement) {
                $sum = $negativeElement['height'];
                $compensatingElements = [];
                foreach ($elements as $key => $positiveElement) {
                    if ($positiveElement['payTo'] == $negativeElement['payTo']) {
                        $sum = $sum + $positiveElement['height'];
                        $compensatingElements[$key] = $positiveElement;
                        if ($sum > 0) break;
                    }
                }
                if ($sum > 0) {
                    foreach ($compensatingElements as $key => $element) {
                        $elementsToBound[$keyN]['contained'][] = $element;
                        unset($elements[$key]);
                    }
                    $elementsToBound[$keyN]['height'] = $sum;
                }
            }
        }
        $elements = array_merge($elementsToBound, array_values($elements));
        return $elements;
    }


    /**
     * @param $elements
     * @param $binHeight
     * @param array $bins
     * @return array
     */
    protected function bestFitWithNegatives($elements, $binHeight, $bins = [])
    {
        $excludedElements = [];
        foreach ($elements as $element) {
            $bestBin = null;
            $bestBinAmount = -1;

            foreach ($bins as $key => $bin) {
                if (!isset($bin['payToHeight'][$element['payTo']])) {
                    $bin['payToHeight'][$element['payTo']] = 0;
                    $bins[$key] = $bin;
                }

                if (
                    ($bin['height'] + $element['height']) > $bestBinAmount &&
                    ($bin['height'] + $element['height']) < $binHeight &&
                    ($bin['payToHeight'][$element['payTo']] + $element['height']) > 0
                ) {
                    $bestBinAmount = $bin['height'] + $element['height'];
                    $bestBin = $key;
                }
            }

            if ($bestBin === null && $element['height'] > 0) {
                $newIndex = count($bins);
                $bin = [
                    'elements' => [],
                    'height' => 0,
                    'payToHeight' => []
                ];

                if (!isset($bin['payToHeight'][$element['payTo']]))
                    $bin['payToHeight'][$element['payTo']] = 0;

                $bins[] = $bin;

                $bestBin = $newIndex;
            }

            if ($bestBin !== null) {
                $bins[$bestBin]['elements'][] = $element;
                $bins[$bestBin]['height'] += $element['height'];
                $bins[$bestBin]['payToHeight'][$element['payTo']] += $element['height'];
            } else {
                $excludedElements[] = $element;
            }
        }
        return [
            'bins' => $bins,
            'excludedElements' => $excludedElements
        ];
    }

    /**
     * set a paymentBill as submitted and set alla invoice as payed in the payment bill paymentDate
     * @param CPaymentBill $paymentBill
     * @param \DateTime $dateTime
     * @param bool $ingoreSubmissionDate
     * @return bool
     * @throws BambooLogicException
     */
    public function submitPaymentBill(CPaymentBill $paymentBill, \DateTime $dateTime, $ingoreSubmissionDate = false)
    {
        if ($paymentBill->isSubmitted()) throw new BambooLogicException('Distinta già sottomessa');
        $dataPagamento = STimeToolbox::GetDateTimeFromDBValue($paymentBill->paymentDate);
        if ($ingoreSubmissionDate && $dateTime->setTime(0, 0) > $dataPagamento->setTime(0, 0)) throw new BambooLogicException('Distinta sottomessa dopo la data di pagamento');
        \Monkey::app()->repoFactory->beginTransaction();
        $date = STimeToolbox::DbFormattedDateTime($dateTime);
        $paymentBill->submissionDate = $date;
        foreach ($paymentBill->document as $document) {
            \Monkey::app()->repoFactory->create('Document')->payFriendInvoice($document, null, $date);
        }
        $paymentBill->update();
        \Monkey::app()->repoFactory->commit();
        return true;
    }
}
