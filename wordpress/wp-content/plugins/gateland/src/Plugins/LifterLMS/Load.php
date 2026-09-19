<?php

namespace Nabik\Gateland\Plugins\LifterLMS;

use Nabik\Gateland\Models\Transaction;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_filter( 'nabik/gateland/transaction_clients', [ $this, 'add_client' ] );
		add_filter( 'nabik/gateland/transaction_client_order_url', [ $this, 'client_order_url' ], 10, 2 );
		add_filter( 'lifterlms_currencies', [ $this, 'add_currencies' ] );
		add_filter( 'lifterlms_currency_symbols', [ $this, 'add_symbols' ] );
		add_filter( 'lifterlms_payment_gateways', [ $this, 'register' ] );
		add_action( 'lifterlms_after_checkout_confirm_form', [ $this, 'auto_confirm' ] );
	}

	public function add_client( array $clients ): array {

		$clients['lifter_lms'] = 'لیفتر';

		return $clients;
	}

	public function client_order_url( $url, Transaction $transaction ) {

		if ( $transaction->client === 'lifter_lms' ) {
			$url = get_edit_post_link( $transaction->order_id );
		}

		return $url;
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

	public function register( array $gateways ): array {

		$gateways[] = Gateway::class;

		return $gateways;
	}

	public function auto_confirm() {
		?>
		<script>
            jQuery('form.llms-checkout').submit();
		</script>
		<?php
	}
}