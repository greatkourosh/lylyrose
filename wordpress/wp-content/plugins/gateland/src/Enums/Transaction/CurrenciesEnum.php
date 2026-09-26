<?php

namespace Nabik\Gateland\Enums\Transaction;

use Nabik\Gateland\Enums\EnumBase;

defined( 'ABSPATH' ) || exit;

class CurrenciesEnum extends EnumBase {
	const IRT  = 'IRT';
	const USDT = 'USDT';

	/**
	 * @return string
	 */
	public function symbol(): string {
		$values = [
			self::IRT  => 'تومان',
			self::USDT => 'USDT',
		];

		return $values[ $this->value ];
	}

	/**
	 * @param  float  $price
	 *
	 * @return string
	 */
	public function price( float $price ): string {
		$values = [
			self::IRT => number_format( $price ) . ' ' . $this->symbol(),
		];

		$default = $this->symbol() . ' ' . number_format( $price, 2 );

		return $values[ $this->value ] ?? $default;
	}
}