<?php

namespace bamboo\domain\entities;

use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;
use bamboo\core\db\pandaorm\repositories\CRepo;
use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooOutOfBoundException;
use bamboo\core\utils\slugify\CSlugify;
use bamboo\domain\repositories\CDocumentRepo;
use bamboo\domain\repositories\CProductHistoryRepo;

/**
 * Class CProduct
 * @package bamboo\domain\entities
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 01/11/2014
 * @since 0.0.1
 *
 * @property CProductBrand $productBrand
 * @property CProductSeason $productSeason
 * @property CProductVariant $productVariant
 * @property CObjectCollection $productSheetActual
 * @property CProductSheetPrototype $productSheetPrototype
 * @property CObjectCollection $productSku
 * @property CObjectCollection $productPublicSku
 * @property CObjectCollection $productPhoto
 * @property CProductColorGroup $productColorGroup
 * @property CObjectCollection $productCategoryTranslation
 * @property CObjectCollection $productCategory
 * @property CObjectCollection $shop
 * @property CObjectCollection $productNameTranslation
 * @property CProductSizeGroup $productSizeGroup
 * @property CObjectCollection $tag
 *  * @property CObjectCollection $tagExclusive
 * @property CProductStatus $productStatus
 * @property CObjectCollection $shopHasProduct
 * @property CObjectCollection $productDescriptionTranslation
 * @property CObjectCollection $marketplaceAccountHasProduct
 * @property CObjectCollection $cartLine
 * @property CObjectCollection $orderLine
 * @property CObjectCollection $shooting
 * @property CProductCardPhoto $productCardPhoto
 * @property CObjectCollection $productHasTag
 * @property CObjectCollection $productEan
 * @property CObjectCollection $productView
 * @property CPrestashopHasProduct $prestashopHasProduct
 * @property CProductBrandHasPrestashopManufacturer $productBrandHasPrestashopManufacturer
 * @property CProductColorGroupHasPrestashopColorOption $productColorGroupHasPrestashopColorOption
 *
 *
 */
class CProduct extends AEntity
{
    protected $entityTable = 'Product';
    protected $primaryKeys = ['id', 'productVariantId'];

    /**
     * @param $number
     * @param $size
     * @return string
     */
    public function getPhoto($number, $size)
    {
        if (!$this->hasData()) {
            return "no data";
        }
        $photo = \Monkey::app()->repoFactory->create('ProductPhoto')->getPhotoForProductSizeOrder($this, $size, $number);
        if ($photo) return $this->productBrand->slug . '/' . $photo->name;
        return "";
    }

    /**
     * @param CMarketplaceAccount $marketplaceAccount
     * @return array
     * @throws BambooOutOfBoundException
     */
    public function getMarketplaceAccountCategoryIds(CMarketplaceAccount $marketplaceAccount)
    {
        $mCategoryIds = [];
        $categories = [];
        foreach ($this->productCategory as $category) {
            $categories[] = $category->id;
            foreach ($category->marketplaceAccountCategory->findByKeys(['marketplaceId' => $marketplaceAccount->marketplaceId, 'marketplaceAccountId' => $marketplaceAccount->id, 'isRelevant' => 1]) as $mCategory) {
                $mCategoryIds[] = $mCategory->marketplaceCategoryId;
            }
        }
        if (count($mCategoryIds) == 0) throw new BambooOutOfBoundException('Could not find Marketplace Category for product starting with categories: ' . implode(',', $categories));
        return $mCategoryIds;
    }
    /**
     * @param CMarketplaceAccount $marketplaceAccount
     * @return array
     * @throws BambooOutOfBoundException
     */
    public function getMarketplaceAccountCategoryIdsFacebook(CMarketplaceAccount $marketplaceAccount)
    {
        $mCategoryIds = [];
        $categories = [];
        foreach ($this->productCategory as $category) {
            $categories[] = $category->id;
            /*foreach ($category->marketplaceAccountCategory->findByKeys(['marketplaceId' => $marketplaceAccount->marketplaceId, 'marketplaceAccountId' => $marketplaceAccount->id, 'isRelevant' => 1]) as $mCategory) {*/
                $mCategoryIds[] = 167;
           /* }*/
        }
        if (count($mCategoryIds) == 0) throw new BambooOutOfBoundException('Could not find Marketplace Category for product starting with categories: ' . implode(',', $categories));
        return $mCategoryIds;
    }
    public function getMarketplaceAccountCategoryNames(CMarketplaceAccount $marketplaceAccount)
    {
        $mCategoryNames = [];
        $categories = [];
        foreach ($this->productCategory as $category) {
            $categories[] = $category->id;
            foreach ($category->marketplaceAccountCategory->findByKeys(['marketplaceId' => $marketplaceAccount->marketplaceId, 'marketplaceAccountId' => $marketplaceAccount->id, 'isRelevant' => 1]) as $mCategory) {
                $mCategoryNames[] = $mCategory->name;
            }
        }
        if (count($mCategoryNames) == 0) throw new BambooOutOfBoundException('Could not find Marketplace Category for product starting with categories: ' . implode(',', $categories));
        return $mCategoryNames;
    }

