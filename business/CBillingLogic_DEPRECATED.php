<?php

namespace bamboo\ecommerce\business;

/**
 * Class CBillingLogic
 * @package bamboo\app
 */
class CBillingLogic {

    /** @var float/ Prezzo di Vendita */
    private $v;
    /** @var float/ Prezzo di Costo */
    private $c;
    /** @var float / Prezzo di Vendita al esposto al Pubblico */
    private $vp;
    /** @var float < 1/ Percentuale Sconto sul prodotto */
    private $s;
    /** @var float < 1/ Percentuale Sconto sul costo Fornitore (0-0.35-???) */
    private $sf;
    /** @var / Moltiplicatore MarkUp */
    private $m;
    /** @var / MarkUp calcolato */
    private $mc;
    /** @var float < 1 / Percentuale Iva */
    private $iva;
    /** @var / costo Spedizione */
    private $csp;



    public function __construct($vendita,$costo,$venditaPubblico,$scontoCosto,$moltiplicatore, $iva, $spedizione)
    {
        $this->v = $vendita;
        $this->c = $costo;
        $this->vp = $venditaPubblico;
        //$this->s = $scontoProdotto; ??
        $this->sf = $scontoCosto;
        $this->m = $moltiplicatore;
        $this->iva = $iva;
        $this->csp = $spedizione;

        $this->mc = $this->calcMC($vendita, $costo);
    }

    public function calcMC($vendita, $costo)
    {
        return ($vendita / $costo) /100;
    }

    public function calcFornitore()
    {
/*        $res1 = ($this->c - ($this->c*$this->sf));
        var_dump($res1);
        $res20 = (($this->mc)*$this->m);
        var_dump($res20);
        $res21 = 1+ $res20;
        var_dump($res21);
        $resf = $res1*$res21;
        var_dump($resf);
  */      $res = ($this->c - ($this->c*$this->sf))*(1+(($this->mc)*$this->m));
        return $res;
    }

    public function calcPickyScorporato()
    {
        $res = ($this->vp*( (100) / (100+(100*$this->iva)) ) ) - $this->calcFornitore();
        return $res;
    }

    public function calcPickyNetto()
    {
        $res = $this->calcPickyScorporato() - $this->csp;
        return $res;
    }

    public function calcPickyLordo()
    {
        $res = $this->vp - $this->calcFornitore();
        return $res;
    }

} 