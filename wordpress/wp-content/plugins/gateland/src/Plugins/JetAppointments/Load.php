<?php

namespace Nabik\Gateland\Plugins\JetAppointments;

use Exception;
use JET_APB\Form;
use JET_APB\Plugin;
use JET_APB\Resources\Appointment_Model;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

class Load {

	protected static ?Load $_instance = null;

	public function __construct() {

		if ( ! class_exists( \JET_APB\Plugin::class ) ) {
			return;
		}

		add_action( 'after_setup_theme', [ $this, 'modify_processing_hooks' ], 1 );
		add_action( 'init', [ $this, 'verify' ] );
		add_filter( 'jet-engine/forms/booking/message-types', [ $this, 'set_custom_messages' ], 10, 1 );
		add_filter( 'jet-apb/admin/helpers/page-config/config', [ $this, 'related_order_to_transaction' ], 10, 2 );

	}

	public static function instance(): ?Load {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function modify_processing_hooks() {
		$plugin_instance = Plugin::instance();

		if ( $plugin_instance && isset( $plugin_instance->wc ) ) {
			remove_action( 'jet-apb/form/notification/success', [ $plugin_instance->wc, 'process_wc_notification' ] );
			remove_action( 'jet-apb/jet-fb/action/success', [ $plugin_instance->wc, 'process_wc_notification' ] );
			remove_filter( 'woocommerce_get_item_data', [ $plugin_instance->wc, 'add_formatted_cart_data' ] );
			remove_filter( 'woocommerce_cart_contents_changed', [ $plugin_instance->wc, 'set_appointment_price' ] );
			remove_filter( 'woocommerce_checkout_get_value', [ $plugin_instance->wc, 'maybe_set_checkout_defaults' ] );
			remove_action( 'woocommerce_checkout_order_processed', [ $plugin_instance->wc, 'process_order' ] );
			remove_action( 'woocommerce_store_api_checkout_order_processed', [ $plugin_instance->wc, 'process_order_by_api' ] );
			remove_action( 'woocommerce_thankyou', [ $plugin_instance->wc, 'order_details' ], 0 );
			remove_action( 'woocommerce_view_order', [ $plugin_instance->wc, 'order_details' ], 0 );
			remove_action( 'woocommerce_email_order_meta', [ $plugin_instance->wc, 'email_order_details' ], 0 );
			remove_action( 'woocommerce_admin_order_data_after_shipping_address', [ $plugin_instance->wc, 'admin_order_details' ] );
			remove_action( 'woocommerce_order_status_changed', [ $plugin_instance->wc, 'update_status_on_order_update' ] );
			remove_action( 'jet-apb/db/update/appointments', [ $plugin_instance->wc, 'update_order_on_status_update' ] );
			remove_filter( 'woocommerce_cart_contents_changed', [ $plugin_instance->wc, 'set_appointment_price' ] );
			remove_filter( 'woocommerce_get_item_data', [ $plugin_instance->wc, 'add_formatted_cart_data' ] );

			add_action( 'jet-apb/form/notification/success', [ $this, 'process_form' ], 11, 2 );
			add_action( 'jet-apb/jet-fb/action/success', [ $this, 'process_form' ], 11, 2 );
		}

	}

	/**
	 * @param Appointment_Model $appointment
	 * @param Form $action
	 *
	 * @return void
	 */
	public function process_form( Appointment_Model $appointment, Form $action ) {
		$form_data        = $action->getInstance()->data;
		$page_id          = $form_data['page_id'] ?? $form_data['post_id'] ?? 0;
		$page_link        = self::redirect_link( $page_id );
		$appointment_data = $appointment->get_data();
		// Assume all payments are based on IRT
		$price          = $appointment_data['price'] ?? null;
		$service_title  = $appointment_data['serviceTitle'] ?? null;
		$date           = $appointment_data['appointment_date'] ?? null;
		$status         = $appointment_data['status'] ?? 'unknown';
		$user_id        = $appointment_data['user_id'] ?? null;
		$appointment_id = $appointment_data['ID'];

		if ( $status !== 'on-hold' || empty( $price ) || $price < 1000 || empty( $appointment_id ) ) {
			return;
		}

		$phone = $appointment_data['phone'] ?? null;

		$callback = add_query_arg( [
			'action'      => Transaction::CLIENT_JET_APPOINTMENTS,
			'appointment' => $appointment_id,
			'secret'      => hash( 'crc32', $appointment_id . AUTH_KEY ),
			'page'        => $page_id,
		], $page_link );

		$data = [
			'amount'      => $price,
			'client'      => 'jet_appointments',
			'user_id'     => $user_id,
			'order_id'    => $appointment_id,
			'callback'    => $callback,
			'description' => sprintf( 'رزرو نوبت - %s - تاریخ %s', $service_title, $date ),
			'mobile'      => $phone,
			'currency'    => CurrenciesEnum::IRT,
		];

		try {
			$response = Pay::request( $data );
		} catch ( Exception $e ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_failed_connection' ) );
			exit;
		}

		if ( ! $response['success'] ) {
			wp_redirect( self::redirect_link( $page_id, sprintf( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] ) ) );
			exit;
		}

		$appointment->set( 'order_id', $response['data']['authority'] );
		$appointment->save();

		$action->filterQueryArgs( function ( $query_args, $handler, $args ) use ( $action, $response ) {

			$url = apply_filters( 'jet-engine/forms/handler/wp_redirect_url', $response['data']['payment_link'] );

			if ( $action->isAjax() ) {
				$query_args['redirect'] = $url;

				return $query_args;
			} else {
				wp_redirect( $url ); // phpcs:ignore

				die();
			}

		} );

	}