    /**
     * @return string
     */
    public function getAztecCode()
    {
        $s = new CSlugify();
        return $this->id . '-' . $this->productVariantId . '__' . $this->productBrand->slug . ' - ' . $s->slugify($this->itemno) . ' - ' . $s->slugify($this->productVariant->name);
    }

    /**
     * @param string $separator
     * @return string
     */
    public function printCpf($separator = ' # ')
    {
        return $this->itemno . $separator . $this->productVariant->name;
    }

    /**
     * @param $baseUrlLang
     * @param null $campaign
     * @return string
     */
    public function getProductUrl($baseUrlLang = null, $campaign = null)
    {
        if (is_null($baseUrlLang)) $baseUrlLang = \Monkey::app()->baseUrl();
        $slugify = new CSlugify();
        $campaign = is_null($campaign) ? "" : "?utm_marketing_data[]=" . $campaign;
        //$urlLang=str_replace('http://www.iwes.pro','https://www.pickyshop.com',$baseUrlLang);

        return $baseUrlLang . "/" . $this->productBrand->slug . "/cpf/" . $slugify->slugify($this->itemno) . "/p/" . $this->id . "/v/" . $this->productVariantId . $campaign;

    }

    /**
     * @return string
     */
    public function getEbayName()
    {
        if (!is_null($this->productNameTranslation->getFirst()) &&
            !empty($this->productNameTranslation->getFirst()->name)
        ) {
            return $this->productBrand->name . " " . $this->productNameTranslation->getFirst()->name;
        } else {
            return $this->productBrand->name . " " . $this->itemno . " " . $this->productVariant->name . " " . ($this->productColorGroup ? $this->productColorGroup->productColorGroupTranslation->getFirst()->name : "");
        }
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        if (!is_null($this->productDescriptionTranslation->getFirst()) &&
            !empty(strip_tags($this->productDescriptionTranslation->getFirst()->description))
        ) {
            return $this->productDescriptionTranslation->getFirst()->description;
        } else {
            return $this->productBrand->name . " " .
                $this->getName() . " " .
                $this->itemno . " " .
                $this->productVariant->name . " " .
                ($this->productColorGroup ? $this->productColorGroup->productColorGroupTranslation->getFirst()->name : "");
        }

    }

    /**
     * @return mixed|string
     */
    public function getName()
    {
        return !is_null($this->productNameTranslation->getFirst()) &&
        !empty($this->productNameTranslation->getFirst()->name) ? $this->productNameTranslation->getFirst()->name :
            $this->itemno . ' ' . $this->productVariant->name;
    }

    /**
     * @return bool|float
     */
    public function getDisplayPrice()
    {
        $price = false;
        foreach ($this->productPublicSku as $sku) {
            if (0 < $sku->stockQty && 0 < $sku->price && ($sku->price < $price || $price === false)) {
                $price = $sku->price;
            }
        }
        return $price;
    }

    /**
     * @return CObjectCollection
     */
    public function getVariants()
    {
        return \Monkey::app()->repoFactory->create('Product')->getVariantsOfProduct($this->id);
    }

    /**
     * @return bool|float
     */
    public function getDisplaySalePrice()
    {
        $salePrice = false;
        foreach ($this->productPublicSku as $sku) {
            if (0 < $sku->stockQty && 0 < $sku->salePrice && ($sku->salePrice < $salePrice || $salePrice === false)) {
                $salePrice = $sku->salePrice;
            }
        }
        return $salePrice;
    }

