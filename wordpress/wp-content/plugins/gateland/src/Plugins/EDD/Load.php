<?php

namespace Nabik\Gateland\Plugins\EDD;

use Nabik\Gateland\Services\GatewayService;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {

		new Gateway();

		foreach ( GatewayService::activated() as $gateway_id => $gateway ) {
			new Gateway( $gateway_id );
		}

		add_filter( 'edd_currencies', [ $this, 'currencies' ] );
		add_filter( 'edd_sanitize_amount_decimals', [ $this, 'amount_decimals' ] );
		add_filter( 'edd_format_amount_decimals', [ $this, 'amount_decimals' ] );
		add_filter( 'edd_irt_currency_filter_before', [ $this, 'currency_filter' ], 10, 1 );
		add_filter( 'edd_rial_currency_filter_before', [ $this, 'currency_filter' ], 10, 1 );
		add_filter( 'edd_irt_currency_filter_after', [ $this, 'currency_filter' ], 10, 1 );
		add_filter( 'edd_rial_currency_filter_after', [ $this, 'currency_filter' ], 10, 1 );
	}

	public function currencies( $currencies ) {
		$currencies['IRT'] = 'تومان';

		return $currencies;
	}

	public function amount_decimals( $decimals ) {
		global $edd_options;

		$currency = function_exists( 'edd_get_currency' ) ? edd_get_currency() : $edd_options['currency'] ?? null;

		if ( $currency == 'IRT' || $currency == 'RIAL' ) {
			return 0;
		}

		return $decimals;
	}

	public function currency_filter( $formatted ) {
		$formatted = str_replace( [
			'IRT',
			'rial',
		], [
			'تومان',
			'ریال',
		], $formatted );

		return str_replace(
			range( 0, 9 ),
			[ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ],
			$formatted
		);
	}
}
