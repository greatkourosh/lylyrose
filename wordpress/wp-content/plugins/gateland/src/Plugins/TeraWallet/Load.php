<?php

namespace Nabik\Gateland\Plugins\TeraWallet;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;
use WC_Customer;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_filter( 'nabik/gateland/transaction_clients', [ $this, 'add_client' ], 10, 1 );
		add_filter( 'nabik/gateland/transaction_client_order_url', [ $this, 'client_order_url' ], 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'redirect' ], 9, 1 );
		add_action( 'init', [ $this, 'callback' ] );

	}

	public function add_client( array $clients ): array {

		$clients['tera_wallet'] = 'کیف‌پول';

		return $clients;
	}

	public function client_order_url( $url, Transaction $transaction ) {

		if ( $transaction->client === 'tera_wallet' ) {
			$url = admin_url( sprintf( 'admin.php?page=woo-wallet-transactions&user_id=%d', $transaction->user_id ) );
		}

		return $url;
	}

	public function redirect( $cart_item_data ) {

		if ( ! isset( $_POST['woo_wallet_balance_to_add'] ) ) {
			return $cart_item_data;
		}

		try {
			$customer = new WC_Customer( get_current_user_id() );
		} catch ( \Exception $e ) {
			wp_die( 'برای شارژ کیف پول وارد حساب کاربری خود شوید.' );
		}

		$amount = intval( $_POST['woo_wallet_balance_to_add'] );

		$currency = get_woocommerce_currency();

		$transaction_id = \Nabik_Net_Database::DB()->table( 'woo_wallet_transactions' )->insertGetId( [
			'blog_id'    => get_current_blog_id(),
			'user_id'    => $customer->get_id(),
			'type'       => 'credit',
			'amount'     => $amount,
			'currency'   => $currency,
			'details'    => '',
			'deleted'    => 1,
			'date'       => current_time( 'mysql' ),
			'created_by' => $customer->get_id(),
		] );

		if ( $currency == 'IRR' ) {
			$amount /= 10;
		} elseif ( $currency == 'IRHR' ) {
			$amount *= 100;
		} elseif ( $currency == 'IRHT' ) {
			$amount *= 1000;
		}

		$callback = add_query_arg( [
			'action'         => 'gateland_charge_wallet',
			'transaction_id' => $transaction_id,
			'secret'         => hash( 'crc32', $transaction_id . AUTH_KEY ),
		], $this->get_return_url() );

		$mobiles = array_filter( array_unique( [
			$customer->get_billing_phone(),
			$customer->get_shipping_phone(),
		] ) );

		$data = [
			'amount'      => $amount,
			'client'      => 'tera_wallet',
			'user_id'     => $customer->get_id(),
			'order_id'    => $transaction_id,
			'callback'    => $callback,
			'description' => sprintf( 'شارژ کیف پول - %s', $customer->get_display_name() ),
			'mobile'      => $mobiles[0] ?? null,
			'currency'    => CurrenciesEnum::IRT,
		];

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {

			$message = 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.';

			wc_add_notice( $message, 'error' );

			wp_redirect( $this->get_return_url() );
			exit;
		}

		if ( ! $response['success'] ) {

			$message = sprintf( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] );

			wc_add_notice( $message, 'error' );

			wp_redirect( $this->get_return_url() );
			exit;
		}

		update_wallet_transaction_meta( $transaction_id, '_authority', $response['data']['authority'] );

		wp_redirect( $response['data']['payment_link'] );
		exit;

	}

	public function callback() {

		if ( ( $_GET['action'] ?? null ) != 'gateland_charge_wallet' ) {
			return;
		}

		$transaction_id = intval( $_GET['transaction_id'] ?? 0 );
		$secret         = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret != hash( 'crc32', $transaction_id . AUTH_KEY ) ) {
			wp_die( 'کلید امنیتی صحیح نمی‌باشد.' );
		}

		$transaction = \Nabik_Net_Database::DB()->table( 'woo_wallet_transactions' )
		                                  ->where( 'transaction_id', $transaction_id )
		                                  ->first();

		if ( is_null( $transaction ) ) {
			wp_die( 'تراکنش یافت نشد.' );
		}

		if ( $transaction->deleted == 0 ) {

			$message = sprintf( 'تراکنش %d قبلا تایید شده است.', $transaction_id );

			wc_add_notice( $message, 'error' );

			wp_redirect( $this->get_return_url() );
			exit;
		}

		$authority = get_wallet_transaction_meta( $transaction_id, '_authority' );

		$response = Pay::verify( $authority, 'tera_wallet' );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$balance = woo_wallet()->wallet->get_wallet_balance( $transaction->user_id, 'edit' ) + $transaction->amount;
			update_user_meta( $transaction->user_id, '_current_woo_wallet_balance', $balance );

			\Nabik_Net_Database::DB()
			                   ->table( 'woo_wallet_transactions' )
			                   ->where( 'transaction_id', $transaction_id )
			                   ->update( [
				                   'details' => sprintf( __( 'کیف‌پول از طریق تراکنش #%s شارژ شد.', 'gateland' ), $authority ),
				                   'deleted' => 0,
			                   ] );

			update_wallet_transaction_meta( $transaction_id, '_type', 'credit_purchase', $transaction->user_id );

			do_action( 'woo_wallet_transaction_recorded', $transaction_id, $transaction->user_id, $transaction->amount, 'credit' );

			$message = sprintf( 'کیف‌پول به مبلغ %s شارژ شد.', wp_strip_all_tags( wc_price( $transaction->amount ) ) );

			wc_add_notice( $message );

			wp_redirect( $this->get_return_url() );
			exit;
		}

		$message = sprintf( 'پرداخت تراکنش %d ناموفق بود.', $authority );

		wc_add_notice( $message, 'error' );

		wp_redirect( $this->get_return_url() );
		exit;
	}

	private function get_return_url(): string {
		return wc_get_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ), 'add', wc_get_page_permalink( 'myaccount' ) );
	}
}
