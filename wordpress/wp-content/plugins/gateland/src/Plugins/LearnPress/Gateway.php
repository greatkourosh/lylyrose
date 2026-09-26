<?php

namespace Nabik\Gateland\Plugins\LearnPress;

use Exception;
use LP_Gateway_Abstract;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;
use Nabik\Gateland\Services\GatewayService;

class Gateway extends LP_Gateway_Abstract {

	/**
	 * @var array|null
	 */
	protected $settings = null;

	/**
	 * @var null
	 */
	protected $order = null;

	/**
	 * @var null
	 */
	protected $posted = null;

	/**
	 *
	 * @var string
	 */
	protected $authority = null;

	public function __construct() {
		$this->id = 'gateland';

		$this->method_title       = 'گیت‌لند';
		$this->method_description = 'درگاه پرداخت آنلاین هوشمند';
		$this->icon               = GATELAND_URL . 'assets/images/shaparak.png';

		if ( ! empty( LP()->settings->get( 'gateland.gateway' ) ) ) {

			$gateways = GatewayService::activated();
			$gateway  = $gateways[ LP()->settings->get( 'gateland.gateway' ) ] ?? [];

			if ( isset( $gateway['icon'] ) ) {
				$this->icon = $gateway['icon'];
			}
		}

		// Get settings
		$this->title       = LP()->settings->get( "gateland.title", $this->method_title );
		$this->description = LP()->settings->get( "gateland.description", $this->method_description );

		if ( did_action( 'learn_press/gateland-add-on/loaded' ) ) {
			return;
		}

		add_filter( 'learn-press/payment-gateway/gateland/available', [ $this, 'is_available', ], 10, 2 );

		do_action( 'learn_press/gateland-add-on/loaded' );

		parent::__construct();

		add_action( 'learn_press_web_hooks_processed', [ $this, 'webhook' ] );
		add_action( "learn-press/before-checkout-order-review", [ $this, 'error_message' ] );
	}

	public function get_settings() {

		$gateways[] = 'درگاه پرداخت آنلاین هوشمند';

		foreach ( GatewayService::activated() as $gateway_id => $gateway ) {
			$gateways[ $gateway_id ] = $gateway['name'];
		}

		return apply_filters( 'learn-press/gateway-payment/gateland/settings', [
				[
					'type' => 'title',
				],
				[
					'title'   => 'فعالسازی',
					'id'      => '[enable]',
					'default' => 'no',
					'type'    => 'checkbox',
				],
				[
					'title'   => 'درگاه پرداخت',
					'id'      => '[gateway]',
					'default' => '',
					'type'    => 'select',
					'desc'    => 'پرداخت توسط چه درگاهی انجام شود؟ پیشفرض: درگاه پرداخت آنلاین هوشمند',
					'options' => $gateways,
				],
				[
					'title'   => 'دریافت تلفن‌همراه',
					'id'      => '[mobile]',
					'default' => 'yes',
					'type'    => 'checkbox',
					'desc'    => 'در صورت فعال بودن، ورودی تلفن همراه به صورت اجباری به تسویه حساب اضافه می‌شود.',
				],
				[
					'title'   => 'عنوان درگاه',
					'id'      => '[title]',
					'default' => 'پرداخت آنلاین',
					'type'    => 'text',
				],
				[
					'title'   => 'توضیحات درگاه',
					'id'      => '[description]',
					'default' => 'پرداخت امن به وسیله کلیه کارت‌های عضو شتاب',
					'type'    => 'textarea',
				],
				[
					'type' => 'sectionend',
				],
			]
		);
	}

	public function get_payment_form() {
		ob_start();
		$template = learn_press_locate_template( 'form.php', learn_press_template_path() . '/addons/gateland-payment/', GATELAND_DIR . '/templates/learnpress' );
		include $template;

		return ob_get_clean();
	}

