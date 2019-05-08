<?php

namespace bamboo\domain\entities;

use bamboo\core\db\pandaorm\entities\AEntity;

/**
 * Class CProductPhoto
 * @package bamboo\app\domain\entities
 *
 * @since 0.0.1
 */
class CProductPhoto extends AEntity
{
    protected $entityTable = 'ProductPhoto';
	protected $primaryKeys = ['id'];

	const SIZE_BIG = 1124;
	const SIZE_MEDIUM = 843;
	const SIZE_SMALL = 562;
	const SIZE_THUMB = 281;

	public function isBig() {
		return $this->size == self::SIZE_BIG;
	}

	public function isMedium() {
		return $this->size == self::SIZE_MEDIUM;
	}

	public function isSmall() {
		return $this->size == self::SIZE_SMALL;
	}

	public function isThumb() {
		return $this->size == self::SIZE_THUMB;
	}
}