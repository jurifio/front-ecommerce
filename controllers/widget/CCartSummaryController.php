<?php

namespace bamboo\controllers\widget;

use bamboo\core\base\CObjectCollection;
use bamboo\core\exceptions\BambooException;
use bamboo\core\router\ANodeController;
use bamboo\core\router\CNodeView;
use bamboo\domain\entities\CCartLine;
use bamboo\domain\repositories\CCartRepo;
use bamboo\helpers\CWidgetCatalogHelper;

/**
 * Class CCartSummaryController
 * @package bamboo\app\controllers\widget
 *
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>
 *
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 */
class CCartSummaryController extends ANodeController
{
    /**
     * @return \bamboo\core\router\CInternalResponse
     */
    public function post()
    {
        return $this->get();
    }

    /**
     * @return \bamboo\core\router\CInternalResponse
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaThemeException
     */
    public function get()
    {
        $this->view = new CNodeView($this->request, $this->config['template']['fullpath']);

        $this->fetchData();

        $totale = 0;
        $newCartLine = new CObjectCollection();
        $this->dataBag->entity->elements = 0;
        $skus = [];

        if (($this->dataBag->entity->cartLine instanceof CObjectCollection) && !$this->dataBag->entity->cartLine->isEmpty()) {

            $this->dataBag->entity->cartLine->reorder('creationDate', 'DESC');
            $this->dataBag->entity->elements = count($this->dataBag->entity->cartLine);

            foreach ($this->dataBag->entity->cartLine as $key => $cartLine) {
                /** @var CCartLine $cartLine */
                $totale = $cartLine->getLineGrossTotal();
                $newCartLine->addConditional(
                    $cartLine, [
                        "cartId" => $cartLine->cartId,
                        "productId" => $cartLine->productId,
                        "productVariantId" => $cartLine->productVariantId,
                        "productSizeId" => $cartLine->productSizeId]);
                $skus[$cartLine->id] = \Monkey::app()->repoFactory->create('ProductPublicSku')->findBy(['productId' => $cartLine->productId, 'productVariantId' => $cartLine->productVariantId]);
            }

            foreach ($newCartLine as $key => $orderLine) {
                $orderLine->qty = $newCartLine->getInfo()["count"];
            }

        }
        $this->dataBag->entity->cartLine = $newCartLine;
        //throw new \Exception('Running Maintenance | Manutenzione in corso');
        $this->helper = new CWidgetCatalogHelper($this->app);
        $this->view->pass('total', $totale);
        $this->view->pass('app', $this->helper);
        $this->view->pass('data', $this->dataBag);
        $this->view->pass('skus', $skus);

        return $this->show();
    }

    /**
     * @return \bamboo\core\router\CInternalResponse
     * @throws \Exception
     * @throws \bamboo\core\exceptions\RedPandaORMInvalidEntityException
     */
    public function put()
    {
        $size = $this->request->getFilter('size');
        $value = $this->request->getFilter('value');

        $cartLineRepo = \Monkey::app()->repoFactory->create('CartLine');
        /** @var CCartRepo $cartRepo */
        $cartRepo = \Monkey::app()->repoFactory->create('Cart');
        if ($size) {
            $cl = $cartLineRepo->findOneBy(['cartId' => $cartRepo->currentCartId(), 'id' => $this->request->getFilter('id')]);
            $cartRepo->removeSku($this->request->getFilter('id'));
            $cl->productSizeId = $this->request->getFilter('productSizeId');
            $cl->insert();

        } elseif ($value > 0) {
            /** @var CCartLine $cl */
            $cl = $cartLineRepo->findOneBy(['cartId' => $cartRepo->currentCartId(), 'id' => $this->request->getFilter('id')]);
            $res = $cartRepo->addSku($cl->productPublicSku, 1);
            if ($res < 0) throw new BambooException('Quantità non disponibile');
        } else {
            $cartRepo->removeSku($this->request->getFilter('id'));
        }

        return $this->get();
    }

    /**
     * @return \bamboo\core\router\CInternalResponse
     */
    public function delete()
    {
        $cartLineOriginal = \Monkey::app()->repoFactory->create('CartLine')
            ->findOneBy(['cartId' => \Monkey::app()->repoFactory->create('Cart')->currentCartId(), 'id' => $this->request->getFilter('id')]);

        foreach ($cartLineOriginal->cart->cartLine as $cartLine) {
            if ($cartLineOriginal->productId == $cartLine->productId &&
                $cartLineOriginal->productVariantId == $cartLine->productVariantId &&
                $cartLineOriginal->productSizeId == $cartLine->productSizeId) {
                \Monkey::app()->repoFactory->create('Cart')->removeSku($cartLineOriginal->id);
            }
        }
        return $this->get();
    }
}