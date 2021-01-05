<?php


namespace bamboo\domain\repositories;
use bamboo\core\base\CObjectCollection;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOrderLineException;
use bamboo\core\exceptions\RedPandaException;
use bamboo\domain\entities\CAddressBook;
use bamboo\domain\entities\COrderLine;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\COrderLineStatus;
use bamboo\domain\entities\CPrestashopHasProduct;
use bamboo\domain\entities\CMarketplaceHasProductAssociate;
use bamboo\domain\entities\CPrestashopHasProductHasMarketplaceHasShop;
use bamboo\domain\entities\CUserAddress;

/**
 * Class CPrestashopHasProductRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 13/09/2018
 * @since 1.0
 */
class CPrestashopHasProductRepo extends ARepo
{
    /**
     * @param int $productId
     * @param int $productVariantId
     * @param int $status
     * @return CPrestashopHasProduct
     * @throws BambooException
     * @throws \bamboo\core\exceptions\BambooORMInvalidEntityException
     * @throws \bamboo\core\exceptions\BambooORMReadOnlyException
     */
    public function updateProductStatus(int $productId, int $productVariantId, $status = 2) : CPrestashopHasProduct{

        /** @var CPrestashopHasProduct $prestashopHasProduct */
        $prestashopHasProduct = $this->findOneBy(
            [
                'productId' => $productId,
                'productVariantId' => $productVariantId
            ]);

        $prestashopHasProduct->status = $status;
        $prestashopHasProduct->update();

        return $prestashopHasProduct;
    }
}