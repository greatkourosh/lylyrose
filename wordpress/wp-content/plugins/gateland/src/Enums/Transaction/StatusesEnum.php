<?php

namespace Nabik\Gateland\Enums\Transaction;

use Nabik\Gateland\Enums\EnumBase;

defined( 'ABSPATH' ) || exit;

class StatusesEnum extends EnumBase {
	const STATUS_PENDING = 'pending';
	const STATUS_FAILED  = 'failed';
	const STATUS_PAID    = 'paid';
	const STATUS_REFUND  = 'refund';

	/**
	 * @return string
	 */
	public function name(): string {
		$values = [
			self::STATUS_PENDING => 'در انتظار پرداخت',
			self::STATUS_FAILED  => 'ناموفق',
			self::STATUS_PAID    => 'پرداخت شده',
			self::STATUS_REFUND  => 'استرداد شده',
		];

		return $values[ $this->value ];
	}

}