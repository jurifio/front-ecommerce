<?php
/**
 * Created by PhpStorm.
 * User: Fabrizio Marconi
 * Date: 04/08/2015
 * Time: 12:53
 */

namespace bamboo\ecommerce\offline\productsync\import;


interface IBluesealProductImporter {

    public function run($args = null);
    public function fetchShop($args);
    public function readConfig();
    public function fetchFiles();
    public function readFile($file);
    public function processFile($file);
    public function saveFile($file, $isGood);
    public function updateDictionaries();
    public function createProducts();
    public function fetchPhotos();
    public function sendPhotos();

}