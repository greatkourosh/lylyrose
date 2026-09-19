<?php

namespace Nabik\Gateland\Plugins\WPUF;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {

		if ( ! function_exists( 'wpuf_get_option' ) ) {
			return;
		}

		include 'Gateway.php';

		new Gateway();

		foreach ( \Nabik\Gateland\Services\GatewayService::activated() as $gateway_id => $gateway ) {
			new Gateway( $gateway_id );
		}

		add_filter( 'wpuf_options_payment', [ $this, 'set_payment_options' ] );
		add_filter( 'wpuf_currencies', [ $this, 'add_currencies' ] );
		add_filter( 'gettext', [ $this, 'paypal_tooltip' ] );
	}

	public function set_payment_options( array $settings ): array {

		$settings = array_combine( array_column( $settings, 'name' ), array_values( $settings ) );

		$settings['currency']['options']['IRT'] = 'تومان ایران (تومان)';
		$settings['currency']['default']        = 'IRT';

		$settings['currency_position']['default']       = 'right_space';
		$settings['wpuf_price_num_decimals']['default'] = 0;

		return array_values( $settings );
	}

	public function add_currencies( array $currencies ): array {

		$currencies[] = [
			'currency' => 'IRT',
			'label'    => 'تومان',
			'symbol'   => 'تومان',
		];

		$currencies[] = [
			'currency' => 'IRR',
			'label'    => 'ریال',
			'symbol'   => 'ریال',
		];

		return $currencies;
	}

	public function paypal_tooltip( $translation ) {

		if ( ! is_admin() ) {
			return $translation;
		}

		return str_replace( 'فعال کرن پرداخت دوره ای', 'فعال کرن پرداخت دوره ای (مخصوص پی‌پال)', $translation );
	}
}
