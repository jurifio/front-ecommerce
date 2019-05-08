<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 04/08/2015
 * Time: 12:53
 */

namespace bamboo\ecommerce\offline\productsync\import;


interface IProductImporter {

    public function run($args = null);
    public function fetchFiles();
    public function readFiles();
    public function readMain();
    public function saveFiles();

}