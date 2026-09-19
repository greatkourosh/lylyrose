<?php

namespace Nabik\Gateland\Plugins\Give;

use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\PaymentGatewayRegister;
use Give\Log\Log;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_action( 'give_gateland_cc_form', '__return_false' );
		add_action( 'give_gateway_gateland', [ $this, 'redirect' ] );
		add_action( 'init', [ $this, 'webhook' ] );

		add_filter( 'give_register_currency', [ $this, 'add_currency' ] );
		add_action( 'givewp_register_payment_gateway', [ $this, 'add_gateways' ] );
		add_action( 'give_view_donation_details_payment_meta_after', [ $this, 'add_transaction_link' ] );

		foreach ( \Nabik\Gateland\Services\GatewayService::activated() as $gateway_id => $gateway ) {
			add_action( "give_gateland_{$gateway_id}_cc_form", '__return_false' );
			add_action( "give_gateway_gateland_{$gateway_id}", [ $this, 'redirect' ] );
		}
	}

	public function add_currency( array $currencies ): array {

		$currencies['IRR']['symbol'] = ' ریال';
		$currencies['IRT']['symbol'] = ' تومان';

		return $currencies;
	}

	public function add_gateways( PaymentGatewayRegister $registrar ): void {

		$gateway             = new Gateway();
		$gateway->gateway_id = null;

		try {
			$registrar->registerGateway( Gateway::class );
		} catch ( Exception $e ) {
			dd( $e->getMessage() );
		}

		return;
		$gateways['gateland'] = [
			'admin_label'    => 'گیت‌لند - درگاه هوشمند',
			'checkout_label' => 'پرداخت آنلاین',
			'is_visible'     => true,
		];

		foreach ( \Nabik\Gateland\Services\GatewayService::activated() as $gateway_id => $gateway ) {

			$id             = "gateland_{$gateway_id}";
			$admin_label    = "گیت‌لند - {$gateway['name']}";
			$checkout_label = $gateway['name'];

			$gateways[ $id ] = [
				'admin_label'    => $admin_label,
				'checkout_label' => $checkout_label,
				'is_visible'     => true,
			];
		}

		return;
	}

	public function add_transaction_link( int $payment_id ) {

		$authority = give_get_payment_transaction_id( $payment_id );

		$path = "admin.php?page=gateland-transaction&transaction_id=" . $authority;
		$url  = admin_url( $path );

		?>
		<div class="give-admin-box-inside">
			<p><?php ?>
				<a href="<?php echo esc_url( $url ); ?>" target="_blank">مشاهده تراکنش</a>
			</p>
		</div>
		<?php
	}

	public function redirect( array $payment_data ) {

		if ( give_get_errors() ) {
			give_send_back_to_checkout( '?payment-mode=' . $payment_data['gateway'] );
			exit;
		}

		if ( $payment_data['gateway'] == 'gateland' ) {
			$gateway_id = null;
		} else {
			$gateway_id = filter_var( $payment_data['gateway'], FILTER_SANITIZE_NUMBER_INT );
		}

		$form_id = $payment_data['post_data']['give-form-id'];

		$amount = intval( $payment_data['price'] );

		$currency = give_get_currency( $form_id );

		if ( $currency == 'IRR' ) {
			$amount /= 10;
		}

		$donation_data = [
			'price'           => $amount,
			'give_form_title' => $payment_data['post_data']['give-form-title'],
			'give_form_id'    => $form_id,
			'give_price_id'   => $payment_data['post_data']['give-price-id'],
			'date'            => $payment_data['date'],
			'user_email'      => $payment_data['user_email'],
			'purchase_key'    => $payment_data['purchase_key'],
			'currency'        => $currency,
			'user_info'       => $payment_data['user_info'],
			'status'          => 'pending',
			'gateway'         => $payment_data['gateway'],
		];

		$payment_id = give_insert_payment( $donation_data );

		if ( ! $payment_id ) {

			Log::error( 'خطایی در زمان ایجاد تراکنش دونیت رخ داده است.', [
				'gateway' => $payment_data['gateway'],
			] );

			give_send_back_to_checkout( '?payment-mode=' . $payment_data['gateway'] );
			exit;
		}

		$callback = add_query_arg( [
			'action'     => 'gateland_donate_give',
			'payment_id' => $payment_id,
			'secret'     => hash( 'crc32', $payment_id . AUTH_KEY ),
		], site_url() );

		$data = [
			'amount'      => $payment_data['price'],
			'client'      => Transaction::CLIENT_GIVE,
			'user_id'     => get_current_user_id() ? get_current_user_id() : null,
			'order_id'    => $payment_id,
			'callback'    => $callback,
			'description' => $payment_data['post_data']['give-form-title'],
			'currency'    => CurrenciesEnum::IRT,
			'gateway_id'  => $gateway_id,
		];

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {

			$message = 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.';

			Log::error( $message, [
				'gateway' => $payment_data['gateway'],
			] );

			give_send_back_to_checkout( '?payment-mode=' . $payment_data['gateway'] );
			exit;
		}

		if ( ! $response['success'] ) {

			$message = sprintf( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] );

			Log::error( $message, [
				'gateway' => $payment_data['gateway'],
			] );

			give_send_back_to_checkout( '?payment-mode=' . $payment_data['gateway'] );
			exit;
		}

		give_set_payment_transaction_id( $payment_id, $response['data']['authority'] );

		wp_redirect( $response['data']['payment_link'] );
		exit;
	}

	public function webhook() {

		if ( ( $_GET['action'] ?? null ) != 'gateland_donate_give' ) {
			return;
		}

		$payment_id = intval( $_GET['payment_id'] ?? 0 );
		$secret     = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret != hash( 'crc32', $payment_id . AUTH_KEY ) ) {
			wp_die( 'کلید امنیتی صحیح نمی‌باشد.' );
		}

		$authority = give_get_payment_transaction_id( $payment_id );

		if ( empty( $authority ) ) {
			wp_die( 'کلید اتصال به درگاه یافت نشد.' );
		}

		if ( get_post_status( $payment_id ) == 'failed' ) {
			wp_safe_redirect( give_get_failed_transaction_uri() );
			exit;
		}

		if ( get_post_status( $payment_id ) == 'publish' ) {
			wp_safe_redirect( give_get_success_page_uri() );
			exit;
		}

		$response = Pay::verify( $authority, Transaction::CLIENT_GIVE );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			give_insert_payment_note( $payment_id, 'تراکنش با موفقیت پرداخت شد. کد رهگیری: ' . $response['data']['trans_id'] );
			give_update_payment_status( $payment_id );

			wp_safe_redirect( give_get_success_page_uri() );
			exit;
		}

		give_insert_payment_note( $payment_id, 'تراکنش ناموفق بود. کد رهگیری: ' . $authority );
		give_update_payment_status( $payment_id, 'failed' );

		wp_safe_redirect( give_get_failed_transaction_uri() );
		exit;
	}
}