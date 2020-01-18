<?php

namespace bamboo\domain\repositories;

use bamboo\core\db\pandaorm\entities\IEntity;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CCampaignVisit;
use bamboo\domain\entities\CMarketplace;
use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CMarketplaceAccountHasProduct;
use bamboo\domain\entities\CProduct;
use bamboo\domain\Entities\CProductSku;
use bamboo\domain\repositories\CProductSkuRepo;

/**
 * Class CMarketplaceAccountHasProductRepo
 * @package bamboo\domain\repositories
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CMarketplaceAccountHasProductRepo extends ARepo
{

    public function addProductToMarketplaceAccount(CProduct $product, CMarketplaceAccount $marketplaceAccount, $cpc = null, $priceModifier = null, $activeAutomatic = null)
    {
        $config = $marketplaceAccount -> config;
        if (!is_null($cpc)) {
            $config['cpc'] = $cpc;
        }
        if (!is_null($priceModifier)) {
            $config['priceModifier'] = $priceModifier;
        }
        if ($activeAutomatic == '0') {

            $marketplaceAccountHasProduct = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> getEmptyEntity();
            $marketplaceAccountHasProduct -> productId = $product -> id;
            $marketplaceAccountHasProduct -> productVariantId = $product -> productVariantId;
            $marketplaceAccountHasProduct -> marketplaceAccountId = $marketplaceAccount -> id;
            $marketplaceAccountHasProduct -> marketplaceId = $marketplaceAccount -> marketplaceId;

            if ($marketplaceAccountHasProduct2 = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> findOneBy($marketplaceAccountHasProduct -> getIds())) {
                $marketplaceAccountHasProduct = $marketplaceAccountHasProduct2;

                $marketplaceAccountHasProduct -> priceModifier = $config['priceModifier'];
                if ($marketplaceAccount -> marketplace -> type == 'cpc') {
                    $marketplaceAccountHasProduct -> fee = $config['defaultCpcF'];
                    $marketplaceAccountHasProduct->feeMobile=$config['defaultCpcFM'];
                    $marketplaceAccountHasProduct -> feeCustomer = $config['defaultCpc'];
                    $marketplaceAccountHasProduct->feeCustomerMobile=$config['defaultCpcM'];
                }

                if ($marketplaceAccountHasProduct -> isDeleted) {
                    $marketplaceAccountHasProduct -> isDeleted = 0;
                    $marketplaceAccountHasProduct -> isToWork = 1;
                    $marketplaceAccountHasProduct -> update();
                    //reinsert
                    $this -> app -> eventManager -> triggerEvent('marketplace.product.add', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);

                } else {
                    $this -> app -> eventManager -> triggerEvent('product.marketplace.change', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
                }
            } else {
                //insert
                $marketplaceAccountHasProduct -> insert();
                $this -> app -> eventManager -> triggerEvent('marketplace.product.add', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
            }
        } else {
            $isOnSale = $product -> isOnSale;
            $productSku = \Monkey ::app() -> repoFactory -> create('ProductSku') -> findOneBy(['productId' => $product -> id, 'productVariantId' => $product -> productVariantId]);
            $price = $productSku -> price;
            $salePrice = $productSku -> salePrice;
            if ($isOnSale == 1) {
                $activePrice = $salePrice;
            } else {
                $activePrice = $price;
            }

            $priceRange1=explode('-',$config['priceModifierRange1']);
            $priceRange2=explode('-',$config['priceModifierRange2']);
            $priceRange3=explode('-',$config['priceModifierRange3']);
            $priceRange4=explode('-',$config['priceModifierRange4']);
            $priceRange5=explode('-',$config['priceModifierRange5']);

            switch(true){
                case $activePrice>=$priceRange1[0] && $activePrice<=$priceRange1[1]:
                    $fee=$config['range1Cpc'];
                    $feeMobile=$config['range1CpcM'];
                    $priceModifier=$config['valueexcept1'];

                    break;
                case $activePrice>=$priceRange2[0] && $activePrice<=$priceRange2[1]:
                    $fee=$config['range2Cpc'];
                    $feeMobile=$config['range2CpcM'];
                    $priceModifier=$config['valueexcept2'];
                    break;
                case $activePrice>=$priceRange3[0] && $activePrice<=$priceRange3[1]:
                    $fee=$config['range3Cpc'];
                    $feeMobile=$config['range3CpcM'];
                    $priceModifier=$config['valueexcept3'];
                    break;
                case $activePrice>=$priceRange4[0] && $activePrice<=$priceRange4[1]:
                    $fee=$config['range4Cpc'];
                    $feeMobile=$config['range4CpcM'];
                    $priceModifier=$config['valueexcept4'];
                    break;
                case $activePrice>=$priceRange5[0] && $activePrice<=$priceRange5[1]:
                    $fee=$config['range5Cpc'];
                    $feeMobile=$config['range5CpcM'];
                    $priceModifier=$config['valueexcept5'];
                    break;
            }
            $marketplaceAccountHasProduct = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> getEmptyEntity();
            $marketplaceAccountHasProduct -> productId = $product -> id;
            $marketplaceAccountHasProduct -> productVariantId = $product -> productVariantId;
            $marketplaceAccountHasProduct -> marketplaceAccountId = $marketplaceAccount -> id;
            $marketplaceAccountHasProduct -> marketplaceId = $marketplaceAccount -> marketplaceId;

            if ($marketplaceAccountHasProduct2 = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> findOneBy($marketplaceAccountHasProduct -> getIds())) {
                $marketplaceAccountHasProduct = $marketplaceAccountHasProduct2;

                $marketplaceAccountHasProduct -> priceModifier = $priceModifier;
                if ($marketplaceAccount -> marketplace -> type == 'cpc') {
                    $marketplaceAccountHasProduct -> fee = $fee;
                    $marketplaceAccountHasProduct->feeMobile=$feeMobile;
                    $markketplaceAccountHasProduct->feeCustomer=$config['defaultCpc'];
                    $marketplaceAccountHasProduct->feeCustomerMobile=$config['defaultCpcM'];
                }

                if ($marketplaceAccountHasProduct -> isDeleted) {
                    $marketplaceAccountHasProduct -> isDeleted = 0;
                    $marketplaceAccountHasProduct -> isToWork = 1;
                    $marketplaceAccountHasProduct -> update();
                    //reinsert
                    $this -> app -> eventManager -> triggerEvent('marketplace.product.add', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);

                } else {
                    $this -> app -> eventManager -> triggerEvent('product.marketplace.change', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
                }
            } else {
                //insert
                $marketplaceAccountHasProduct -> insert();
                $this -> app -> eventManager -> triggerEvent('marketplace.product.add', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
            }

        }
    }
    public function addProductToMarketplaceAccountJob(CProduct $product, CMarketplaceAccount $marketplaceAccount, $activeAutomatic = null)
    {
        $config = $marketplaceAccount -> config;





        if ($activeAutomatic == '0') {

            $marketplaceAccountHasProduct = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> getEmptyEntity();
            $marketplaceAccountHasProduct -> productId = $product -> id;
            $marketplaceAccountHasProduct -> productVariantId = $product -> productVariantId;
            $marketplaceAccountHasProduct -> marketplaceAccountId = $marketplaceAccount -> id;
            $marketplaceAccountHasProduct -> marketplaceId = $marketplaceAccount -> marketplaceId;

            if ($marketplaceAccountHasProduct2 = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> findOneBy($marketplaceAccountHasProduct -> getIds())) {
                $marketplaceAccountHasProduct = $marketplaceAccountHasProduct2;


                if ($marketplaceAccount -> marketplace -> type == 'cpc') {
                    $marketplaceAccountHasProduct -> fee = $config['defaultCpcF'];
                    $marketplaceAccountHasProduct->feeMobile=$config['defaultCpcFM'];
                    $markketplaceAccountHasProduct->feeCustomer=$config['defaultCpc'];
                    $marketplaceAccountHasProduct->feeCustomerMobile=$config['defaultCpcM'];
                }

                if ($marketplaceAccountHasProduct -> isDeleted) {
                    $marketplaceAccountHasProduct -> isDeleted = 0;
                    $marketplaceAccountHasProduct -> isToWork = 1;
                    $marketplaceAccountHasProduct -> update();
                    //reinsert
                    $this -> app -> eventManager -> triggerEvent('marketplace.product.add', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);

                } else {
                    $this -> app -> eventManager -> triggerEvent('product.marketplace.change', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
                }
            } else {
                //insert
                $marketplaceAccountHasProduct -> insert();
                $this -> app -> eventManager -> triggerEvent('marketplace.product.add', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
            }
        } else {
            $isOnSale = $product -> isOnSale;
            $productSku = \Monkey ::app() -> repoFactory -> create('ProductSku') -> findOneBy(['productId' => $product -> id, 'productVariantId' => $product -> productVariantId]);
            $price = $productSku -> price;
            $salePrice = $productSku -> salePrice;
            if ($isOnSale == 1) {
                $activePrice = $salePrice;
            } else {
                $activePrice = $price;
            }

            $priceRange1=explode('-',$config['priceModifierRange1']);
            $priceRange2=explode('-',$config['priceModifierRange2']);
            $priceRange3=explode('-',$config['priceModifierRange3']);
            $priceRange4=explode('-',$config['priceModifierRange4']);
            $priceRange5=explode('-',$config['priceModifierRange5']);


            switch(true){
                case $activePrice>=$priceRange1[0] && $activePrice<=$priceRange1[1]:
                    $fee=$config['range1Cpc'];
                    $feeMobile=$config['range1CpcM'];
                    $priceModifier=$config['valueexcept1'];

                    break;
                case $activePrice>=$priceRange2[0] && $activePrice<=$priceRange2[1]:
                    $fee=$config['range2Cpc'];
                    $feeMobile=$config['range2CpcM'];
                    $priceModifier=$config['valueexcept2'];
                    break;
                case $activePrice>=$priceRange3[0] && $activePrice<=$priceRange3[1]:
                    $fee=$config['range3Cpc'];
                    $feeMobile=$config['range3CpcM'];
                    $priceModifier=$config['valueexcept3'];
                    break;
                case $activePrice>=$priceRange4[0] && $activePrice<=$priceRange4[1]:
                    $fee=$config['range4Cpc'];
                    $feeMobile=$config['range4CpcM'];
                    $priceModifier=$config['valueexcept4'];
                    break;
                case $activePrice>=$priceRange5[0] && $activePrice<=$priceRange5[1]:
                    $fee=$config['range5Cpc'];
                    $feeMobile=$config['range5CpcM'];
                    $priceModifier=$config['valueexcept5'];
                    break;
            }
            $marketplaceAccountHasProduct = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> getEmptyEntity();
            $marketplaceAccountHasProduct -> productId = $product -> id;
            $marketplaceAccountHasProduct -> productVariantId = $product -> productVariantId;
            $marketplaceAccountHasProduct -> marketplaceAccountId = $marketplaceAccount -> id;
            $marketplaceAccountHasProduct -> marketplaceId = $marketplaceAccount -> marketplaceId;

            if ($marketplaceAccountHasProduct2 = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> findOneBy($marketplaceAccountHasProduct -> getIds())) {
                $marketplaceAccountHasProduct = $marketplaceAccountHasProduct2;


                if ($marketplaceAccount -> marketplace -> type == 'cpc') {
                    $marketplaceAccountHasProduct -> fee = $fee;
                    $marketplaceAccountHasProduct -> feeMobile = $feeMobile;
                    $markketplaceAccountHasProduct->feeCustomer=$config['defaultCpc'];
                    $marketplaceAccountHasProduct->feeCustomerMobile=$config['defaultCpcM'];
                }

                if ($marketplaceAccountHasProduct -> isDeleted) {
                    $marketplaceAccountHasProduct -> isDeleted = 0;
                    $marketplaceAccountHasProduct -> isToWork = 1;
                    $marketplaceAccountHasProduct -> update();
                    //reinsert
                    $this -> app -> eventManager -> triggerEvent('marketplace.product.add', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);

                } else {
                    $this -> app -> eventManager -> triggerEvent('product.marketplace.change', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
                }
            } else {
                //insert
                $marketplaceAccountHasProduct -> insert();
                $this -> app -> eventManager -> triggerEvent('marketplace.product.add', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
            }

        }
    }

    /**
     * @param string|CMarketplaceAccountHasProduct $marketplaceAccountHasProduct
     * @return bool
     */
    public function deleteProductFromMarketplaceAccount($marketplaceAccountHasProduct)
    {
        try {
            if (!($marketplaceAccountHasProduct instanceof CMarketplaceAccountHasProduct)) {
                $stringId = $marketplaceAccountHasProduct;
                $marketplaceAccountHasProduct = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> findOneByStringId($stringId);
            }

            if (null == $marketplaceAccountHasProduct) {
                $marketplaceAccountHasProduct = \Monkey ::app() -> repoFactory -> create('MarketplaceAccountHasProduct') -> getEmptyEntity();
                $marketplaceAccountHasProduct -> readId($stringId);
                $marketplaceAccountHasProduct -> isDeleted = 1;
                $marketplaceAccountHasProduct -> isRevised = 0;
                $marketplaceAccountHasProduct -> isToWork = 0;
                $marketplaceAccountHasProduct -> insert();
            } else {
                $marketplaceAccountHasProduct -> isRevised = 0;
                $marketplaceAccountHasProduct -> isDeleted = 1;
                $marketplaceAccountHasProduct -> update();
            }
            $this -> app -> eventManager -> triggerEvent('product.marketplace.change', ['marketplaceAccountHasProductId' => $marketplaceAccountHasProduct -> printId()]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * read the flags of $marketplaceAccountHasProduct and returns the action, null if no action is needed,
     * also corrects the wrong flag configurations if they occurs
     * @param CMarketplaceAccountHasProduct $marketplaceAccountHasProduct
     * @return null|string
     */
    public function detectAction(CMarketplaceAccountHasProduct $marketplaceAccountHasProduct)
    {
        if (!$marketplaceAccountHasProduct -> isToWork && !$marketplaceAccountHasProduct -> isDeleted) {
            foreach ($marketplaceAccountHasProduct -> product -> productSku as $productSku) {
                if ($productSku -> stockQty > 0) return 'revise';
            }
            $marketplaceAccountHasProduct -> isDeleted = 1;
            $marketplaceAccountHasProduct -> update();
            return 'end';
        } elseif (!$marketplaceAccountHasProduct -> isToWork &&
            !$marketplaceAccountHasProduct -> isRevised &&
            $marketplaceAccountHasProduct -> isDeleted
        ) return 'end';
        elseif ($marketplaceAccountHasProduct -> isToWork &&
            !$marketplaceAccountHasProduct -> isRevised &&
            !$marketplaceAccountHasProduct -> isDeleted
        ) return 'add';
        elseif (!$marketplaceAccountHasProduct -> isToWork &&
            $marketplaceAccountHasProduct -> isRevised &&
            $marketplaceAccountHasProduct -> isDeleted &&
            !$marketplaceAccountHasProduct -> hasError
        ) return null;

        $marketplaceAccountHasProduct -> isToWork = 0;
        $marketplaceAccountHasProduct -> isRevised = 1;
        $marketplaceAccountHasProduct -> isDeleted = 1;
        $marketplaceAccountHasProduct -> hasError = 0;
        $marketplaceAccountHasProduct -> update();
        return null;
    }
}