<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\exceptions\BambooApplicationException;
use bamboo\utils\time\STimeToolbox;

/**
 * Class CPaymentBill
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
 * @property CObjectCollection $paymentBillHasInvoiceNew
 * @property CObjectCollection $document
 */
class CPaymentBill extends AEntity
{
    protected $entityTable = 'PaymentBill';
    protected $primaryKeys = ['id'];

    CONST Cd = 'SUPP';
    CONST SvcLvl_Cd = 'SEPA';
    CONST CtgyPurp_Cd = 'SUPP';
    CONST Issr = 'CBI';
    CONST InstrPrty = 'NORM';
    CONST PmtMtd = 'TRF';
    CONST Nm = 'INTERNATIONAL WEB ECOMMERCE SERVICES S.N.C.';

    public function isSubmitted() {
        return isset($this->fields['submissionDate']) && !is_null(STimeToolbox::GetDateTimeFromDBValue($this->fields['submissionDate']));
    }

    /**
     * @param bool $indent
     * @return string
     */
    public function toXML($indent = true) {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent($indent);
        $writer->startDocument('1.0','ISO-8859-1');
        $writer->startElement('CBIPaymentRequest');
        $writer->writeAttribute('xmlns','urn:CBI:xsd:CBIPaymentRequest.00.04.00');

        $writer->startElement('GrpHdr');
        $writer->writeElement('MsgId',$this->id);
        $writer->writeElement('CreDtTm',(new \DateTime())->format(DATE_ATOM)); // controllare formato data
        $writer->writeElement('NbOfTxs',count($this->getDistinctPayments())); // controllare formato data
        $writer->writeElement('CtrlSum',$this->getTotal()); // controllare formato data

        $writer->startElement('InitgPty'); // chi paga?
        $writer->writeElement('Nm',self::Nm); // chi paga?
        $writer->startElement('Id'); // chi paga?

        $writer->startElement('OrgId'); // chi paga?

        $writer->startElement('Othr'); // chi paga?
        $writer->writeElement('Id',$this->getOrganizationId()); // do cazzo sta l'id
        $writer->writeElement('Issr',self::Issr); // di default? bo
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('PmtInf');
        $writer->writeElement('PmtInfId',$this->id);
        $writer->writeElement('PmtMtd',self::PmtMtd);
        $writer->startElement('PmtTpInf');

        $writer->writeElement('InstrPrty',self::InstrPrty);

        $writer->startElement('SvcLvl');
        $writer->writeElement('Cd',self::SvcLvl_Cd);
        $writer->endElement();
        $writer->endElement();

        $writer->writeElement('ReqdExctnDt',STimeToolbox::FormatDateFromDBValue($this->paymentDate,'Y-m-d'));
        $writer->startElement('Dbtr');

        $writer->writeElement('Nm',self::Nm); // chi paga?
        $writer->startElement('Id'); // chi paga?

        $writer->startElement('OrgId'); // chi paga?

        $writer->startElement('Othr'); // chi paga?
        $writer->writeElement('Id',$this->getOrganizationId()); // do cazzo sta l'id
        $writer->writeElement('Issr','CBI'); // di default? bo
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('DbtrAcct');
        $writer->startElement('Id');
        $writer->writeElement('IBAN',$this->getOurIban());
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('DbtrAgt');
        $writer->startElement('FinInstnId');
        $writer->startElement('ClrSysMmbId');
        $writer->writeElement('MmbId',$this->getOurBIC());
        $writer->endElement();

        //$writer->writeElement('BIC',$this->getOurBIC());
        $writer->endElement();

        $writer->endElement();

        $writer->writeElement('ChrgBr','SLEV');

        $this->writeTransactions($writer);
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        return $writer->outputMemory();
    }

    /**
     * @param \XMLWriter $writer
     */
    protected function writeTransactions(\XMLWriter $writer)
    {
        $addressBookDest = [];
        foreach($this->document as $document) {
            $addressBookDest[$document->shopAddressBook->id][] = $document;
        }
        $i = 1;
        foreach ($addressBookDest as $key => $addressBook) {
            $i = $this->writeTransactionInvoice($writer,$addressBook,$addressBook[0]->shopAddressBook, $i);
        }
        return;
    }

    /**
     * @param \XMLWriter $writer
     * @param array $documents
     * @param CAddressBook $addressBook
     * @param $instrId
     */
    protected function writeTransactionInvoice(\XMLWriter $writer, array $documents, CAddressBook $addressBook, $instrId) {
        $total = 0;
        $comments = "";
        /** Se arriviamo oltre il limite di commenti spezziamo il pagamento in piu pezzi */
        $remaining = [];
        shuffle($documents);
        foreach ($documents as $document) {
            /** @var CDocument $document */
            if(strlen($comments . " " . $document->number) < 140) {
                $total += $document->getSignedValueWithVat();
                $comments.= (" " . $document->number);
            } else {
                $remaining[] = $document;
            }
        }

        if($total < 0) throw new BambooApplicationException('Si è verificata una anomalia nello spezzare le transazioni a causa della conta dei caratteri nei commenti');
        $writer->startElement('CdtTrfTxInf');
        $writer->startElement('PmtId');
        $writer->writeElement('InstrId',$instrId);
        $writer->writeElement('EndToEndId',$this->calculateEndToEndId($instrId));
        $writer->endElement();

        $writer->startElement('PmtTpInf');
        $writer->startElement('CtgyPurp');
        $writer->writeElement('Cd',self::CtgyPurp_Cd);
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('Amt');
        $writer->startElement('InstdAmt');
        $writer->writeAttribute('Ccy','EUR');
        $writer->writeRaw(round($total,2));
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('Cdtr');
        $writer->writeElement('Nm',$addressBook->subject);

        $writer->startElement('PstlAdr');
        $writer->writeElement('Ctry',$addressBook->country->ISO);
        $writer->writeElement('AdrLine',$addressBook->address);
        $writer->writeElement('AdrLine',$addressBook->city.' '.$addressBook->province.' '.$addressBook->postcode);
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('CdtrAcct');
        $writer->startElement('Id');
        $writer->writeElement('IBAN',$addressBook->iban);
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('RmtInf');
        $writer->writeElement('Ustrd', trim($comments));
        $writer->endElement();
        $writer->endElement();

        if(!empty($remaining)) {
            $instrId = $this->writeTransactionInvoice($writer,$remaining,$addressBook,++$instrId);
        }
        return ++$instrId;
    }

    /**
     * Calcola l' EndToEndId non si sa come
     * @param $instrId
     * @return int
     */
    protected function calculateEndToEndId($instrId)
    {
        return 'NOTPROVIDED'.$instrId;
    }

    /**
     * @return array
     */
    public function getDistinctPayments()
    {
        $addressBookDest = [];
        foreach($this->document as $document) {
            $addressBookDest[$document->shopAddressBook->id][] = $document;
        }
        return $addressBookDest;
    }

    /**
     * scrivere le configurazioni da qualche parte
     * e tirarle fuori, non si capisce a che serve sto coso
     * @return string
     */
    public function getOrganizationId()
    {
        return 'F99999HB';
    }

    /**
     * calcolare il totale o prenderlo da amount
     * @return float
     */
    public function getTotal()
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getOurIban()
    {
        return 'IT48L0521613400000000002334';
    }

    /**
     * return BIC of our bank
     * @return string
     */
    public function getOurBIC()
    {
        return '05216';

    }

    /**
     * return ABI of our bank
     * @return string
     */
    public function getOurABI()
    {
        return 'BPCVIT2S';
    }
}