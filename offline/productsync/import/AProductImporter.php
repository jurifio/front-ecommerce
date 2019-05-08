<?php
namespace bamboo\ecommerce\offline\productsync\import;

use bamboo\core\application\AApplication;
use bamboo\core\base\CConfig;
use bamboo\core\jobs\ACronJob;

/**
 * Class AProductImporter
 * @package bamboo\offline\import\productsync
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
abstract class AProductImporter extends ACronJob implements IProductImporter
{
    protected $config;
    protected $shop;

    /**
     * @param AApplication $app
     * @param null $jobExecution
     */
    public function __construct(AApplication $app, $jobExecution = null)
    {
        parent::__construct($app, $jobExecution);
        $this->shop = $this->getShop();

	    $this->config = new CConfig(__DIR__ . "/" . $this->getShop()->name . "/import." . $this->getShop()->name . ".config.json");
	    $this->report("__construct import for ".$this->getShop()->name,"Config File: ".__DIR__ . "/" . $this->shop->name . "/import." . $this->getShop()->name . ".config.json");
	    $this->config->load();
    }

    public abstract function getShop();
}