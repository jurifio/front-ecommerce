<?php

namespace bamboo\export\order;

use bamboo\domain\entities\COrderLine;
use bamboo\domain\entities\CShop;
use bamboo\core\application\AApplication;
use bamboo\domain\repositories\CEmailRepo;

/**
 * Class COrderExport
 * @package export\order
 * @author Bambooshoot Team <emanuele@bambooshoot.agency>, ${DATE}
 * @copyright (c) Bambooshoot snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @since ${VERSION}
 */
class COrderExport
{
	CONST IVA_PERCENT = 22;

	/** @var AApplication $app */
	protected $app;

	public function __construct(AApplication $app)
	{
		$this->app = $app;
	}


	/**
	 * @param CShop $shop
	 * @param $orderLines
	 * @return bool
	 */
	public function exportPrefileForFriend(CShop $shop, $orderLines)
	{
		$lines = $this->buildDatas($shop, $orderLines);
		$fileName = 'preorder_' . date('YmdHis', time()) . '_rows_' . count($lines) . '.csv';
		try {
			$f = fopen($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $shop->name . '/export/' . $fileName, 'x');
			foreach ($lines as $row) {
				$line = $row['orderId'] . ';' . $row['orderLineId'] . ';' . $row['extId'] . ';' . $row['var'] . ';' . $row['itemno'] . ';' . $row['size'] . ';' . $row['friendRevenue'] . "\n";
				fwrite($f, $line);
			}
			fflush($f);
			fclose($f);
		} catch (\Throwable $e) {
			var_dump($e);
		}

		return true;
	}

	/**
	 * @param CShop $shop
	 * @param $orderLines
	 * @return array
	 */
	public function buildDatas(CShop $shop, $orderLines)
	{
		$lines = [];
		foreach ($orderLines as $line) {
			$lines[] = $this->buildData($shop, $line);
		}

		return $lines;
	}

	/**
	 * @param CShop $shop
	 * @param COrderLine $line
	 * @return array
	 */
	private function buildData(CShop $shop, COrderLine $line)
	{
		$row = [];
		$billing = new $shop->billingLogic($this->app);
		$row['orderId'] = $line->orderId;
		$row['orderLineId'] = $line->id;
		$row['productId'] = $line->productId;
		$row['productVariantId'] = $line->productVariantId;
		$row['productSizeId'] = $line->productSizeId;
		$row['shopId'] = $line->shopId;
		$row['remoteShopSellerId']=$line->remoteShopSellerId;

		try {
		    $dirtySku = $line->productSku->findRightDirtySku();
			/*
			 $findIds = "SELECT itemno, extId, ds.extSkuId AS extSkuId, var, size
						FROM DirtyProduct dp, DirtySku ds 
						WHERE ds.dirtyProductId = dp.id AND 
								dp.productId = ? AND 
								dp.productVariantId = ? AND 
								dp.shopId = ? AND 
								ds.productSizeId = ?";
			$ids = $this->app->dbAdapter->query($findIds, [$line->productId, $line->productVariantId, $line->shopId, $line->productSizeId])->fetchAll()[0];
			*/
			$row['extId'] = !empty($dirtySku->extSkuId) ? $dirtySku->extSkuId : $dirtySku->dirtyProduct->extId;
			$row['var'] = $dirtySku->dirtyProduct->var;
			$row['size'] = $dirtySku->size;
			$row['itemno'] = $dirtySku->dirtyProduct->itemno;
		} catch (\Throwable $e) {
			$row['extId'] = $line->productSku->shopHasProduct->extId;
			$row['itemno'] = $line->productSku->product->itemno;
			$row['var'] = $line->productSku->product->productVariant->name;
			$row['size'] = $line->productSku->productSize->name;
		}

		$product = $line->productSku->product;

		/**  find brand name*/
		//$findIds = "SELECT pb.name AS brand, slug  FROM ProductBrand pb, Product p WHERE p.productBrandId = pb.id AND p.id = ? AND p.productVariantId = ?";
		//$ids = $this->app->dbAdapter->query($findIds, [$line->productId, $line->productVariantId])->fetchAll()[0];
		$row['brand'] = $product->productBrand->name;
		$row['brandSlug'] = $product->productBrand->slug;
		$row['productNameTranslation'] = $product->getName();

		$row['friendRevenue'] = isset($line->friendRevenue) && !is_null($line->friendRevenue) && $line->friendRevenue <> 0 ? $line->friendRevenue : $billing->calculateFriendReturn($line);
		/** find photo */
		//$findIds = "SELECT pp.name AS photo FROM ProductHasProductPhoto ps, ProductPhoto pp WHERE ps.productPhotoId = pp.id AND ps.productId = ? AND ps.productVariantId = ? AND pp.size = ? ";
		//$ids = $this->app->dbAdapter->query($findIds, [$line->productId, $line->productVariantId, 281])->fetchAll()[0];
		$row['photo'] = $product->getPhoto(1,281);

		return $row;
	}

	/**
	 * @param CShop $shop
	 * @param $orderLines
	 */
	public function sendMailForFriendConfirmation(CShop $shop, $orderLines)
	{
		$lines = $this->buildDatas($shop, $orderLines);
		$to = explode(';', $shop->referrerEmails);

		/*
		$this->app->mailer->prepare('friendconfirmationmail', 'no-reply', $to, [], [], ['lines' => $lines]);
		$res = $this->app->mailer->send();*/

        /** @var CEmailRepo $emailRepo */
        $emailRepo = \Monkey::app()->repoFactory->create('Email');
        $emailRepo->newPackagedTemplateMail('friendconfirmationmail', 'no-reply@iwes.pro', $to, [], [], ['lines' => $lines],'MailGun',null);
	}

	/**
	 * @param CShop $shop
	 * @param $orderLines
	 * @return bool
	 *
	 * TODO: aggiungere iva (e data sulla riga ? )
	 */
	public function exportOrderFileForFriend(CShop $shop, $orderLines)
	{
		$lines = $this->buildDatas($shop, $orderLines);
		$fileName = 'order_' . date('YmdHis', time()) . '_rows_' . count($lines) . '.csv';
		try {
			$f = fopen($this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . '/' . $shop->name . '/export/' . $fileName, 'x');
			foreach ($lines as $row) {
				$line = $row['orderId'] . ';' . $row['orderLineId'] . ';' . $row['extId'] . ';' . $row['var'] . ';' . $row['itemno'] . ';' . $row['size'] . ';' . $row['friendRevenue'] . "\n";
				fwrite($f, $line);
			}
			fflush($f);
			fclose($f);
		} catch (\Throwable $e) {
			throw $e;
		}

		return true;
	}


	/**
	 * @param CShop $shop
	 * @param $orderLines
	 */
	public function sendMailForOrderNotification(CShop $shop, $orderLines)
	{
		$lines = $this->buildDatas($shop, $orderLines);
		$to = explode(';', $shop->referrerEmails);
		$total = 0;
		foreach ($orderLines as $line) {
			$total += $line->friendRevenue;
		}
		$total = round($total + ($total * self::IVA_PERCENT / 100), 2);


		/*$this->app->mailer->prepare('friendordernotificationmail', 'no-reply', $to, [], [], ['lines' => $lines, 'total' => $total]);
		$res = $this->app->mailer->send();*/

		/** @var CEmailRepo $emailRepo */
        $emailRepo = \Monkey::app()->repoFactory->create('Email');
        $emailRepo->newPackagedTemplateMail('friendordernotificationmail', 'no-reply@iwes.pro', $to, [], [], ['lines' => $lines, 'total' => $total],'MailGun',null);
	}


}