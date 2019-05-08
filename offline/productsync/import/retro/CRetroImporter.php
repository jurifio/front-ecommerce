<?php

namespace bamboo\offline\productsync\import\retro;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooFileException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\offline\productsync\import\standard\ABluesealXMLProductImporter;


/**
 * Class CRetroImporter
 * @package bamboo\htdocs\pickyshop\import\productsync\retro
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, 03/12/2015
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class CRetroImporter extends ABluesealXMLProductImporter
{
    /**
     * @param $file
     * @return bool
     * @throws BambooFileException
     * @throws BambooOutOfBoundException
     */
    public function readFile($file)
    {
        try {
            $this->report('readFile', 'translating to json: ' . $file);
            $content = file_get_contents($file);
            if(empty($content)) throw new BambooFileException('File di input vuoto: '.$file);
            $xml = simplexml_load_string($content);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);
        } catch (\Throwable $e) {
            $this->error('readFile', 'error while translating to json', $e);
            throw new $e;
        }

        $emptyCpf = [];
        $emptyColor = [];
        $emptyBrand = [];

        foreach ($array['feed']['items']['item'] as $item) {
            if (!isset($item['brand']) || empty(trim($item['brand']))) {
                $emptyBrand[] = $item['variants'][0]['extId'];
            }
            foreach ($item['variants'] as $variant) {
                if (!isset($variant['cpf']) || empty($variant['cpf']) || empty(trim($variant['cpf']))) {
                    $emptyCpf[] = $variant['extId'];
                }
                if (!isset($variant['brandColor']) || empty($variant['brandColor']) || empty(trim($variant['brandColor']))) {
                    $emptyColor[] = $variant['extId'];
                }
            }

        }

        if (!(empty($emptyCpf) &&
            empty($emptyColor) &&
            empty($emptyBrand))
        ) {
            $text = "Si è verificato un errore durante l'importazione dei prodotti in pickyshop, verificare i dati per i seguenti prodotti: \n" .
                (empty($emptyCpf) ? "" : "Prodotti a cui manca il Codice Prodotto Fornitore: \n" . implode("\n", $emptyCpf) . "\n") .
                (empty($emptyColor) ? "" : "Prodotti a cui manca il Colore Fornitore: \n" . implode("\n", $emptyColor) . "\n") .
                (empty($emptyBrand) ? "" : "Prodotti a cui manca il Brand: \n" . implode("\n", $emptyBrand) . "\n");
            iwesMail($this->getShop()->referrerEmails,
                'Errore importazione Prodotti Pickyshop ' . $this->getShop()->name,
                $text

            );

            throw new BambooFileException('Incomplete file input');
        }
        return true;
        //return parent::readFile($file);
    }

    /**
     * @param $file
     */
    public function processFile($file) {
        $doc = new \DOMDocument();
        $content = file_get_contents($file);
        $doc->loadXML($content);
        $this->set($doc);
    }
}