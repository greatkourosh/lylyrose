<?php

namespace Nabik\Gateland\Enums\Gateway;

use Nabik\Gateland\Enums\EnumBase;

defined( 'ABSPATH' ) || exit;

class StatusesEnum extends EnumBase {
	const STATUS_ACTIVE   = 'active';
	const STATUS_INACTIVE = 'inactive';

	/**
	 * @return string
	 */
	public function name(): string {
		$values = [
			self::STATUS_ACTIVE   => 'فعال',
			self::STATUS_INACTIVE => 'غیرفعال',
		];

		return $values[ $this->value ];
	}

	/**
	 * @return string
	 */
	public function class(): string {
		$values = [
			self::STATUS_ACTIVE   => 'text-success',
			self::STATUS_INACTIVE => 'text-danger',
		];

		return $values[ $this->value ];
	}
}