	public function verify() {

		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== Transaction::CLIENT_JET_APPOINTMENTS || ! isset( $_GET['appointment'] ) || ! isset( $_GET['secret'] ) || ! isset( $_GET['page'] ) ) {
			return;
		}

		$page_id        = intval( $_GET['page'] ?? 0 );
		$appointment_id = intval( $_GET['appointment'] ?? 0 );
		$secret         = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret != hash( 'crc32', $appointment_id . AUTH_KEY ) ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_secret_error' ) );
		}

		/**@var Appointment_Model $appointment */
		$appointment = new Appointment_Model( [], $appointment_id );

		$appointment_data = $appointment->get_data();

		if ( empty( $appointment ) ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_appointment_not_found' ) );
			exit;
		}

		if ( $appointment_data['status'] !== 'on-hold' ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_already_processed' ) );
			exit;
		}

		$authority = $appointment_data['order_id'];

		$response = Pay::verify( $authority, Transaction::CLIENT_JET_APPOINTMENTS );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$appointment->set( 'status', 'processing' );
			$appointment->save();

			wp_redirect( self::redirect_link( $page_id, 'success' ) );
			exit;
		}

		wp_redirect( self::redirect_link( $page_id, sprintf( 'پرداخت شما با شناسه پرداخت %s ناموفق شد. لطفا مجددا تلاش کنید.', $authority ) ) );
		exit;
	}

	public function set_custom_messages( $messages ) {

		$messages['gateland_failed_connection'] = [
			'label'   => 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.',
			'default' => 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.'
		];

		$messages['gateland_already_processed'] = [
			'label'   => 'تراکنش قبلا پردازش شده است.',
			'default' => 'تراکنش قبلا پردازش شده است.'
		];

		$messages['gateland_appointment_not_found'] = [
			'label'   => 'رزرو نوبت، یافت نشد.',
			'default' => 'رزرو نوبت، یافت نشد.'
		];

		$messages['gateland_secret_error'] = [
			'label'   => 'کلید امنیتی صحیح نمی‌باشد.',
			'default' => 'کلید امنیتی صحیح نمی‌باشد.'
		];

		return $messages;
	}

	public function related_order_to_transaction( array $config, string $handle ): array {

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
