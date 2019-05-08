<?php

namespace bamboo\domain\entities;

use bamboo\core\application\AApplication;
use bamboo\core\base\CObjectCollection;
use bamboo\core\db\pandaorm\entities\AEntity;


class CLog extends AEntity
{
	protected $entityTable = 'Log';
	protected $primaryKeys = ['id'];
}