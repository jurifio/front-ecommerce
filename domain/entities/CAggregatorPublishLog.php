<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CAggregatorPublishLog
 * @package bamboo\domain\entities
 *
 * @author Iwes Team <it@iwes.it>
 *
 * @copyright (c) Iwes  snc - All rights reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 *
 * @date 17/01/2020
 * @since 1.0
 */
class CAggregatorPublishLog extends AEntity
{
    protected $entityTable = 'AggregatorPublishLog';
    protected $primaryKeys = ['id'];
}