    /**
     * @return bool|float
     */
    public function getDisplayActivePrice()
    {
        $price = false;
        foreach ($this->productPublicSku as $sku) {
            if (0 < $sku->stockQty) {
                if ($this->isOnSale() && 0 < $sku->salePrice && ($sku->salePrice < $price || $price === false)) $price = $sku->salePrice;
                elseif (0 < $sku->price && ($sku->price < $price || $price === false)) $price = $sku->price;
            }
        }
        return $price;
    }
    public function getDisplayFullPrice()
    {
       //price full
        $price = false;
        foreach ($this->productPublicSku as $sku) {
            if (0 < $sku->stockQty) {

                if (0 < $sku->price && ($sku->price < $price || $price === false)) $price = $sku->price;
            }
        }
        return $price;
    }

    /**
     * @return bool
     */
    public function isOnSale()
    {
        return $this->isOnSale == 1;
    }

    /**
     * @return string
     */
    public function getGender()
    {
        return implode(",", $this->getGendersId());
    }

    /**
     * @return array
     */
    public function getGendersId()
    {
        return array_keys($this->getGendersCategory());
    }

    /**
     * @return array
     */
    public function getGendersCategory()
    {
        $genders = [];
        foreach ($this->productCategory as $category) {
            $genders[$category->getGenderCategory()->printId()] = $category->getGenderCategory();
        }
        return $genders;
    }

    /**
     * @return array
     */
    public function getGendersName()
    {
        $names = [];
        foreach ($this->getGendersCategory() as $category) {
            /** @var CProductCategory $category */
            $names[] = $category->getLocalizedName();
        }
        return array_unique($names);
    }

    /**
     * @param string $separator1
     * @param string $separator2
     * @return string
     */
    public function getLocalizedProductCategories($separator1 = " ", $separator2 = "/")
    {
        $categories = [];
        foreach ($this->productCategory as $category) {
            $categories[] = $category->getLocalizedPath($separator2);
        }

        return implode($separator1, $categories);
    }

    public function update()
    {
        if (is_null($this->em())) {
            throw new BambooException('Classic should never happen exception, updating on ' . $this->entityTable);
        }

        if ($this->productSeasonId != $this->originalFields['productSeasonId']) { //MAGIC TRICK! (∩｀-´)⊃━☆ﾟ.*･｡ﾟ This line get if the season is updated from an html form
            $dba = \Monkey::app()->dbAdapter;
            $pR = \Monkey::app()->repoFactory->create('Product');
            $res = $dba->query("SELECT * FROM Product WHERE id = ? AND productVariantId = ? ", [$this->id, $this->productVariantId])->fetch();
            if ($this->productSeasonId != $res['productSeasonId']) {
                $pR->createMovementsOnChangingSeason($this->printId(), $this->productSeasonId);
            }
        }
        if ($this->productSizeGroupId != $this->originalFields['productSizeGroupId']) {
            $this->writeHistory(
                'Changed ProductSizeGroup',
                'Changed from ' . $this->originalFields['productSizeGroupId'] . ' to: ' . $this->productSizeGroupId);
        }
        return $this->em()->update($this);
    }

    public function writeHistory($action, $description = null, $userId = null)
    {
        try {
            if (isset($this->id) && isset($this->productVariantId)) {
                if ($userId === null) $userId = \Monkey::app()->getUser()->id;
                /** @var CProductHistoryRepo $historyRepo */
                $historyRepo = \Monkey::app()->repoFactory->create('ProductHistory');
                $historyRepo->add(
                    $this->id,
                    $this->productVariantId,
                    $userId,
                    $action,
                    $description
                );
            }
        } catch (\Throwable $e) {
            \Monkey::app()->applicationWarning('Product', 'writeHistory', 'Error while updating history', $e);
        }
    }

    /**
     * @param string $separator1
     * @param bool $onlyPublics
     * @return string
     */
    public function getLocalizedTags($separator1 = " ", $onlyPublics = true)
    {
        $tags = [];
        foreach ($this->tag as $tag) {
            if ($onlyPublics && $tag->isPublic == 0) continue;
            $tags[] = $tag->getLocalizedName();
        }
        return implode($separator1, $tags);
    }
    /**
     * @param string $separator1
     * @param bool $onlyPublics
     * @return string
     */
    public function getLocalizedTagsExclusive($separator1 = " ", $onlyPublics = true)
    {
        $tags = [];
        foreach ($this->tagExclusive as $tag) {
            if ($onlyPublics && $tag->isPublic == 0) continue;
            $tags[] = $tag->getLocalizedName();
        }
        return implode($separator1, $tags);
    }

