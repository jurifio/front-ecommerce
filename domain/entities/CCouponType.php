<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CCouponType
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
 *
 * @property CObjectCollection $couponTypeHasTag
 */
class CCouponType extends AEntity
{
    protected $entityTable = 'CouponType';
    protected $primaryKeys = ['id'];

    /**
     * @return array
     */
    public function getValidTagIds() {
        $res = [];
        foreach ($this->couponTypeHasTag as $couponTypeHasTag) {
            $res[] = $couponTypeHasTag->tagId;
        }
        return $res;
    }
}