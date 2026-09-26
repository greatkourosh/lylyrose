<?php

namespace Nabik\Gateland\Plugins\RCP;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

defined( 'ABSPATH' ) || die();

if ( ! class_exists( '\RCP_Payment_Gateway' ) ) {
	return;
}

class Gateway extends \RCP_Payment_Gateway {

	public function init() {
		$this->supports[] = 'one-time';
		$this->supports[] = 'ajax-payment';
	}

	public function process_signup() {

		$payment_id = $this->payment->id;

		if ( ! $payment_id ) {
			rcp_errors()->add( 'gateway_connection', 'شناسه تراکنش معتبر نمی‌باشد.', 'register' );

			return;
		}

		$amount   = intval( $this->initial_amount );
		$currency = rcp_get_currency();

		if ( $currency == 'IRR' ) {
			$amount /= 10;
		} elseif ( $currency == 'IRHR' ) {
			$amount *= 100;
		} elseif ( $currency == 'IRHT' ) {
			$amount *= 1000;
		}

		$callback = add_query_arg( [
			'listener'   => 'gateland',
			'payment_id' => $payment_id,
		], site_url() );

		$data = [
			'amount'      => $amount,
			'client'      => Transaction::CLIENT_RCP,
			'user_id'     => $this->user_id,
			'order_id'    => $payment_id,
			'callback'    => $callback,
			'description' => $this->payment->subscription,
			'currency'    => CurrenciesEnum::IRT,
		];

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			rcp_errors()->add( 'gateway_connection', 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.', 'register' );

			return;
		}

		if ( ! $response['success'] ) {
			rcp_errors()->add( 'gateway_connection', 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است. ' . $response['message'], 'register' );

			return;
		}

		( new \RCP_Payments() )->update_meta( $payment_id, 'authority', $response['data']['authority'] );

		wp_redirect( $response['data']['payment_link'] );
		exit;
	}

	/**
	 * method for verifying payment
	 */
	public function process_webhooks() {
		global $rcp_options;

		if ( ! isset( $_GET['listener'], $_GET['payment_id'] ) ) {
			return;
		}

		if ( $_GET['listener'] != 'gateland' ) {
			return;
		}

		$payment_id = intval( $_GET['payment_id'] );

		$rcp_payments = new \RCP_Payments();

		$payment = $rcp_payments->get_payment_by( 'id', $payment_id );

		if ( empty( $payment ) ) {
			wp_redirect( get_permalink( $rcp_options['redirect'] ) );
			exit;
		}

		if ( $payment->status != 'pending' ) {
			rcp_errors()->add( 'gateway_connection', 'پرداخت شما قبلا پردازش شده است.', 'register' );
			wp_redirect( get_permalink( $rcp_options['redirect'] ) );
			exit;
		}

		$authority = $rcp_payments->get_meta( $payment_id, 'authority', true );

		$user_id = $payment->user_id;

		$response = Pay::verify( $authority, Transaction::CLIENT_RCP );

		if ( $response['success'] || $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$rcp_payments->update( $payment_id, [
				'transaction_id' => $authority,
				'status'         => 'complete',
			] );

			$member = new \RCP_Member( $user_id );
			$member->set_subscription_id( $payment->object_id );

			delete_user_meta( $member->ID, 'rcp_pending_subscription_amount' );

			if ( $member->get_subscription_key() !== $payment->subscription_key ) {
				$member->set_subscription_key( $payment->subscription_key );
			}

			if ( ! $member->get_expiration_date() ) {
				$member->set_expiration_date( $member->calculate_expiration() );
			}

			$member->set_status( 'active' );

		} else {

			$rcp_payments->update( $payment_id, [
				'transaction_id' => '',
				'status'         => 'cancelled',
			] );

			rcp_errors()->add( 'gateway_connection', 'متاسفانه پرداخت شما ناموفق بود!', 'register' );

		}

		wp_redirect( get_permalink( $rcp_options['redirect'] ) );
		exit;
	}
}

