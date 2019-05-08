<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CUserPreferences
 * @package bamboo\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 13/03/2016
 * @since 1.0
 */
class CUserPreferences extends AEntity
{
    protected $entityTable = 'UserPreferences';
    protected $primaryKeys = ['userId'];
}