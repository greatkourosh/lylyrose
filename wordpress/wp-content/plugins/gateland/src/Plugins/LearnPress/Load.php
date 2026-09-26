<?php

namespace Nabik\Gateland\Plugins\LearnPress;

use LP_Addon;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {

		LP_Addon::load( Addon::class, 'Addon.php', __DIR__ . '/Addon.php' );

		add_filter( 'learn-press/currencies', [ $this, 'add_currencies' ] );
		add_filter( 'learn-press/currency-symbols', [ $this, 'add_symbols' ] );
	}

	public function add_currencies( array $currencies ): array {

		$currencies['IRR'] = 'ریال ایران';
		$currencies['IRT'] = 'تومان ایران';

		return $currencies;
	}

	public function add_symbols( array $symbols ): array {

		$symbols['IRR'] = '&nbsp;ریال';
		$symbols['IRT'] = '&nbsp;تومان';

		return $symbols;
	}
}