    /**
 * @param string $separator
 * @return string
 */
    public function getShops($separator = ", ", $getIds = false)
    {
        $shops = [];
        foreach ($this->shopHasProduct as $shopHasProduct) {
            $single = '';
            if ($getIds) $single .= $shopHasProduct->shop->id . '-';
            $single .= $shopHasProduct->shop->name;
            $shops[] = $single;
        }
        return implode($separator, $shops);
    }
    /**
     * @param string $separator
     * @return string
     */
    public function getShopsIdOrigin($separator = ", ", $getIds = false)
    {
        $shops = [];
        foreach ($this->shopHasProduct as $shopHasProduct) {
            $single = '';
            if ($getIds) $single .= $shopHasProduct->shop->id . '-';
            $single .= $shopHasProduct->shop->name;
            $shops[] = $single;
        }
        return implode($separator, $shops);
    }
    public function getShopsIddestination($separator = ", ", $getIds = false)
    {
        $shops = [];
        foreach ($this->shopHasProduct as $shopHasProduct) {
            $single = '';
            if ($getIds) $single .= $shopHasProduct->shop->id . '-';
            $single .= $shopHasProduct->shop->name;
            $shops[] = $single;
        }
        return implode($separator, $shops);
    }


    /**
     * @param null $separator
     * @return array|string
     */
    public function getShopExtenalIds($separator = null)
    {
        foreach ($this->shopHasProduct as $shp) {
            if (!empty($shp->extId)) {
                $ext[] = trim($shp->extId);
            }
            foreach ($shp->dirtyProduct as $dirtyProduct) {
                if (!empty($dirtyProduct->extId)) {
                    $ext[] = trim($dirtyProduct->extId);
                }
                foreach ($dirtyProduct->dirtySku as $sku) {
                    if (!empty($sku->extSkuId)) {
                        $ext[] = trim($sku->extSkuId);
                    }
                }
            }
        }
        $ext[] = $this->externalId;
        $ext = array_unique($ext);
        if ($separator != null) {
            return implode($separator, $ext);
        } else return $ext;
    }

    /**
     * @param $id
     * @throws \Exception
     */
    public function setProductStatusId($id)
    {
        if (!isset($this->fields['productStatusId'])) {
            $this->fields['productStatusId'] = $id;
        } else {
            switch ($id) {
                case 6:
                    $this->sendPublish();
                    break;
                case 8;
                    $this->sendCancel();
                    break;
            }
            $this->fields['productStatusId'] = $id;
        }
    }

    /**
     *
     */
    public function updatePublicSkus() {
        \Monkey::app()->repoFactory->create('Product')->updatePublicSkus($this);
    }

    /**
     * @throws \Exception
     */
    private function sendPublish()
    {
        if (is_null($this->productPhoto) || $this->productPhoto->isEmpty() ||
            is_null($this->productSku) || $this->productSku->isEmpty()) {
            throw new \Exception('Il prodotto non può passare in "Pubblicato". Foto o Sku mancanti');
        }
    }

    private function sendCancel()
    {
        \Monkey::app()->eventManager->triggerEvent('product.cancel', ['productId' => $this->printId()]);
    }

    /**
     * @return string
     */
    public function getDummyPictureUrl()
    {
        $baseURL = \Monkey::app()->cfg()->fetch('paths', 'dummyUrl');
        $remoteURL = \Monkey::app()->cfg()->fetch('general', 'product-photo-host');
        $dummyUrl = (false !== strpos($this->fields['dummyPicture'], $remoteURL)) ? $this->fields['dummyPicture'] : $baseURL . "/" . $this->fields['dummyPicture'];
        return $dummyUrl = str_replace('http://', 'https://', $dummyUrl);
    }

