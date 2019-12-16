<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;


/**
 * Class CCouponHasNewsletterUser
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 16/12/2019
 * @since 1.0
 */

class CCouponHasNewsletterUser extends AEntity
{

    protected $entityTable = 'CouponHasNewsletterUser';
    protected $primaryKeys = ['id'];

}