	public function error_message() {

		$error = sanitize_text_field( $_GET['gateland_error'] ?? null );

		if ( empty( $error ) ) {
			return;
		}

		$template = learn_press_locate_template( 'payment-error.php', learn_press_template_path() . '/addons/gateland-payment/', GATELAND_DIR . '/templates/learnpress' );
		include $template;
	}

	/**
	 * Check gateway available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return LP()->settings->get( "gateland.enable" ) == 'yes';
	}

	/**
	 * @throws Exception
	 */
	public function validate_fields() {

		if ( LP()->settings->get( 'gateland.mobile', 'yes' ) == 'no' ) {
			return true;
		}

		$posted = learn_press_get_request( 'learn-press-gateland' );
		$mobile = $posted['mobile'] ?? '';

		if ( ! preg_match( "/^(09)(\d{9})$/", $mobile ) ) {
			throw new Exception( sprintf( '<div>%s</div>', esc_html__( 'تلفن همراه وارد شده معتبر نمی‌باشد.', 'gateland' ), 202 ) );
		}

		return true;
	}

	public function process_payment( $order_id ) {
		$order = learn_press_get_order( $order_id );

		if ( is_bool( $order ) ) {
			return false;
		}

		$callback = add_query_arg( [
			'lp_pay_method' => 'gateland',
			'order_id'      => $order_id,
			'secret'        => hash( 'crc32', $order_id . AUTH_KEY ),
		], site_url() );

		$currency = learn_press_get_currency();
		$amount   = $order->get_total();

		if ( $currency == 'IRR' ) {
			$amount /= 10;
		}

		$posted = learn_press_get_request( 'learn-press-gateland' );
		$mobile = $posted['mobile'] ?? null;

		$data = [
			'amount'      => $amount,
			'client'      => Transaction::CLIENT_LP,
			'user_id'     => get_current_user_id(),
			'order_id'    => $order_id,
			'callback'    => $callback,
			'description' => $order->get_order_key(),
			'mobile'      => $mobile,
			'currency'    => CurrenciesEnum::IRT,
		];

		if ( LP()->settings->get( 'gateland.gateway' ) ) {
			$data['gateway_id'] = LP()->settings->get( 'gateland.gateway' );
		}

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			return [
				'result'   => 'fail',
				'messages' => 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.',
			];
		}

		if ( ! $response['success'] ) {
			return [
				'result'   => 'fail',
				'messages' => 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است. ' . $response['message'] ?? '',
			];
		}

		update_post_meta( $order_id, 'authority', $response['data']['authority'] );

		return [
			'result'   => 'success',
			'redirect' => $response['data']['payment_link'],
		];
	}

	public static function webhook() {

		if ( ( $_GET['lp_pay_method'] ?? '' ) != 'gateland' ) {
			return;
		}

		$order_id = intval( $_GET['order_id'] ?? 0 );
		$secret   = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret !== hash( 'crc32', $order_id . AUTH_KEY ) ) {
			wp_die( 'کلید امنیتی صحیح نمی‌باشد.' );
		}

		$order = learn_press_get_order( $order_id );

		if ( is_bool( $order ) ) {
			wp_die( 'سفارش یافت نشد.' );
		}

		if ( $order->has_status( 'completed' ) ) {
			wp_die( 'سفارش قبلا پردازش شده است.' );
		}

		$authority = get_post_meta( $order_id, 'authority', true );

		if ( ! $authority ) {
			wp_die( 'کلید اتصال به درگاه یافت نشد.' );
		}

		$response = Pay::verify( $authority, Transaction::CLIENT_LP );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$order->payment_complete( $authority );

			$order->add_note( 'پرداخت با موفقیت انجام شد.' );

			wp_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$url = add_query_arg( [
			'gateland_error' => 'پرداخت ناموفق بود. لطفا دوباره تلاش کنید.',
		], learn_press_get_page_link( 'checkout' ) );

		wp_redirect( $url );
		exit();
	}
}