    /**
 * @param string $separator1
 * @param string $separator2
 * @return string
 */
    public function getMarketplaceAccountsName($separator1 = " - ", $separator2 = ", ", $excludeDeleted = false)
    {
        $data = [];
        foreach ($this->marketplaceAccountHasProduct as $marketplaceLine) {
            if ($excludeDeleted && $marketplaceLine->isDeleted == 1) continue;
            $data[] = $separator1 . $marketplaceLine->marketplaceAccount->name;
        }
        return implode($separator2, $data);
    }
    /**
     * @param string $separator1
     * @param string $separator2
     * @return string
     */
    public function getMarketplaceAccountsNameShopDestination($separator1 = " - ", $separator2 = ", ", $excludeDeleted = false)
    {
        $data = [];
        foreach ($this->marketplaceAccountHasProduct as $marketplaceLine) {
            if ($excludeDeleted && $marketplaceLine->isDeleted == 1) continue;
            $data[] =  $separator1 . $marketplaceLine->marketplaceAccount->name;
        }
        return implode($separator2, $data);
    }
    /**
     * @param string $separator1
     * @param string $separator2
     * @return string
     */
    public function getShopIdOriginName($separator1 = " - ", $separator2 = ",", $excludeDeleted = false)
    {
        $data = [];
        foreach ($this->shop as $shopLine) {
            if ($excludeDeleted && $shopLine->isActive == 0) continue;
            $data[] = $shopLine->id . $separator1 . $shopLine->name;
        }
        return implode($separator2, $data);
    }

