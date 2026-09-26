<?php


namespace Nabik\Gateland\Plugins\JetBooking;

use JET_ABAF\Price;
use JET_ABAF\Compatibility\Packages\Jet_Engine\Forms\Manager;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

class Load {
	protected static ?Load $_instance = null;

	public function __construct() {

		if ( ! class_exists( \JET_ABAF\Plugin::class ) ) {
			return;
		}

		add_action( 'init', [ $this, 'modify_processing_hooks' ], - 999 );
		add_action( 'init', [ $this, 'verify' ] );
		add_filter( 'jet-engine/forms/booking/message-types', [ $this, 'set_custom_messages' ], 10, 1 );
		add_filter( 'jet-abaf/dashboard/helpers/page-config/config', [ $this, 'related_order_to_transaction' ], 10, 1 );
	}

	public static function instance(): ?Load {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function modify_processing_hooks() {
		// todo : maybe remove manager hooks too?
		$jet_abaf_wc    = jet_abaf()->wc;
		$jet_abaf_plain = $jet_abaf_wc->mode; // The plain mode option for WooCommerce integration

		remove_filter( 'jet-abaf/dashboard/helpers/page-config/config', [ $jet_abaf_plain, 'set_booking_page_config' ] );
		remove_action( 'jet-abaf/form/notification/success', [ $jet_abaf_plain, 'process_wc_notification' ] );
		remove_action( 'jet-abaf/jet-fb/action/success', [ $jet_abaf_plain, 'process_wc_notification' ] );
		remove_filter( 'woocommerce_get_cart_contents', [ $jet_abaf_plain, 'set_booking_price' ] );
		remove_filter( 'woocommerce_cart_item_name', [ $jet_abaf_plain, 'set_booking_item_name' ] );
		remove_filter( 'woocommerce_checkout_get_value', [ $jet_abaf_plain, 'maybe_set_checkout_defaults' ] );
		remove_filter( 'woocommerce_cart_item_permalink', [ $jet_abaf_plain, 'woocommerce_cart_item_permalink' ] );
		remove_filter( 'woocommerce_cart_item_thumbnail', [ $jet_abaf_plain, 'woocommerce_cart_item_thumbnail' ] );
		remove_filter( 'woocommerce_order_item_name', [ $jet_abaf_plain, 'set_booking_item_name' ] );
		remove_action( 'woocommerce_thankyou', [ $jet_abaf_plain, 'order_details' ], 0 );
		remove_action( 'woocommerce_view_order', [ $jet_abaf_plain, 'order_details' ], 0 );
		remove_action( 'woocommerce_email_order_meta', [ $jet_abaf_plain, 'email_order_details' ], 0 );
		remove_action( 'add_meta_boxes', [ $jet_abaf_plain, 'admin_order_booking_details' ] );
		remove_action( 'jet-booking/rest-api/add-booking/set-related-order-data', [ $jet_abaf_plain, 'set_booking_related_order' ] );
		remove_action( 'woocommerce_checkout_order_processed', [ $jet_abaf_plain, 'process_order' ], 20 );
		remove_action( 'woocommerce_store_api_checkout_order_processed', [ $jet_abaf_plain, 'process_order_by_api' ], 20 );
		remove_action( 'wp_login', [ $jet_abaf_plain, 'reset_saved_cart_after_login' ], 20 );

		if ( jet_abaf()->settings->get( 'wc_sync_orders' ) ) {

			remove_action( 'jet-booking/db/booking-updated', [ $jet_abaf_plain, 'update_order_on_status_update' ] );
			remove_action( 'jet-booking/wc-integration/before-update-status', [ $jet_abaf_plain, 'validate_booking_update' ] );
			remove_action( 'jet-booking/wc-integration/before-set-order-data', [ $jet_abaf_plain, 'validate_booking_update' ] );

		}

		add_action( 'jet-abaf/form/notification/success', [ $this, 'process_form' ], 10, 3 );
		add_action( 'jet-abaf/jet-fb/action/success', [ $this, 'process_form' ], 10, 3 );
	}

	public function process_form( array $booking, Manager $action, array $bookings ) {

		$form_data = $action->getInstance()->data;

		$page_id = $form_data['page_id'];

		$page_link = self::redirect_link( $page_id );

		// Assume all payments are based on IRT
		$price_obj = new Price( $booking['apartment_id'] );

		try {
			$price = $price_obj->get_booking_price( $booking );
		} catch ( \Exception $e ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_price_error' ) );
		}

		$check_in_date  = $form_data['_check_in_date'] ?? null;
		$check_out_date = $form_data['_check_out_date'] ?? null;
		$status         = $booking['status'] ?? 'unknown';
		$user_id        = $booking['user_id'] ?? null;
		$booking_id     = $booking['booking_id'];

		if ( $status !== 'pending' || empty( $price ) || $price < 1000 || empty( $booking_id ) ) {
			return;
		}

		$phone = $booking['phone'] ?? null;

		$callback = add_query_arg( [
			'action'  => Transaction::CLIENT_JET_BOOKING,
			'booking' => $booking_id,
			'page'    => $page_id,
			'secret'  => hash( 'crc32', $booking_id . AUTH_KEY ),
		], $page_link );

		$data = [
			'amount'      => $price,
			'client'      => Transaction::CLIENT_JET_BOOKING,
			'user_id'     => $user_id,
			'order_id'    => $booking_id,
			'callback'    => $callback,
			'description' => sprintf( 'رزرو نوبت - شروع از تاریخ %s و پایان در تاریخ %s', $check_in_date, $check_out_date ),
			'mobile'      => $phone,
			'currency'    => CurrenciesEnum::IRT,
		];

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_failed_connection' ) );
			exit;
		}

		if ( ! $response['success'] ) {
			wp_redirect( self::redirect_link( $page_id, sprintf( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] ) ) );
			exit;
		}

