<?php

namespace Nabik\Gateland\Plugins\Woocommerce;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Gateland;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;
use Nabik\Gateland\Services\GatewayService;
use Nabik\QestiPay\Models\Installment;
use Nabik\QestiPay\Models\Loan;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit;

class Gateway extends WC_Payment_Gateway {

	private int $gateway_id = 0;

	public array $gateway = [];

	public string $success_massage = '';

	public string $failed_massage = '';

	public function __construct( int $gateway_id = null ) {

		$id          = 'gateland';
		$title       = 'گیت‌لند';
		$icon        = GATELAND_URL . '/assets/images/shaparak.png';
		$description = 'گیت‌لند - درگاه پرداخت آنلاین هوشمند برای افزونه فروشگاه ساز ووکامرس';

		if ( ! is_null( $gateway_id ) ) {
			$gateways         = GatewayService::activated();
			$this->gateway_id = $gateway_id;
			$this->gateway    = $gateways[ $gateway_id ] ?? [];

			$id          = "gateland_{$gateway_id}";
			$title       = $this->gateway['name'];
			$icon        = $this->gateway['icon'];
			$description = "گیت‌لند - درگاه پرداخت {$this->gateway['name']} برای افزونه فروشگاه ساز ووکامرس";
		}

		$this->id                 = $id;
		$this->icon               = $icon;
		$this->has_fields         = false;
		$this->method_title       = $title;
		$this->method_description = $description;

		$this->supports = [
			'products',
		];

		$options = get_option( $this->get_option_key(), false );

		if ( $options === false ) {
			update_option( $this->get_option_key(), [], 'yes' );
		}

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->enabled         = $this->get_option( 'enabled' );
		$this->success_massage = $this->get_option( 'success_massage', '' );
		$this->failed_massage  = $this->get_option( 'failed_massage', '' );

		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options',
		] );

		add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'request_payment' ] );
		add_action( 'woocommerce_api_' . $this->id, [ $this, 'webhook' ] );
	}


	/**
	 * Init form fields in admin panel
	 */
	public function init_form_fields() {

		$this->form_fields = [
			'base_config'     => [
				'title'       => 'تنظیمات پایه‌ای',
				'type'        => 'title',
				'description' => '',
			],
			'enabled'         => [
				'title'       => 'فعالسازی/غیرفعالسازی',
				'type'        => 'checkbox',
				'label'       => "فعالسازی درگاه {$this->method_title}",
				'description' => 'برای فعالسازی درگاه پرداخت گیت‌لند باید چک باکس را تیک بزنید',
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'title'           => [
				'title'       => 'عنوان درگاه',
				'type'        => 'text',
				'description' => 'عنوان درگاه که در طی خرید به مشتری نمایش داده می‌شود',
				'default'     => $this->gateway_id ? $this->gateway['name'] : 'پرداخت آنلاین',
				'desc_tip'    => true,
			],
			'description'     => [
				'title'       => 'توضیحات درگاه',
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد',
				'default'     => 'پرداخت امن به وسیله کلیه کارت‌های عضو شتاب',
			],
			'payment_config'  => [
				'title'       => 'تنظیمات عملیات پرداخت',
				'type'        => 'title',
				'description' => '',
			],
			'success_massage' => [
				'title'       => 'پیام پرداخت موفق',
				'type'        => 'textarea',
				'description' => 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد {authority} برای نمایش کد رهگیری (توکن) گیت‌لند استفاده نمایید.',
				'default'     => 'با تشکر از شما. سفارش شما با موفقیت پرداخت شد.',
			],
			'failed_massage'  => [
				'title'       => 'پیام پرداخت ناموفق',
				'type'        => 'textarea',
				'description' => 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد {authority} برای نمایش کد رهگیری (توکن) گیت‌لند استفاده نمایید.',
				'default'     => 'پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.',
			],
		];
	}

	/**
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function request_payment( int $order_id ) {

		$response = $this->process_payment( $order_id );

		if ( $response['result'] == 'success' ) {
			wp_redirect( $response['redirect'] );
		} else {
			echo $response['message'];
		}

	}

	/**
	 * Request method
	 *
	 * @param $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( intval( $order_id ) );

		$callback = add_query_arg( [
			'order_id' => $order_id,
			'secret'   => hash( 'crc32', $order_id . AUTH_KEY ),
		], WC()->api_request_url( $this->id ) );

		$amount = $order->get_total();

		$currency = $order->get_currency();

		if ( $currency == 'IRR' ) {
			$amount /= 10;
		} elseif ( $currency == 'IRHR' ) {
			$amount *= 100;
		} elseif ( $currency == 'IRHT' ) {
			$amount *= 1000;
		}

		if ( $amount > 400_000_000 ) {

			if ( ! class_exists( Loan::class ) ) {

				$message = 'جهت پرداخت مبالغ بالای ۴۰۰ میلیون تومان، افزونه قسطی‌پی را نصب و فعال نمایید.';

				wc_add_notice( $message, 'error' );

				return [
					'result'  => 'failure',
					'message' => $message,
				];
			}

			$loan_data = [
				'number'    => Loan::generate_number(),
				'order_id'  => $order->get_id(),
				'full_name' => $order->get_formatted_billing_full_name(),
				'phone'     => $order->get_billing_phone(),
				'user_id'   => $order->get_user_id(),
				'status'    => Loan::STATUS_PENDING,
				'amount'    => $order->get_total( 'edit' ),
			];

			/** @var Loan $loan */
			$loan = Loan::create( $loan_data );

			$loan->installments()->create( [
				'user_id'  => $loan_data['user_id'],
				'status'   => Installment::STATUS_PENDING,
				'amount'   => 400_000_000,
				'due_date' => verta()->setTimezone( wp_timezone() )->endDay()->toCarbon()->utc(),
			] );

			$next_payment_date = verta()->setTimezone( wp_timezone() );

			$created_amount = $loan->installments()->sum( 'amount' );

			while ( $loan->amount - $created_amount > 0 ) {

				$next_payment_date = $next_payment_date->addDay();

				$loan->installments()->create( [
					'user_id'  => $loan_data['user_id'],
					'status'   => Installment::STATUS_WAITING,
					'amount'   => min( 400_000_000, $loan->amount - $created_amount ),
					'due_date' => $next_payment_date->endDay()->toCarbon()->utc(),
				] );

				$created_amount = $loan->installments()->sum( 'amount' );
			}

			WC()->cart->empty_cart();

			do_action( 'nabik/qesti_pay/loan_created', $loan, $order );

			return [
				'result'   => 'success',
				'redirect' => $loan->get_url(),
			];
		}

		$data = [
			'amount'      => $amount,
			'client'      => Transaction::CLIENT_WOOCOMMERCE,
			'user_id'     => $order->get_customer_id(),
			'order_id'    => $order->get_id(),
			'callback'    => $callback,
			'description' => $order->get_id() . ' - ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'mobile'      => $order->get_billing_phone(),
			'currency'    => CurrenciesEnum::IRT,
		];

		if ( $this->gateway_id ) {
			$data['gateway_id'] = $this->gateway_id;
		}

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			$message = '[W1] خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.';

			wc_add_notice( $message, 'error' );

			Gateland::log( '[W1]', $order->get_id(), $e->getMessage() );

			return [
				'result'  => 'failure',
				'message' => $message,
			];
		}

		if ( ! $response['success'] ) {
			$message = '[W2] خطایی در زمان ارتباط با درگاه پرداخت رخ داده است. ' . $response['message'] ?? '';

			wc_add_notice( $message, 'error' );

			Gateland::log( '[W2]', $order->get_id(), $response['message'] ?? '-' );

			return [
				'result'  => 'failure',
				'message' => $message,
			];
		}

		WC()->cart->empty_cart();

		$order->update_meta_data( 'authority', $response['data']['authority'] );
		$order->save_meta_data();

		do_action( 'gateland_process_payment', $order, $response );

		return [
			'result'   => 'success',
			'redirect' => $response['data']['payment_link'],
		];
	}

	/**
	 * Callback method
	 *
	 * @return void
	 */
	public function webhook() {

		if ( ! isset( $_GET['order_id'], $_GET['secret'] ) ) {
			return;
		}

		$secret = sanitize_text_field( $_GET['secret'] );

		$order_id = intval( $_GET['order_id'] );

		if ( $secret !== hash( 'crc32', $order_id . AUTH_KEY ) ) {
			wp_die( 'کلید امنیتی صحیح نمی‌باشد.' );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_die( 'سفارش معتبر نمی‌باشد.' );
		}

		$authority = $order->get_meta( 'authority' );

		$meta_id = intval( $_GET['meta_id'] ?? 0 );

		if ( $meta_id ) {
			foreach ( $order->get_meta( 'authority', false ) as $meta ) {
				if ( $meta->id == $meta_id ) {
					$authority = $meta->value;
					break;
				}
			}
		}

		if ( ! $authority ) {
			wp_die( 'کلید اتصال به درگاه یافت نشد.' );
		}

		if ( ! $order->needs_payment() ) {

			if ( $order->is_paid() ) {
				Pay::verify( $authority, Transaction::CLIENT_WOOCOMMERCE );
			}

			wc_add_notice( 'سفارش شما قبلا پردازش شده است.', 'error' );
			wp_redirect( $this->get_return_url( $order ) );
			exit;
		}

		$response = Pay::verify( $authority, Transaction::CLIENT_WOOCOMMERCE );

		if ( $response['success'] || $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$order->payment_complete( $authority );

			$lines = [
				'پرداخت موفقیت آمیز بود.',
				'شناسه پرداخت: ' . $authority,
				'درگاه: ' . $response['data']['gateway'],
				'کد رهگیری: ' . $response['data']['trans_id'],
				'شماره کارت: ' . $response['data']['card_number'],
			];

			$order_note = implode( '<br/>', $lines );

			$message     = $this->success_massage;
			$notice_type = 'success';

			$url = $this->get_return_url( $order );

			do_action( 'gateland_webhook_paid', $order, $response );

		} else {

			$order->update_status( 'pending' );

			$order_note = sprintf( 'پرداخت ناموفق بود.<br/>کد رهگیری: %d', $authority );

			$message     = $this->failed_massage;
			$notice_type = 'error';

			$url = $order->get_checkout_payment_url();

			do_action( 'gateland_webhook_failed', $order, $response );
		}

		$order->add_order_note( $order_note, 1 );

		wc_add_notice( str_replace( '{authority}', $authority, $message ), $notice_type );

		wp_redirect( $url );
		exit;
	}
}