    /**
     * @param array $shopIds
     * @return array
     */
    public function getStockSituationTable(array $shopIds)
    {
        $object = [];
        $object['head'][0] = 'Shop';
        $object['head'][1] = 'Store';
        $object['head'][2] = 'Gr.Tag';

        $i = 3;
        /*   $psp = \Monkey::app()->repoFactory->create('ProductSizeGroupHasProductSize')->findBy(['productSizeGroupId' => $this->productSizeGroupId]);
           foreach ($psp as $productSizeGroup) {
               $productSize = \Monkey::app()->repoFactory->create('ProductSize')->findOneBy(['id' => $productSizeGroup->productSizeId]);
               $object['head'][$i] = $productSize->name;
               $i++;
           }*/
        $object['rows'] = [];


        foreach ($shopIds as $shopId) {
            $i = 3;
            $object['head'][0] = 'Shop';
            $object['head'][1] = 'Store';
            $object['head'][2] = 'Gr.Tag';


            /** @var CShopHasProduct $shopHasProduct */
            $shopHasProduct = $this->shopHasProduct->findOneByKey('shopId',$shopId);
            if ($shopHasProduct) {
                foreach($this->product as $productSku ) {
                    /** @var CProductSku $productSku */
                    if ($productSku->stockQty > 0) {
                        $object['head'][$productSku->productSizeId] = $productSku->productSize->name;
                    }
                }
            }
            $storehouses = \Monkey::app()->repoFactory->create('Storehouse')->findBy(['shopId' => $shopId]);

            /** @var CStorehouse $storehouse */
            foreach ($storehouses as $storehouse) {

                $object['rows'][$storehouse->id][0] = $storehouse->shop->name;
                $object['rows'][$storehouse->id][1] = $storehouse->sigla;
                $object['rows'][$storehouse->id][2] = $shopHasProduct->productSizeGroup->locale . ' ' . $shopHasProduct->productSizeGroup->productSizeMacroGroup->name;

                \Monkey::dump($shopHasProduct);
                \Monkey::dump($shopHasProduct->productSizeGroup);
                \Monkey::dump($shopHasProduct->productSizeGroup->productSizeMacroGroup);
                /** @var CDirtyProduct $dirtyProduct */
                $dirtyProduct = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['productId' => $shopHasProduct->productId,'productVariantId' => $shopHasProduct->productVariantId,'shopId' => $shopId]);


                /** @var CDirtySku $dirtySku */
                $dirtySku = \Monkey::app()->repoFactory->create('DirtySku')->findBy(['dirtyProductId' => $dirtyProduct->id,'shopId' => $storehouse->shopId,'storeHouseId' => $storehouse->id,'status' => 'ok']);
                foreach ($dirtySku as $dirtySkus) {
                    if ($dirtySkus) {
                        if($dirtySkus->qty>0){
                            $object['rows'][$storehouse->id][$dirtySkus->productSizeId]['qty'] = $dirtySkus->qty;
                            $i++;
                        }
                    }


                }
            }

            return $object;
        }
    }

    public function getDdt(){

        $ddts = [];
        /** @var CObjectCollection $shootings */
        $shootings = $this->shooting;

        /** @var CDocumentRepo $docRepo */
        $docRepo = \Monkey::app()->repoFactory->create('Document');

        /** @var CShooting $shooting */
        foreach ($shootings as $shooting){
            $ddtNum = $docRepo->findShootingFriendDdt($shooting);


            $phs = \Monkey::app()->repoFactory->create('ProductHasShooting')->findBySql("
            SELECT *
            FROM ProductHasShooting phs
            JOIN Shooting s ON phs.shootingId = s.id
            WHERE phs.productId = ? AND phs.productVariantId= ? AND phs.shootingId = ?",[$this->id, $this->productVariantId, $shooting->id]
            );

            $progressiveNumberLine = "";
            foreach ($phs as $val){
                $progressiveNumberLine .= ' | '.$val->progressiveLineNumber;
            }

            $ddts[] = $ddtNum.' Num Prog: '.$progressiveNumberLine;
        }

        return implode("<br />", $ddts);

    }

    public function getAllShootingsIdsFromProduct() : array {

        /** @var CObjectCollection $shootings */
        $shootings = $this->shooting;

        $ids = [];

        /** @var CShooting $shooting */
        foreach ($shootings as $shooting) {
            $ids[] = $shooting->id;
        }

        return $ids;

    }

    public function getBarcodeInt() {

        /** @var CDirtyProduct $dp */
        $dp = \Monkey::app()->repoFactory->create('DirtyProduct')->findOneBy(['productId'=>$this->id, 'productVariantId'=>$this->productVariantId]);

        /** @var CObjectCollection $dSkus */
        $dSkus = $dp->dirtySku;

        /** @var CDirtySku $dirtySku */
        $dirtySku = $dSkus->getFirst();

        $barcodeInt = (is_null($dirtySku->barcode_int) ? '' : $dirtySku->barcode_int);

        return $barcodeInt;
    }

    public function hasPhoto(){
        if($this->productPhoto->count() == 0){
            return false;
        } else return true;
    }

    public function hasShooting(){
        if($this->shooting->count() == 0){
            return false;
        } else return true;
    }

    public function getProductCardUrl(){
        $prodPhoto = $this->productCardPhoto;

        if(!is_null($prodPhoto)){
            return $prodPhoto->productCardUrl;
        } else {
            return false;
        }
    }

    public function getSpecialTag(){

        /** @var CObjectCollection $tags */
        $tags = $this->tag;
        $special = [];
        /** @var CTag $tag */
        foreach ($tags as $tag){

            $isSpecial = substr($tag->slug, 0, 3);

            if($isSpecial == 'spc'){
                $special[] = $tag;
            }

        }

        return $special;

    }

    public function getOrderedProductDetails(){

        //PRODOTTO
        /** @var CObjectCollection $prSheetProduct */
        $prSheetProduct = $this->productSheetActual;

        if(is_null($this->productSheetPrototypeId) || $prSheetProduct->isEmpty()){
            return $prSheetProduct;
        }

        //AMMUCCHIO TT LE LABEL IN UN ARRAY
        $allProdLabel = [];
        /** @var CProductSheetActual $sing */
        foreach ($prSheetProduct as $sing){
            $allProdLabel[] = $sing->productDetailLabelId;
        }

        //TUTTE
        /** @var CObjectCollection $allDetails */
        $allDetails = $this->productSheetPrototype->productDetailLabel;
        $allDetails->reorderNumbersAndDates('order');

        /** @var CProductDetailLabel $val */
        foreach ($allDetails as $val){
            if(!in_array($val->id, $allProdLabel)) $allDetails->del($val);
        }

        //Ciclo la nuova collezione contente solamente quello che mi interessa (ordinato)
        $correctSheetActual = new CObjectCollection();

        /** @var CRepo $psaRepo */
        $psaRepo = \Monkey::app()->repoFactory->create('ProductSheetActual');
        /** @var CProductDetailLabel $detLabel */
        foreach ($allDetails as $detLabel){
            $correctSheetActual->add( $productSheetActual = $psaRepo->findOneBy([
                'productId'=>$this->id,
                'productVariantId'=>$this->productVariantId,
                'productDetailLabelId'=>$detLabel->id
            ]));
        }

        return $correctSheetActual;
    }

}