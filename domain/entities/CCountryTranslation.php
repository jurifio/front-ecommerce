<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCountryTranslation
 * @package bamboo\app\domain\entities
 */
class CCountryTranslation extends AEntity
{
    protected $entityTable = 'CountryTranslation';
    protected $primaryKeys = ['countryId','langId'];
}