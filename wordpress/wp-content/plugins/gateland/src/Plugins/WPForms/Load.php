<?php

namespace Nabik\Gateland\Plugins\WPForms;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_filter( 'wpforms_currencies', [ $this, 'add_currencies' ] );
	}

	public function add_currencies( array $currencies ): array {

		$currencies['IRR'] = [
			'name'                => 'ریال ایران',
			'symbol'              => '&nbsp;ریال',
			'symbol_pos'          => 'right',
			'thousands_separator' => ',',
			'decimal_separator'   => '.',
			'decimals'            => 0,
		];

		$currencies['IRT'] = [
			'name'                => 'تومان ایران',
			'symbol'              => '&nbsp;تومان',
			'symbol_pos'          => 'right',
			'thousands_separator' => ',',
			'decimal_separator'   => '.',
			'decimals'            => 0,
		];

		return $currencies;
	}
}