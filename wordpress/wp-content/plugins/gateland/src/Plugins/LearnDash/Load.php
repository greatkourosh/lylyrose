<?php

namespace Nabik\Gateland\Plugins\LearnDash;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_filter( 'learndash_currency_code_list', [ $this, 'add_currencies' ] );
		add_filter( 'learndash_payment_gateways', [ $this, 'add_gateway' ] );
		add_action( 'learndash_settings_sections_init', [ Settings::class, 'add_section_instance' ] );
		add_filter( 'learndash_model_product_display_price', [ $this, 'format_price' ], 10, 3 );
	}

	public function format_price( $display_price, $price, $product ) {
		$currency_code = strtoupper( strval( learndash_get_currency_code() ) );
		$price         = str_replace( [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ], [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], strval( $price ) );
		$price         = floatval( preg_replace( '/[^0-9.]/', '', $price ) );

		switch ( $currency_code ) {
			case 'IRT':
				return number_format_i18n( $price ) . ' تومان';

			case 'IRHR':
				return number_format_i18n( $price ) . ' هزار ریال';

			case 'IRHT':
				return number_format_i18n( $price ) . ' هزار تومان';

			default:
				return learndash_get_currency_symbol( $currency_code ) . ' ' . number_format_i18n( $price );
		}

	}

	public function add_currencies( $currency_codes ): array {

		return array_merge( $currency_codes, [
			[
				'currency_code' => 'IRT',
				'country'       => 'ایران',
				'option_label'  => 'ایران (تومان)',
				'currency'      => 'تومان',
			],
			[
				'currency_code' => 'IRHR',
				'country'       => 'ایران',
				'option_label'  => 'ایران (هزار ریال)',
				'currency'      => 'هزار ریال',
			],
			[
				'currency_code' => 'IRHT',
				'country'       => 'ایران',
				'option_label'  => 'ایران (هزار تومان)',
				'currency'      => 'هزار تومان',
			],
		] );
	}

	public function add_gateway( $gateways ) {
		$options = get_option( 'learndash_settings_gateland', [] );

		if ( ( $options['enabled'] ?? 'no' ) !== 'yes' ) {
			return $gateways;
		}

		if ( empty( $options['gateway_id'] ) ) {
			$options['gateway_id'] = 0;
		}

		$gateways[] = new Gateway( $options['gateway_id'] );

		return $gateways;
	}

}