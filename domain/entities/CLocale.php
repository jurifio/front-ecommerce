<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCountry
 * @package bamboo\app\domain\entities
 */
class CLocale extends AEntity
{
    protected $entityTable = 'Locale';
    protected $primaryKeys = ['langId','currencyId','countryId'];
}