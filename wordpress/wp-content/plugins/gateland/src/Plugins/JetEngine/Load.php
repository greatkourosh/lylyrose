<?php

namespace Nabik\Gateland\Plugins\JetEngine;

use Jet_Engine_Booking_Forms_Handler;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

/**
 * It works only with insert/updater post notification
 * The form should have total_price field to process payment
 * payment_status will have : pending, processing, failed, completed statuses
 * gateland_authority meta will create automatically, to show it, set it up for the form
 */
class Load {

	protected static ?Load $_instance = null;

	public function __construct() {

		if ( ! class_exists( Jet_Engine_Booking_Forms_Handler::class ) ) {
			return;
		}

		add_action( 'jet-engine/forms/handler/after-send', [ $this, 'process_form' ], 1, 2 );
		add_filter( 'jet-engine/forms/booking/message-types', [ $this, 'set_custom_messages' ], 10, 1 );
		// Main class is already running in init hook
		$this->verify();
	}

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function process_form( Jet_Engine_Booking_Forms_Handler $handler, bool $status ) {

		if ( $status !== true ) {
			return;
		}

		$form_data = $handler->get_form_data();

		$current_post_id = intval( $form_data['post_id'] );

		$page_link = self::redirect_link( $current_post_id );

		// Assume that price is always IRT
		$price = intval( $form_data['total_price'] ?? $form_data['total_cost'] ?? 0 );

		// In this situation, payment_status is empty
		$payment_status = get_post_meta( $current_post_id, 'payment_status', true );

		$user_id = wp_get_current_user()->ID ?? null;

		$phone = $form_data['phone'] ?? $form_data['mobile'] ?? null;

		$inserted_post_id = intval( $handler->notifcations->data['inserted_post_id'] );

		if ( ! empty( $payment_status ) || empty( $price ) || $price < 1000 || empty( $inserted_post_id ) ) {
			return;
		}

		$callback = add_query_arg( [
			'action'     => Transaction::CLIENT_JET_ENGINE,
			'page'       => $current_post_id,
			'submission' => $inserted_post_id,
			'secret'     => hash( 'crc32', $inserted_post_id . AUTH_KEY ),
		], $page_link );

		$data = [
			'amount'      => $price,
			'client'      => Transaction::CLIENT_JET_ENGINE,
			'user_id'     => $user_id,
			'order_id'    => $inserted_post_id,
			'callback'    => $callback,
			'description' => 'پرداخت مبلغ ' . $price . ' تومان در ' . home_url(),
			'mobile'      => $phone,
			'currency'    => CurrenciesEnum::IRT,
		];

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			$handler->redirect( [ 'status' => 'gateland_failed_connection' ] );
			exit;
		}

		if ( ! $response['success'] ) {
			$handler->redirect( [ 'status' => sprintf( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] ) ] );
			exit;
		}

		update_post_meta( $inserted_post_id, 'gateland_authority', $response['data']['authority'] );
		update_post_meta( $inserted_post_id, 'payment_status', 'pending' );

		if ( $handler->is_ajax() ) {
			add_filter( 'jet-engine/forms/handler/query-args', function ( $query_args, $args, $handler_obj ) use ( $response ) {
				return $query_args['redirect'] = $response['data']['payment_link'];
			} );
		} else {
			wp_redirect( $response['data']['payment_link'] );
			exit();
		}
	}

	public function verify() {

		if ( ! isset( $_GET['action'] ) || $_GET['action'] !== Transaction::CLIENT_JET_ENGINE || ! isset( $_GET['submission'] ) || ! isset( $_GET['secret'] ) || ! isset( $_GET['page'] ) ) {
			return;
		}

		$page_id       = intval( $_GET['page'] ?? 0 );
		$submission_id = intval( $_GET['submission'] ?? 0 );
		$secret        = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret != hash( 'crc32', $submission_id . AUTH_KEY ) ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_secret_error' ) );
		}

		$submission     = get_post( $submission_id );
		$payment_status = get_post_meta( $submission_id, 'payment_status', true );
		$authority      = get_post_meta( $submission_id, 'gateland_authority', true );

		if ( empty( $submission ) || empty( $payment_status ) || empty( $authority ) ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_submission_not_found' ) );
			exit;
		}

		if ( $payment_status !== 'pending' ) {
			wp_redirect( self::redirect_link( $page_id, 'gateland_already_processed' ) );
			exit;
		}

		$response = Pay::verify( $authority, Transaction::CLIENT_JET_ENGINE );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			update_post_meta( $submission_id, 'payment_status', 'processing' );

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

		$messages['gateland_submission_not_found'] = [
			'label'   => 'ورودی فرم، یافت نشد.',
			'default' => 'ورودی فرم، یافت نشد.'
		];

		$messages['gateland_secret_error'] = [
			'label'   => 'کلید امنیتی صحیح نمی‌باشد.',
			'default' => 'کلید امنیتی صحیح نمی‌باشد.'
		];

		return $messages;
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