		// Set transaction authority as order_id
		jet_abaf()->db->update_booking( $booking['booking_id'], [ 'order_id' => $response['data']['authority'] ] );

		$action->filterQueryArgs( function ( $query_args, $handler, $args ) use ( $action, $response ) {
			$url = apply_filters( 'jet-engine/forms/handler/wp_redirect_url', $response['data']['payment_link'] );

			if ( $action->isAjax() ) {
				$query_args['redirect'] = $url;

				return $query_args;
			} else {
				wp_safe_redirect( $url );
				die();
			}
		} );

	}

	public function verify() {

		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== Transaction::CLIENT_JET_BOOKING || ! isset( $_GET['booking'] ) || ! isset( $_GET['secret'] ) || ! isset( $_GET['page'] ) ) {
			return;
		}

		$page_id    = intval( $_GET['page'] ?? 0 );
		$booking_id = intval( $_GET['booking'] ?? 0 );
		$secret     = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret != hash( 'crc32', $booking_id . AUTH_KEY ) ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_secret_error' ) );
			exit;
		}

		$booking = jet_abaf_get_booking( $booking_id );

		if ( empty( $booking ) ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_booking_not_found' ) );
			exit;
		}

		if ( $booking->status !== 'pending' ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_already_processed' ) );
			exit;
		}

		$authority = $booking->order_id;

		$response = Pay::verify( $authority, Transaction::CLIENT_JET_BOOKING );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {
			// todo : processing or completed?
			jet_abaf()->db->update_booking( $booking->ID, [ 'status' => 'processing' ] );

			wp_redirect( self::redirect_link( $page_id, 'success' ) );
			exit;
		}

		wp_redirect( self::redirect_link( $page_id, sprintf( 'پرداخت شما با شناسه پرداخت %s ناموفق شد. لطفا مجددا تلاش کنید.', $authority ) ) );
		exit;
	}

	public function set_custom_messages( $messages ) {

		$messages['gateland_price_error'] = [
			'label'   => 'خطایی در محاسبه هزینه رخ داده است.',
			'default' => 'خطایی در محاسبه هزینه رخ داده است.'
		];

		$messages['gateland_failed_connection'] = [
			'label'   => 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.',
			'default' => 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.'
		];

		$messages['gateland_already_processed'] = [
			'label'   => 'تراکنش قبلا پردازش شده است.',
			'default' => 'تراکنش قبلا پردازش شده است.'
		];

		$messages['gateland_booking_not_found'] = [
			'label'   => 'رزرواسیون، یافت نشد.',
			'default' => 'رزرواسیون، یافت نشد.'
		];

		$messages['gateland_secret_error'] = [
			'label'   => 'کلید امنیتی صحیح نمی‌باشد.',
			'default' => 'کلید امنیتی صحیح نمی‌باشد.'
		];

		return $messages;
	}

	public function related_order_to_transaction( array $config ): array {

		$config['edit_link'] = add_query_arg( [
			'page'           => 'gateland-transaction',
			'transaction_id' => '%id%',
		], admin_url( 'admin.php' ) );

		return $config;
	}

	public static function redirect_link( int $page_id = 0, string $status = '' ): string {
		$page_link = home_url();

		if ( ! empty( $page_id ) ) {
			$page_link = get_permalink( $page_id );
		}

		if ( ! empty( $status ) ) {
			return add_query_arg( [
				'status' => $status
			], $page_link );
		}

		return $page_link;
	}
}
