<?php

namespace bamboo\ecommerce\views\widget;

use bamboo\core\router\CInternalRequest;
use bamboo\core\router\CNodeView;

/**
 * Class VBase
 * @package bamboo\ecommerce\views\widget
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date $date
 * @since 1.0
 * @deprecated extending CRootView for consistency
 */
class VBase extends CNodeView {
    public function __construct($internalRequest = null)
    {
        if(!$internalRequest instanceof CInternalRequest)
            $internalRequest = new CInternalRequest('dummy',
                \Monkey::app()->getLang()->getLang(),
                \Monkey::app()->router->getMatchedRoute()->getComputedFilters(),
                \Monkey::app()->router->request()->getRequestData(),
                \Monkey::app()->router->request()->getMethod(),
                is_array($internalRequest) ? $internalRequest : []
            );
        parent::__construct($internalRequest);

    }
}