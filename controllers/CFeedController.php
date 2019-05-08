<?php

namespace bamboo\ecommerce\controllers;

use bamboo\domain\entities\CMarketplaceAccount;
use bamboo\domain\entities\CProduct;
use bamboo\core\router\ARootController;
use bamboo\core\router\CInternalRequest;
use bamboo\core\theming\CWidgetHelper;
use bamboo\core\theming\nestedCategory\CCategoryManager;

/**
 * Class CFeedController
 * @package bamboo\ecommerce\controllers
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
class CFeedController extends ARootController
{

    public function createAction($action)
    {
        $filters = $this->app->router->getMatchedRoute()->getComputedFilters();

        $request = new CInternalRequest("", $filters['loc'], $filters, $this->request->getRequestData(),$this->app->router->request()->getMethod());
        return $this->{$action}($request);
    }

    /**
     * @param CInternalRequest $internalRequest
     * @return string
     */
    public function get(CInternalRequest $internalRequest)
    {
        foreach (\Monkey::app()->repoFactory->create('MarketplaceAccount')->findAll() as $marketplaceAccount) {
            try {
                if ($this->app->router->request()->getUrlPath() == $marketplaceAccount->config['feedUrl'] || (
                        (!isset($marketplaceAccount->config['lang']) ||
                            $this->app->router->getMatchedRoute()->getComputedFilter('loc') == $marketplaceAccount->config['lang']) &&
                            $this->app->router->getMatchedRoute()->getComputedFilter('marketplaceSlug') == $marketplaceAccount->config['slug'])) {
                    return $this->prepareFeed($marketplaceAccount->config['filePath'],true,$marketplaceAccount->config['contentType'] ?? 'application/xml');
                }
            } catch (\Throwable $e) {
            };
        }
        $this->app->router->response()->raiseProcessingError();
        return "Feed not Found";
    }

    /**
     * @param $filePath
     * @param bool $isRelative
     * @param string $feedType
     * @return string
     */
    protected function prepareFeed($filePath, $isRelative = true,$feedType = 'application/xml')
    {
        $uri = $isRelative ? $this->app->rootPath() . $this->app->cfg()->fetch('paths', 'productSync') . $filePath : $filePath;

        $this->response->setContentType($feedType.'; charset=UTF-8');
        $this->response->sendHeaders();
        return file_get_contents($uri);
    }

    public function __destruct()
    {
    }
}