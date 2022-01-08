<?php

namespace bamboo\domain\repositories;

use bamboo\core\exceptions\BambooException;
use bamboo\core\exceptions\BambooLogicException;
use bamboo\core\exceptions\BambooStorehouseOperationException;
use bamboo\core\traits\TMySQLTimestamp;
use bamboo\core\db\pandaorm\repositories\ARepo;
use bamboo\domain\entities\CProduct;
use bamboo\domain\entities\CProductSku;
use bamboo\domain\entities\CShopHasProduct;
use bamboo\domain\entities\CStorehouseOperationLine;

/**
 * Class CStorehouseOperationRepo
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
class CStorehouseOperationRepo extends ARepo
{

    const PUBLISH_RELEASE = 'released';
    const PUBLISH_OUT_OF_STOCK = 'out-of-stock';
    const PUBLISH_RESTOCK = 'restocked';

    use TMySQLTimestamp;

    /**
     * @param $skusToMove
     * @param $shop
     * @param $movementCauseId
     * @param null $storehouse
     * @param null $notes
     * @return bool
     * @throws \Exception
     */
    public function registerOperation($skusToMove, $shop, $movementCauseId, $operationDate = null, $storehouse = null, $notes = null)
    {
        $rf = \Monkey::app()->repoFactory;

        if (!$shop) throw new BambooException('Lo shop selezionato non esiste!!');
        $causeR = $rf->create('StorehouseOperationCause');
        $cause = $causeR->findOne([$movementCauseId]);
        if (!$cause) throw new BambooException('Causa inesistente');
        if (is_numeric($shop)) {
            $shopR = \Monkey::app()->repoFactory->create('Shop');
            $shop = $shopR->findOne([$shop]);
        }
        if ((!$shop->isActive) && ($cause->available)) throw new BambooException('Operazione non permessa in uno shop non attivo');

        $SOEm = $rf->create('StorehouseOperation');
        $SOLRepo = $rf->create('StorehouseOperationLine');
        $SOCEm = $rf->create('StorehouseOperationCause');
        $SEm = $rf->create('Storehouse');


        /**
         *  Ciclo che evita i duplicati all'interno del movimento
         */
        $productSku = [];
        foreach ($skusToMove as $sku) {
            if (!count($productSku)) {
                $productSku[] = $sku;
                continue;
            }
            $copied = false;
            foreach ($productSku as $k => $skuFinal) {
                if (
                    ($skuFinal['id'] == $sku['id'])
                    && ($skuFinal['productVariantId'] == $sku['productVariantId'])
                    && ($skuFinal['productSizeId'] == $sku['productSizeId'])
                ) {
                    $copied = true;
                    $productSku[$k]['qtMove'] += $skuFinal['qtMove'];
                    break;
                }
            }
            if (false === $copied) {
                $productSku[] = $sku;
            }
        }

        try {
            if (!$storehouse) {
                $storehouse = \Monkey::app()->repoFactory->create('Storehouse')->findOneBy(['shopId' => $shop->id]);
            } elseif (is_string($storehouse)) {
                $storehouse = \Monkey::app()->repoFactory->create('Storehouse')->findOneBy(['shopId' => $shop->id, 'id' => $storehouse]);
            }

            if (!$storehouse) {
                $storehouse = $SEm->getEmptyEntity();
                $storehouse->shopId = $shop->id;
                $storehouse->name = 'auto-generated';
                $storehouse->countryId = 1;
                $storehouse->id = $storehouse->insert();
            }

            if (!$SOC = $SOCEm->findOne([$movementCauseId])) throw new \Exception('La causale è obbligatoria');

            //fatti tutti i controlli preliminari, inizio la transazione
            if (!$operationDate) $operationDate = time();
            elseif (is_string($operationDate)) {
                $operationDate = strtotime($operationDate);
                if (!$operationDate) throw new BambooException('La data del movimento è stata fornita in un formato che non riesco ad interpretare');
            }
            $operationDate = date('Y-m-d', $operationDate);
            $newOp = $SOEm->getEmptyEntity();
            $newOp->shopId = $shop->id;
            $newOp->storehouseId = $storehouse->id;
            $newOp->storehouseOperationCauseId = $movementCauseId; //$get['mag-movementCause'];
            $newOp->userId = \Monkey::app()->getUser()->id;
            $newOp->operationDate = $operationDate;
            $newOp->notes = $notes;
            $newOp->id = $newOp->insert();

            //inizio l'inserimento dei singoli movimenti

            foreach ($productSku as $v) {
                $SOLRepo->createMovementLine(
                    $v['id'],
                    $v['productVariantId'],
                    $v['productSizeId'],
                    $shop->id,
                    $v['qtMove'],
                    $newOp->id,
                    $storehouse->id
                );

                /*if (0 < $v['qtMove']) {
                    $stringId = $v['id'] . '-' . $v['productVariantId'] . '-' . $shop->id;
                    if (!in_array($stringId, $shpToCheck, true)) {
                        $shpToCheck[] = $stringId;
                    }
                }*/
            }

            $this->updateStocksOnOperationTime();


            /*foreach ($shpToCheck as $shpStringId) {
                $shp = $shpR->findOneByStringId($shpStringId);
                if (!$shp) {
                    throw new BambooException(
                        'Non possono essere salvati i movimenti di un prodotto a cui non sono stati assegnati costo e prezzo'
                    );
                }
                if (!$shp->releaseDate) {
                    if ($shp->product->productPhoto->count()) {
                        $now = date('Y-m-d H:i:s');

                        \Monkey::app()->eventManager->triggerEvent(
                            'releaseProduct',
                            [
                                'ShopHasProductE' => $shp,
                                'releaseDate' => $now
                            ]);
                    }
                }
            }*/

            return true;
        } catch (BambooException $e) {
            throw new BambooException($e->getMessage());
        }
    }

    /**
     *
     * @throws BambooException
     */
    public function updateStocksOnOperationTime()
    {
        $dba = \Monkey::app()->dbAdapter;
        $query = "SELECT * FROM `StorehouseOperation` WHERE `isProcessed` = 0 AND `operationDate` <= ?";
        $res = $dba->query($query, [date('Y-m-d')])->fetchAll();

        $soR = \Monkey::app()->repoFactory->create('StorehouseOperation');
        $skuR = \Monkey::app()->repoFactory->create('ProductSku');

        foreach ($res as $v) {
            $soE = $soR->findOneBy([
                'id' => $v['id'],
                'shopId' => $v['shopId'],
                'storehouseId' => $v['storehouseId'],
            ]);
            $solE = $soE->storehouseOperationLine;

            /** @var CStorehouseOperationLine $sol */
            foreach ($solE as $sol) {

                $skuToUpdate = \Monkey::app()->repoFactory->create('ProductSku')->findOneBy(['productId'=>$sol->productId,'productVariantId'=>$sol->productVariantId,'productSizeId'=>$sol->productSizeId]);




                if ($skuToUpdate) {
                    $shpEm = $skuToUpdate->shopHasProduct;
                    $value = $shpEm->value;
                    $price = $shpEm->price;

                    $qtyToMove = $sol->qty;
                    //TODO: gestione padding da aggiornare quando si implementeranno i resi

                    //prepare i releaseDate o gli esaurito
                    //todo: RESCRIVERE LE CONDIZIONI PER DEFINIRE LO STATO AGGIORNATO DEI PRODOTTI


                    if (0 == $skuToUpdate->padding) {
                        $skuToUpdate->stockQty = $skuToUpdate->stockQty + $qtyToMove;
                    } elseif (0 > $skuToUpdate->padding) {

                        if (0 > $qtyToMove) {
                            $diff = $skuToUpdate->padding - $qtyToMove;
                            if (0 > $diff) $skuToUpdate->padding = $diff;
                            else {
                                $skuToUpdate->padding = 0;
                                $skuToUpdate->stockQty = $skuToUpdate->stockQty - $diff;
                            }
                        } else {
                            $skuToUpdate->stockQty = $skuToUpdate->stockQty + $qtyToMove;
                        }

                    } else { // se il padding è maggiore di zero
                        if (0 < $qtyToMove) {
                            $diff = $skuToUpdate->padding - $qtyToMove;
                            if (0 < $diff) $skuToUpdate->padding = $diff;
                            else {
                                $skuToUpdate->padding = 0;
                                $skuToUpdate->stockQty = $skuToUpdate->stockQty - $diff;
                            }
                        } else {
                            $skuToUpdate->stockQty = $skuToUpdate->stockQty + $qtyToMove;
                        }
                    }
                    $skuToUpdate->update();

                    $ret = $skuToUpdate;
                } else {
                    if (0 > $qtyToMove) throw new BambooException('Non posso inserire un nuovo sku con quantità negativa');
                    $sku = $skuR->getEmptyEntity();
                    $sku->productId = $sol['productId'];
                    $sku->productVariantId = $sol['productVariantId'];
                    $sku->productSizeId = $sol['productSizeId'];
                    $sku->shopId = $sol['shopId'];
                    $sku->stockQty = $qtyToMove;
                    $sku->value = $value;
                    $sku->price = $price;
                    $sku->currencyId = 1;
                    $sku->insert();

                    $ret = $sku;
                }

                $skuR->levelPrice($ret->productId, $ret->productVariantId);
            }

            $soE->isProcessed = 1;
            $soE->update();
        }
    }


    /**
     * @param $shopId
     * @param array $productSku
     * @param null $storehouseId
     * @param null $notes
     * @return bool
     */
    public function registerEcommerceSale($shopId, array $productSku, $storehouseId, $accepted)
    {
        $shop = \Monkey::app()->repoFactory->create('Shop')->findOne([$shopId]);
        if (!$shop) throw new BambooException('i can\'t register a sale without a shop');
        $arrSku = [];

        if (!$shop->importer) {
            foreach($productSku as $ps) {
                $single = [];
                $single['id'] = $ps->productId;
                $single['productVariantId'] = $ps->productVariantId;
                $single['productSizeId'] = $ps->productSizeId;
                $single['qtMove'] = -1;
                $arrSku[] = $single;
            }

            if ($accepted) $causeId = 2;
            else $causeId = 13;
            return $this->registerOperation($arrSku, $shop, $causeId, $storehouseId);
        }
    }

    /**
     * @param $productStringIdOrProductEntity string|CProduct
     * @return array
     */
    public function prepareAllSkusToBeMoved($productStringIdOrProductEntity) {
        if (is_string($productStringIdOrProductEntity)) {
            $p = \Monkey::app()->repoFactory->create('Product')->findOneByStringId($productStringIdOrProductEntity);
        } else {
            $p = $productStringIdOrProductEntity;
        }
            $ret = [];
            foreach($p->productSku as $s) {
                if (!array_key_exists($s->shopId, $ret)) $ret[$s->shopId] = [];
                $single  = [];
                $single['id'] = $s->productId;
                $single['productVariantId'] = $s->productVariantId;
                $single['productSizeId'] = $s->productSizeId;
                $single['qtMove'] = $s->stockQty + $s->padding;
                $ret[$s->shopId][] = $single;
            }
            return $ret;
    }

    public function AllSkusToZero($productStringIdOrProductEntity, $storehouseOpCauseId) {
        if (is_string($productStringIdOrProductEntity)) {
            $p = \Monkey::app()->repoFactory->create('Product')->findOneByStringId($productStringIdOrProductEntity);
        } else {
            $p = $productStringIdOrProductEntity;
        }

        foreach($this->prepareAllSkusToBeMoved($p) as $sid => $skus) {
            foreach($skus as $k => $v) {
                $skus[$k]['qtMove'] =  $v['qtMove'] * -1;
            }
            $this->registerOperation($skus, $sid, $storehouseOpCauseId);
        }
    }

    /**
     * @param $shopId
     * @return bool
     */
    public function AllSkusByFriendToZero($shopId) {
        $dba = \Monkey::app()->dbAdapter;
        $res = $dba->query(
            "SELECT productId as `id`, productVariantId, productSizeId, stockQty, padding FROM ProductSku WHERE shopId = ? AND (stockQty > 0 || padding < 0)",
            [$shopId]
        )->fetchAll();


        foreach($res as $k => $v) {
            $res[$k]['qtMove'] = ($v['stockQty'] - $v['padding']) * -1;
            unset($res[$k]['stockQty']);
            unset($res[$k]['padding']);
        }
        return $this->registerOperation($res, $shopId, 12);
    }

    public function resumeFriendFromZero($shopId){
        $dba = \Monkey::app()->dbAdapter;
        $sql = 'SELECT MAX(creationDate), storehouseOperationCauseId, id, shopId, storehouseId FROM StorehouseOperation WHERE shopId = ?';
        $res = $dba->query($sql, [$shopId])->fetch();

        $socR = \Monkey::app()->repoFactory->create('StorehouseOperationCause');
        $soc = $socR->findOne([$res['storehouseOperationCauseId']]);
        if ('Friend a zero' !== $soc->name) throw new BambooStorehouseOperationException('Il Friend non risulta essere stato messo offline');

        $so = \Monkey::app()->repoFactory->create('StorehouseOperation')->findOne([$res['id'], $res['shopId'], $res['storehouseId']]);
        $skuToLoad = [];
        foreach($so->storehouseOperationLine as $v) {
            $single = [];
            $single['id'] = $v->productId;
            $single['productVariantId'] = $v->productVariantId;
            $single['productSizeId'] = $v->productSizeId;
            $single['qtMove'] = $v->qty * -1;
            $skuToLoad[] = $single;
        }
        unset($single);

        return $this->registerOperation($skuToLoad, $shopId, 17);
    }

    /**
     * @param CProductSku $originProductSku
     * @param CProductSku $destProductSku
     * @return bool
     * @throws BambooLogicException
     */
    public function moveSilentlyMovementOnADifferentProductSku(CProductSku $originProductSku, CProductSku $destProductSku) {
        if($originProductSku->shopId == $destProductSku->shopId && $originProductSku->productSizeId == $destProductSku->productSizeId) {
            return \Monkey::app()->dbAdapter->query('UPDATE StorehouseOperationLine 
                                                 SET productId = :newId,
                                                     productVariantId = :newProductVariantId 
                                                 WHERE productId = :productId AND 
                                                       productVariantId = :productVariantId AND 
                                                       productSizeId = :productSizeId AND 
                                                       shopId = :shopId',['newId'=>$destProductSku->productId,'newProductVariantId'=>$destProductSku->productVariantId] + $originProductSku->getIds())->countAffectedRows() > 1;
        } else throw new BambooLogicException('Different skus fusion');
    }

    /**
     * @param $productSource
     * @param $productDestination
     * @param $sourceShopId
     * @param $destinationShopId
     * @return bool
     * @throws BambooException
     */
    public function moveStocksOnADifferentProduct($productSource, $productDestination, $sourceShopId) {
        if ($productSource->productSizeGroupId != $productDestination->productSizeGroupId) {
            throw new BambooException('I prodotti devono appartenere allo stesso gruppo taglia');
        }
        $productSkuSource = $productSource->productSku->findByKey('shopId', $sourceShopId);

        $arrSku = [];
        foreach($productSkuSource as $s) {
            $single = [];
            $single['id'] = $s->productId;
            $single['productVariantId'] = $s->productVariantId;
            $single['productSizeId'] = $s->productSizeId;
            $single['qtMove'] = $s->stockQty * -1;
            $arrSku[] = $single;
        }

        $this->registerOperation($arrSku, $sourceShopId, 18);

        foreach($arrSku as $k => $s) {
            $arrSku[$k]['id'] = $productDestination->id;
            $arrSku[$k]['productVariantId'] = $productDestination->productVariantId;
            $arrSku[$k]['qtMove'] = $s['qtMove'] * -1;
        }

        $this->registerOperation($arrSku, $sourceShopId, 19);
        return true;
    }

    /**
     * @param $arrSku
     * @return mixed
     */
    protected function reverseStockSign($arrSku) {
        foreach($arrSku as $v) {
            $arrSku['qtMove'] = $v['qtMove'] * -1;
        }
        return $arrSku;
    }
}