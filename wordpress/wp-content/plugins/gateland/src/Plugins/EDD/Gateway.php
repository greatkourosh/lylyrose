<?php

namespace Nabik\Gateland\Plugins\EDD;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Gateland;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

class Gateway {

	public int $gateway_id = 0;

	public array $gateway = [];

	/**
	 * @var string $id
	 */
	public $id;

	/**
	 * Initialize gateway and hook
	 *
	 * @return                void
	 */
	public function __construct( int $gateway_id = null ) {

		if ( is_null( $gateway_id ) ) {
			$this->id = 'gateland';
		} else {

			$gateways         = \Nabik\Gateland\Services\GatewayService::activated();
			$this->gateway_id = $gateway_id;
			$this->gateway    = $gateways[ $gateway_id ] ?? [];

			$this->id = 'gateland_' . $gateway_id;
		}

		add_filter( 'edd_payment_gateways', [ $this, 'register' ] );
		add_filter( 'edd_settings_gateways', [ $this, 'settings' ] );

		add_action( 'edd_' . $this->id . '_cc_form', '__return_false' );
		add_action( 'edd_gateway_' . $this->id, [ $this, 'process' ] );
		add_action( 'edd_verify_' . $this->id, [ $this, 'verify' ] );

		add_action( 'edd_payment_receipt_after', [ $this, 'receipt' ] );

		add_action( 'init', [ $this, 'webhook' ] );
	}

	public function register( array $gateways ): array {
		global $edd_options;

		$gateways[ $this->id ] = [
			'checkout_label' => $edd_options[ $this->id . '_label' ] ?? ( $this->gateway['name'] ?? 'پرداخت آنلاین' ),
			'admin_label'    => $this->gateway['name'] ?? 'گیت‌لند',
		];

		return $gateways;
	}

	public function settings( array $settings ): array {
		return array_merge( $settings, [
			$this->id . '_header' => [
				'id'   => $this->id . '_header',
				'type' => 'header',
				'name' => sprintf( '<strong>درگاه %s</strong>', $this->gateway['name'] ?? 'گیت‌لند' ),
			],
			$this->id . '_label'  => [
				'id'          => $this->id . '_label',
				'name'        => 'عنوان درگاه',
				'type'        => 'text',
				'size'        => 'regular',
				'placeholder' => $this->gateway['name'] ?? 'پرداخت آنلاین',
			],
		] );
	}

	public function process( $purchase_data ) {
		global $edd_options;

		// Collect payment data
		$payment_data = [
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $purchase_data['downloads'],
			'user_info'    => $purchase_data['user_info'],
			'cart_details' => $purchase_data['cart_details'],
			'gateway'      => $this->id,
			'status'       => 'pending',
		];

		// Record the pending payment
		$payment = edd_insert_payment( $payment_data );

		// Were there any errors?
		if ( ! $payment ) {

			edd_record_gateway_error( 'خطا پرداخت', 'تراکنش ایجاد شده با خطا مواجه شد.', $payment );
			edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );

			return;
		}

		if ( edd_get_currency() == 'IRR' ) {
			$amount = $purchase_data['price'] / 10;
		} else {
			$amount = $purchase_data['price'];
		}

		$callback = add_query_arg( [
			'payment'    => $payment,
			'secret'     => hash( 'crc32', $payment . AUTH_KEY ),
			'edd_verify' => $this->id,
		], get_permalink( $edd_options['success_page'] ) );

		$data = [
			'amount'      => $amount,
			'client'      => Transaction::CLIENT_EDD,
			'order_id'    => $payment,
			'callback'    => $callback,
			'description' => $purchase_data['purchase_key'] . ' - ' . $purchase_data['user_email'],
			'currency'    => CurrenciesEnum::IRT,
		];

		$user_id = intval( $purchase_data['user_info']['id'] ?? 0 );

		if ( $user_id ) {
			$data['user_id'] = $user_id;
		}

		if ( $this->gateway_id ) {
			$data['gateway_id'] = $this->gateway_id;
		}

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			edd_set_error( 'gateland_exception', '[E1] خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.' );

			Gateland::log( '[E1]', $payment, $e->getMessage() );

			edd_send_back_to_checkout();

			return;
		}

		if ( ! $response['success'] ) {
			edd_set_error( 'gateland_not_success', '[E2] خطایی در زمان ارتباط با درگاه پرداخت رخ داده است. ' . $response['message'] ?? '' );

			Gateland::log( '[E2]', $payment, $response['message'] ?? '-' );

			edd_insert_payment_note( $payment, $response['message'] );
			edd_send_back_to_checkout();

			return;
		}

		edd_update_payment_meta( $payment, 'authority', $response['data']['authority'] );

		// Get rid of cart contents
		edd_empty_cart();

		// Redirect to payment link
		wp_redirect( $response['data']['payment_link'] );
		exit;
	}

	public function verify() {
		global $edd_options;

		if ( ! isset( $_GET['payment'], $_GET['secret'] ) ) {
			return false;
		}

		$secret = sanitize_text_field( $_GET['secret'] );

		$payment = intval( $_GET['payment'] );

		if ( $secret !== hash( 'crc32', $payment . AUTH_KEY ) ) {
			wp_die( 'Security key is invalid!' );
		}

		$authority = edd_get_payment_meta( $payment, 'authority' );

		if ( ! $authority ) {
			wp_die( 'Authority not found!' );
		}

		$payment_status = edd_get_payment_status( $payment );

		if ( $payment_status != 'pending' ) {
			edd_send_back_to_checkout();

			return false;
		}

		$response = Pay::verify( $authority, Transaction::CLIENT_EDD );

		if ( $response['success'] || $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			edd_set_payment_transaction_id( $payment, $authority );
			edd_update_payment_status( $payment, 'publish' );
			edd_send_to_success_page();

		}

		edd_update_payment_status( $payment, 'failed' );
		wp_redirect( get_permalink( $edd_options['failure_page'] ) );
	}

	public function receipt( $payment ) {
		$authority = edd_get_payment_meta( $payment->ID, 'authority' );

		if ( $authority ) {
			?>
			<div class="edd-blocks__row edd-blocks-receipt__row-item">
				<div class="edd-blocks__row-label">کد پیگیری پرداخت:</div>
				<div class="edd-blocks__row-value">
					<?php echo esc_html( $authority ); ?>
				</div>
			</div>
			<?php
		}
	}

	public function webhook() {
		if ( isset( $_GET['edd_verify'] ) && $_GET['edd_verify'] == $this->id ) {
			do_action( 'edd_verify_' . $this->id );
		}
	}

}