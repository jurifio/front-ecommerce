<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\domain\entities\CProductDetail;
use bamboo\domain\entities\CProductDetailLabel;

/**
 * Class CProductSheetActual
 * @package bamboo\app\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 * @property CProductDetailLabel $productDetailLabel
 * @property CProductDetail $productDetail
 * @property CProductDetailsHasPrestashopFeatures $productDetailsHasPrestashopFeatures
 */
class CProductSheetActual extends AEntity
{
    protected $entityTable = 'ProductSheetActual';
    protected $primaryKeys = ['productId','productVariantId','productDetailLabelId'];
}