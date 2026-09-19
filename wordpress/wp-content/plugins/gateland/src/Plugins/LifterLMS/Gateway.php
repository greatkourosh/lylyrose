<?php

namespace Nabik\Gateland\Plugins\LifterLMS;

use LLMS_Access_Plan;
use LLMS_Order;
use LLMS_Payment_Gateway;
use LLMS_Student;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Pay;

class Gateway extends LLMS_Payment_Gateway {

	public function __construct() {
		$this->id                = 'gateland';
		$this->icon              = sprintf( '<img src="%s/assets/images/shaparak.png' . '" style="width: auto; max-height: 40px;">', GATELAND_URL );
		$this->admin_description = 'پرداخت آنلاین هوشمند';
		$this->admin_title       = 'گیت‌لند';
		$this->title             = 'پرداخت آنلاین';
		$this->description       = 'پرداخت امن به وسیله کلیه کارت‌های عضو شتاب';

		$this->supports = [
			'single_payments' => true,
		];
	}


	/**
	 * @param LLMS_Order       $order
	 * @param LLMS_Access_Plan $plan
	 * @param LLMS_Student     $person
	 * @param bool             $coupon
	 *
	 * @return void|null
	 */
	public function handle_pending_order( $order, $plan, $person, $coupon = false ) {

		$amount = $order->get_price( 'total', [], 'float' );

		if ( $order->currency == 'IRT' ) {
			// :)
		} elseif ( $order->currency == 'IRR' ) {
			$amount /= 10;
		} else {
			llms_add_notice( 'واحد پولی توسط گیت‌لند پشتیبانی نمی‌شود.', 'error' );

			return;
		}

		$data = [
			'amount'      => $amount,
			'client'      => 'lifter_lms',
			'user_id'     => $person->get_id(),
			'order_id'    => $order->id,
			'callback'    => llms_confirm_payment_url( $order->order_key ),
			'description' => $order->get_customer_name() . ' - ' . $order->product_title,
			'mobile'      => $order->billing_phone,
			'currency'    => CurrenciesEnum::IRT,
		];

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {

			llms_add_notice( $e->getMessage(), 'error' );

			return;
		}


		if ( ! $response['success'] ) {
			$message = sprintf( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] );

			llms_add_notice( $message, 'error' );

			return;
		}

		$order->set( 'authority', $response['data']['authority'] );

		wp_redirect( $response['data']['payment_link'] );
		exit;
	}

	/**
	 * @param LLMS_Order $order
	 *
	 * @return void
	 */
	public function confirm_pending_order( $order ) {

		$key = sanitize_key( $_GET['order'] ?? '' );

		/** @var LLMS_Order $order */
		$order = llms_get_order_by_key( $key );

		if ( ! $order || $this->id !== $order->get( 'payment_gateway' ) ) {
			return;
		}

		$authority = intval( $order->get( 'authority' ) );

		if ( empty( $authority ) ) {
			return;
		}

		$response = Pay::verify( $authority, 'lifter_lms' );

		if ( $response['success'] || $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$order->record_transaction( [
				'amount'             => $order->get_price( 'total', [], 'float' ),
				'customer_id'        => $order->user_id,
				'source_description' => $response['data']['card_number'],
				'transaction_id'     => $response['data']['trans_id'],
			] );

			$order->add_note( 'تراکنش موفق: ' . $authority );

			$this->complete_transaction( $order );

		} else {

			llms_add_notice( 'پرداخت ناموفق بود، لطفا دوباره تلاش کنید.', 'error' );

			$order->add_note( 'تراکنش ناموفق: ' . $authority );

			$url = add_query_arg( [
				'plan' => $order->plan_id,
			], get_permalink( llms_get_page_id( 'checkout' ) ) );

			wp_safe_redirect( $url );
			exit();

		}

	}

}