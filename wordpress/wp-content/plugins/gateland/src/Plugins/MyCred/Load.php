<?php

namespace Nabik\Gateland\Plugins\MyCred;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_action( 'mycred_buycred_load_gateways', [ $this, 'include_gateway' ] );
		add_filter( 'mycred_setup_gateways', [ $this, 'setup_gateway' ] );
		add_filter( 'mycred_buycred_refs', [ $this, 'buycred_refs' ] );
		add_filter( 'mycred_buycred_log_refs', [ $this, 'buycred_log_refs' ] );
		add_filter( 'mycred_dropdown_currencies', [ $this, 'currencies' ] );
		add_filter( 'mycred_buycred_display_user_amount', [ $this, 'format_amount' ], 10, 1 );
		add_filter( 'mycred_buycred_order_table_rows', [ $this, 'replace_currency' ], 10, 1 );
	}

	public function include_gateway() {
		require_once 'Gateway.php';
	}

	public function setup_gateway( array $installed ): array {

		$installed['gateland'] = [
			'title'    => get_option( 'gateland_mycred_title', 'گیت‌لند - پرداخت آنلاین هوشمند' ),
			'callback' => [ Gateway::class ],
		];

		return $installed;
	}

	public function buycred_refs( array $addons ): array {

		$addons['buy_creds_with_gateland'] = 'خرید با پرداخت آنلاین';

		return $addons;
	}

	public function buycred_log_refs( array $references ): array {

		$references[] = 'buy_creds_with_gateland';

		return $references;
	}

	public function currencies( array $currencies ): array {

		$currencies['ریال']  = 'ریال';
		$currencies['تومان'] = 'تومان';

		return $currencies;
	}

	public function format_amount( $amount ): string {
		return number_format( $amount, 0 );
	}

	public function replace_currency( $contents ) {

		if ( is_array( $contents ) ) {

			foreach ( $contents as &$content ) {
				$content = $this->replace_currency( $content );
			}

			return $contents;
		}

		return str_replace( [ 'IRT', 'IRR' ], [ '&nbsp;تومان', '&nbsp;ریال' ], $contents );
	}
}
