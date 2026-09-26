<?php


namespace Nabik\Gateland\Plugins\WalletForWoo;


use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Pay;
use ReflectionClass;
use WAL_Current_User_Wallet;
use WAL_Dashboard_Handler;
use WAL_Date_Time;
use WAL_Order_Handler;
use WAL_Topup_Bonus_Handler;
use WAL_Topup_Handler;
use WC_Customer;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {

		if ( ! class_exists( WAL_Topup_Handler::class ) ) {
			return;
		}

		// Remove autotopup
		add_filter( 'wal_dashboard_menus', [ $this, 'remove_autotopup' ], 1000, 1 );

		remove_action( 'wp_loaded', [ WAL_Topup_Handler::class, 'process_topup_form' ] );
		remove_action( 'woocommerce_checkout_update_order_meta', [ WAL_Order_Handler::class, 'maybe_add_topup_data' ] );
		remove_action( 'woocommerce_store_api_checkout_order_processed', [ WAL_Order_Handler::class, 'maybe_add_topup_data' ] );

		add_action( 'wp_loaded', [ $this, 'process_topup_form' ] );
		add_action( 'wp_loaded', [ $this, 'verify' ] );
	}

	public function remove_autotopup( $menus ) {

		if ( isset( $menus['autotopup'] ) ) {
			unset( $menus['autotopup'] );
		}

		return $menus;
	}

	public function process_topup_form() {

		if ( isset( $_POST['woocommerce-cart-nonce'] ) ) {
			return;
		}

		$nonce_value = isset( $_POST['wal-topup-nonce'] ) ? wc_clean( wp_unslash( $_POST['wal-topup-nonce'] ) ) : null;

		if ( ! isset( $_POST['wal-topup-action'] ) || empty( $_POST['wal-topup-action'] ) || ! wp_verify_nonce( $nonce_value, 'wal-topup' ) ) {
			return;
		}

		try {

			$amount = isset( $_POST['wal-topup-form-amount'] ) ? wc_clean( wp_unslash( $_POST['wal-topup-form-amount'] ) ) : '';
			// Return if the fund is empty.
			if ( ! $amount ) {
				throw new \Exception( get_option( 'wal_messages_wallet_topup_fund_empty' ) );
			}

			// Return if the fund is not numeric.
			if ( ! is_numeric( $amount ) ) {
				throw new \Exception( get_option( 'wal_messages_wallet_topup_fund_numeric' ) );
			}

			$incrementor = get_option( 'wal_general_topup_amount_incrementor' );

			if ( ! empty( $incrementor ) ) {

				$remainder = $amount % $incrementor;

				if ( ! empty( $remainder ) ) {

					$error_notice = get_option( 'wal_messages_error_notice_for_topup_incrementor' );
					$error_notice = str_replace( '{incrementor_value}', $incrementor, $error_notice );
					throw new \Exception( $error_notice );

				}

			}

			// Return if the topup is disabled.
			if ( 'no' == get_option( 'wal_general_enable_topup' ) ) {
				throw new \Exception( __( 'Currently, top-up form is disabled. Hence, you are unable to top-up your wallet.', 'wallet-for-woocommerce' ) );
			}

			// Return if the wallet is in active.
			if ( 'wal_inactive' == WAL_Current_User_Wallet::get_status() ) {
				throw new \Exception( get_option( 'wal_messages_topup_wallet_disabled' ) );
			}

			// Return if the user is not valid.
			if ( WAL_Topup_Handler::validate_user_restriction() ) {
				throw new \Exception( get_option( 'wal_messages_topup_wallet_restricted' ) );
			}

			$amount = wal_convert_price( $amount, true );

			// Return if the fund is less than minimum amount.
			$minimum_amount = floatval( get_option( 'wal_general_topup_min_amount' ) );

			if ( $minimum_amount && $minimum_amount > $amount ) {
				throw new \Exception( str_replace( '{topup_min_amount}', wal_convert_price_by_currency( $minimum_amount ), get_option( 'wal_messages_wallet_topup_minimum_amount' ) ) );
			}

			// Return if the fund is greater than maximum amount.
			$maximum_amount = floatval( get_option( 'wal_general_topup_max_amount' ) );

			if ( $maximum_amount && $maximum_amount < $amount ) {
				throw new \Exception( str_replace( '{topup_max_amount}', wal_convert_price_by_currency( $maximum_amount ), get_option( 'wal_messages_wallet_topup_maximum_amount' ) ) );
			}

			// Return if the fund is greater than maximum wallet amount.
			$max_wallet_balance = floatval( get_option( 'wal_general_topup_max_wallet_balance' ) );

			if ( $max_wallet_balance && $max_wallet_balance < ( $amount + WAL_Current_User_Wallet::get_balance() ) ) {
				throw new \Exception( str_replace( '{max_threshold_value}', wal_convert_price_by_currency( $max_wallet_balance ), get_option( 'wal_messages_wallet_topup_maximum_wallet_amount_error' ) ) );
			}

			// Return if the wallet topup count has reached the per day count.
			$max_count_per_day = floatval( get_option( 'wal_general_topup_count_per_day' ) );

			if ( $max_count_per_day && $max_count_per_day <= wal_customer_wallet_topup_count_per_day() ) {
				throw new \Exception( '' );
			}

			// Return if the wallet topup total has reached the per day total.
			$max_total_per_day = floatval( get_option( 'wal_general_topup_max_amount_per_day' ) );

			if ( $max_total_per_day && $max_total_per_day < $amount + wal_customer_wallet_topup_total_per_day() ) {

				$topup_balance_amount = $max_total_per_day - wal_customer_wallet_topup_total_per_day();
				$topup_balance_amount = ( $topup_balance_amount > 0 ) ? $topup_balance_amount : 0;

				$find    = [ '{topup_amount}', '{topup_balance_amount}' ];
				$replace = [ wal_convert_price_by_currency( $max_total_per_day ), wal_convert_price_by_currency( $topup_balance_amount ) ];

				throw new \Exception( str_replace( $find, $replace, get_option( 'wal_messages_wallet_topup_maximum_amount_per_day_error' ) ) );

			}

			$customer = self::get_customer();

			$currency = get_woocommerce_currency();

			// Transaction $amount : Based on shop currency
			$transaction_id = self::create_transaction_log( $amount );

			// Gateway $amount : Based on IRT
			if ( $currency == 'IRR' ) {
				$amount /= 10;
			} elseif ( $currency == 'IRHR' ) {
				$amount *= 100;
			} elseif ( $currency == 'IRHT' ) {
				$amount *= 1000;
			}

			$callback = add_query_arg( [
				'action'         => 'wallet_for_woo_topup',
				'transaction_id' => $transaction_id,
				'secret'         => hash( 'crc32', $transaction_id . AUTH_KEY ),
			], wc_get_page_permalink( 'wal_dashboard' ) );

			$mobiles = array_filter( array_unique( [
				$customer->get_billing_phone(),
				$customer->get_shipping_phone(),
			] ) );


			$data = [
				'amount'      => $amount,
				'client'      => 'wallet_for_woo',
				'user_id'     => $customer->get_id(),
				'order_id'    => $transaction_id,
				'callback'    => $callback,
				'description' => sprintf( 'شارژ کیف پول ووکامرس - %s', $customer->get_display_name() ),
				'mobile'      => $mobiles[0] ?? null,
				'currency'    => CurrenciesEnum::IRT,
			];

			try {
				$response = Pay::request( $data );
			} catch ( \Exception $e ) {
				throw new \Exception( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.' );
			}

			if ( ! $response['success'] ) {
				throw new \Exception( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] );
			}

			update_post_meta( $transaction_id, 'gateland_wallet_authority', strval( $response['data']['authority'] ) );

			wp_safe_redirect( $response['data']['payment_link'] );

			exit();

		} catch ( \Exception $ex ) {
			WAL_Dashboard_Handler::add_error( $ex->getMessage() );
		}
	}

	public function verify() {

		if ( ( $_GET['action'] ?? null ) != 'wallet_for_woo_topup' ) {
			return;
		}

		try {

			$transaction_id  = intval( $_GET['transaction_id'] ?? 0 );
			$transaction_log = wal_get_transaction_log( $transaction_id );

			if ( empty( $transaction_log ) || $transaction_log->get_status() !== 'wal_pending' ) {
				throw new \Exception( 'تراکنش نامعتبر است.' );
			}

			$secret = sanitize_text_field( $_GET['secret'] ?? null );

			if ( $secret != hash( 'crc32', $transaction_id . AUTH_KEY ) ) {
				throw new \Exception( 'کلید امنیتی صحیح نمی‌باشد.' );
			}

			$authority = get_post_meta( $transaction_id, 'gateland_wallet_authority', true );

			if ( empty( $authority ) ) {
				throw new \Exception( 'تراکنش یافت نشد.' );
			}

			$response = Pay::verify( $authority, 'wallet_for_woo' );

			if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

				$amount = $response['data']['amount'];
				$this->maybe_credit_topup_fund( $amount, $transaction_log );

				$message = sprintf( 'کیف‌پول به مبلغ %s شارژ شد.', wp_strip_all_tags( wc_price( $amount ) ) );
				wc_add_notice( $message );
				wp_safe_redirect( wc_get_page_permalink( 'wal_dashboard' ) );
				exit();

			}

			$message = sprintf( 'پرداخت تراکنش %d ناموفق بود.', $authority );

			throw new \Exception( $message );

		} catch ( \Exception $e ) {

			WAL_Dashboard_Handler::add_error( $e->getMessage() );

		}
	}

	public static function maybe_credit_topup_fund( $amount, $transaction_log ) {

		if ( ! $amount ) {
			return;
		}

		$transaction_id = $transaction_log->get_id();

		$customer = self::get_customer();

		$args = [
			'user_id'            => $customer->get_id(),
			'amount'             => floatval( $amount ),
			'event_id'           => 3,
			'event_message'      => sprintf( 'شارژ کیف پول ووکامرس به مبلغ %s', $amount ),
			'currency'           => get_woocommerce_currency(),
			'update_topup_total' => true,
			'transaction_log'    => $transaction_log
		];

		wal_update_transaction_log(
			$transaction_id,
			[],
			[ 'post_status' => 'wal_credit' ]
		);

		self::wal_credit_wallet_fund( $args );

		self::maybe_award_topup_bonus( $amount );
	}

	public static function maybe_award_topup_bonus( $amount ) {
		$customer           = self::get_customer();
		$topup_bonus_amount = WAL_Topup_Bonus_Handler::get_amount( $amount );

		if ( empty( $topup_bonus_amount ) ) {
			return;
		}

		$matched_rule_id = WAL_Topup_Bonus_Handler::get_matched_rule_id();
		$previous_ids    = get_user_meta( $customer->get_id(), 'wal_awarded_topup_rule_ids', true );
		$awarded_rules   = wal_check_is_array( $previous_ids ) ? array_merge( $previous_ids, [ $matched_rule_id ] ) : [ $matched_rule_id ];
		update_user_meta( $customer->get_id(), 'wal_awarded_topup_rule_ids', $awarded_rules );

		$args = [
			'user_id'            => $customer->get_id(),
			'amount'             => $topup_bonus_amount,
			'event_id'           => 21,
			'event_message'      => wal_topup_bonus_fund_credit_log_label(),
			'currency'           => get_woocommerce_currency(),
			'update_topup_total' => true,
		];

		wal_credit_wallet_fund( $args );

		$note = sprintf( __( 'The topup bonus amount of %1$s credited to user %2$s', 'wallet-for-woocommerce' ), wal_price( $topup_bonus_amount ), $customer->get_display_name() );

		wc_add_notice( $note );
	}

	public static function wal_credit_wallet_fund( $args ) {

		$default_args = [
			'user_id'            => get_current_user_id(),
			'amount'             => 0,
			'event_id'           => 1,
			'event_message'      => '',
			'currency'           => get_woocommerce_currency(),
			'update_usage_total' => false,
			'update_topup_total' => false,
			'mode'               => 'automatic',
		];

		$args = wp_parse_args( $args, $default_args );

		$wallet_id = wal_get_wallet_id_by_user_id( $args['user_id'] );

		// Update the wallet details if the wallet ID exists.
		if ( $wallet_id ) {

			$wallet = wal_get_wallet( $wallet_id );

			$meta_args = [
				'wal_balance'              => floatval( $wallet->get_balance() ) + floatval( $args['amount'] ),
				'wal_current_expiry_date'  => wal_get_wallet_current_expiry_date(),
				'wal_previous_expiry_date' => $wallet->get_current_expiry_date(),
			];

			if ( $args['update_usage_total'] ) {
				$meta_args['wal_usage_total'] = floatval( $wallet->get_usage_total() ) - floatval( $args['amount'] );
			}

			if ( $args['update_topup_total'] ) {
				$meta_args['wal_topup_total'] = floatval( $wallet->get_topup_total() ) + floatval( $args['amount'] );
			}

			wal_update_wallet( $wallet_id, $meta_args, [ 'post_status' => 'wal_active' ] );

		} else {

			// Create the wallet ID by user ID if wallet ID does not exists.
			$meta_args = [
				'wal_balance'             => $args['amount'],
				'wal_current_expiry_date' => wal_get_wallet_current_expiry_date(),
				'wal_currency'            => get_woocommerce_currency(),
			];

			if ( $args['update_topup_total'] ) {
				$meta_args['wal_topup_total'] = $args['amount'];
			}

			$wallet_id = wal_create_new_wallet( $meta_args, [ 'post_parent' => $args['user_id'] ] );

		}

		$transaction_meta_args = [
			'wal_user_id'       => $args['user_id'],
			'wal_event_id'      => $args['event_id'],
			'wal_event_message' => $args['event_message'],
			'wal_amount'        => $args['amount'],
			'wal_total'         => $meta_args['wal_balance'],
			'wal_currency'      => $args['currency'],
		];

		$transaction_log = $args['transaction_log'];

		wal_update_transaction_log(
			$transaction_log->get_id(),
			$transaction_meta_args,
			[ 'post_status' => 'wal_credit' ]
		);

		//Update last wallet activity.
		update_post_meta( $wallet_id, 'wal_last_activity_date', WAL_Date_Time::get_mysql_date_time_format( 'now', true ) );

		if ( $transaction_log->has_status( 'wal_credit' ) ) {
			return $transaction_log;
		}

		return false;
	}

	/** @throws \Exception */
	public static function create_transaction_log( $amount ) {
		$customer  = self::get_customer();
		$wallet_id = wal_get_wallet_id_by_user_id( $customer->get_id() );

		if ( ! $wallet_id ) {

			$wallet_id = wal_create_new_wallet( [
				'wal_balance'             => 0,
				'wal_current_expiry_date' => wal_get_wallet_current_expiry_date(),
				'wal_currency'            => get_woocommerce_currency(),
			], [ 'post_parent' => $customer->get_id() ] );

		}

		$transaction_meta_args = [
			'wal_user_id'       => $customer->get_id(),
			'wal_event_id'      => 3,
			'wal_event_message' => sprintf( 'شارژ کیف پول ووکامرس - %s', $customer->get_display_name() ),
			'wal_amount'        => $amount,
			'wal_total'         => floatval( wal_get_wallet( $wallet_id )->get_balance() ),
			'wal_currency'      => get_woocommerce_currency(),
		];

		return wal_create_new_transaction_log(
			$transaction_meta_args,
			[
				'post_parent' => $wallet_id,
				'post_status' => 'wal_pending'
			]
		);
	}

	/** @throws \Exception */
	public static function get_customer(): WC_Customer {
		try {
			return new WC_Customer( get_current_user_id() );
		} catch ( \Exception $e ) {
			throw new \Exception( 'برای شارژ کیف پول وارد حساب کاربری خود شوید.' );
		}
	}
}
