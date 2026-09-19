<?php

namespace Nabik\Gateland\Plugins\WPUF;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;
use WPUF_Subscription;

class Gateway {

	public string $id;

	public string $title;

	public string $description;

	public string $icon;

	public ?int $gateway_id = null;

	public function __construct( int $gateway_id = null ) {

		$this->gateway_id = $gateway_id;

		$this->payment_status  = '';
		$this->payment_message = '';

		$this->id          = 'gateland';
		$this->title       = 'گیت‌لند';
		$this->icon        = GATELAND_URL . '/assets/images/shaparak.png';
		$this->description = 'گیت‌لند - درگاه پرداخت آنلاین هوشمند';

		if ( ! is_null( $gateway_id ) ) {

			$gateways = \Nabik\Gateland\Services\GatewayService::activated();

			$gateway = $gateways[ $gateway_id ] ?? [];

			$this->id          = "gateland_{$gateway_id}";
			$this->title       = $gateway['name'];
			$this->icon        = $gateway['icon'];
			$this->description = "گیت‌لند - درگاه پرداخت {$gateway['name']}";
		}

		add_filter( 'wpuf_payment_gateways', [ $this, 'add_gateway' ] );
		add_action( 'wpuf_options_payment', [ $this, 'gateway_options' ] );

		add_action( 'wpuf_gateway_' . $this->id, [ $this, 'request' ] );
		add_action( 'init', [ $this, 'callback' ] );

	}

	public function add_gateway( $gateways ) {

		$gateways[ $this->id ] = [
			'admin_label'    => $this->description,
			'checkout_label' => wpuf_get_option( $this->id . '_name', 'wpuf_payment', $this->description ),
			'icon'           => $this->icon,
		];

		return $gateways;
	}


	public function gateway_options( $options ) {

		$options[] = [
			'name'  => $this->id . '_header',
			'label' => sprintf( 'پیکربندی درگاه %s', $this->title ),
			'type'  => 'html',
			'desc'  => '<hr/>',
		];

		$options[] = [
			'name'    => 'name_' . $this->id,
			'label'   => 'عنوان درگاه',
			'default' => $this->gateway_id ? $this->title : 'پرداخت آنلاین',
		];

		$options[] = [
			'name'    => 'gate_instruct_' . $this->id,
			'label'   => 'توضیحات درگاه',
			'type'    => 'textarea',
			'default' => 'پرداخت امن به وسیله کلیه کارت‌های عضو شتاب',
			'desc'    => 'توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد',
		];

		$options[] = [
			'name'    => 'success_massage_' . $this->id,
			'label'   => 'پیام پرداخت موفق',
			'type'    => 'textarea',
			'desc'    => 'متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد {authority} برای نمایش کد رهگیری (توکن) گیت‌لند استفاده نمایید.',
			'default' => 'با تشکر از شما. سفارش شما با موفقیت پرداخت شد.',
		];

		$options[] = [
			'name'    => 'failed_massage_' . $this->id,
			'label'   => 'پیام پرداخت ناموفق',
			'type'    => 'textarea',
			'desc'    => 'متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید. همچنین می توانید از شورت کد {authority} برای نمایش کد رهگیری (توکن) گیت‌لند استفاده نمایید.',
			'default' => 'پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.',
		];

		$options[] = [
			'name'  => $this->id . '_footer',
			'label' => '<hr/>',
			'type'  => 'html',
			'desc'  => '<hr/>',
		];

		return $options;
	}


	public function request( $data ) {

		//item detail
		$item_number = intval( $data['item_number'] );
		$item_name   = sanitize_text_field( $data['item_name'] );

		$post_id = $data['type'] == 'post' ? $item_number : 0;
		$pack_id = $data['type'] == 'pack' ? $item_number : 0;

		$user_id = $data['user_info']['id'] ?? get_current_user_id();

		$gateway_id = null;

		if ( isset( $data['post_data']['post_id'] ) ) {
			$_post_id    = $data['post_data']['post_id'];
			$post_object = get_post( $_post_id );
			$user_id     = $post_object->post_author;
			$gateway_id  = filter_var( $data['post_data']['wpuf_payment_method'], FILTER_SANITIZE_NUMBER_INT );
		}

		$user_name  = __( 'مهمان', 'gateland' );
		$user_email = $data['user_info']['email'] ?? '';
		$first_name = $data['user_info']['first_name'] ?? '';
		$last_name  = $data['user_info']['last_name'] ?? '';

		$user_data = get_userdata( $user_id );

		if ( $user_data instanceof \WP_User ) {
			$user_name  = $user_data->user_login;
			$user_email = $user_data->user_email;
			$first_name = $user_data->first_name;
			$last_name  = $user_data->last_name;
		}

		$full_user = $first_name . ' ' . $last_name;
		$full_user = strlen( $full_user ) > 5 ? $full_user : $user_name;

		$redirect_page_id     = wpuf_get_option( 'payment_success', 'wpuf_payment' );
		$subscription_page_id = wpuf_get_option( 'subscription_page', 'wpuf_payment' );

		$amount   = intval( $data['price'] );
		$currency = $data['currency'];

		if ( $currency == 'IRR' ) {
			$amount /= 10;
		}

		if ( isset( $data['coupon_id'] ) && class_exists( '\WPUF_Coupons' ) ) {

			$coupon_id = intval( $data['coupon_id'] );

			if ( get_post_meta( $coupon_id, '_coupon_used', true ) >= get_post_meta( $coupon_id, '_usage_limit', true ) ) {
				wp_die( esc_html__( 'تعداد دفعات استفاده از کد تخفیف به اتمام رسیده است .', 'gateland' ) );
			}

			$amount = \WPUF_Coupons::init()->discount( $amount, $coupon_id, $data['item_number'] );

			update_post_meta( $post_id, '_used_coupon_id', true );
		}

		$data = [
			'user_id'          => $user_id,
			'status'           => 'pending',
			'subtotal'         => $amount,
			'tax'              => 0,
			'cost'             => $amount,
			'post_id'          => $post_id,
			'pack_id'          => $pack_id,
			'payer_first_name' => ! empty( $first_name ) ? $first_name : $full_user,
			'payer_last_name'  => ! empty( $last_name ) ? $last_name : null,
			'payer_email'      => $user_email,
			'payment_type'     => $this->title,
			'created'          => current_time( 'mysql' ),
		];

		$transaction_id = $this->create_transaction( $data );

		if ( $redirect_page_id ) {
			$base_callback_url = get_permalink( $redirect_page_id );
		} else {
			$base_callback_url = get_permalink( $subscription_page_id );
		}

		$callback = add_query_arg( [
			'wpuf_pay_method' => 'gateland',
			'transaction_id'  => $transaction_id,
			'secret'          => hash( 'crc32', $transaction_id . AUTH_KEY ),
		], $base_callback_url );

		if ( $amount == 0 ) {

			$this->update_transaction( [
				'status' => 'completed',
			], $transaction_id );

			WPUF_Subscription::init()->new_subscription( $user_id, $item_number, null, false, 'free' );

			wp_redirect( $base_callback_url );
			exit();
		}

		$data = [
			'amount'      => $amount,
			'client'      => Transaction::CLIENT_WPUF,
			'user_id'     => $user_id,
			'order_id'    => $transaction_id,
			'callback'    => $callback,
			'description' => sprintf( __( 'پرداخت برای %1$s با شناسه %2$s برای کاربر %3$s', 'gateland' ), $item_name, $item_number, $full_user ),
			'mobile'      => null,
			'currency'    => CurrenciesEnum::IRT,
		];

		if ( $gateway_id ) {
			$data['gateway_id'] = $this->gateway_id;
		}

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			wp_die( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.', 'error' );
		}

		if ( ! $response['success'] ) {
			wp_die( esc_html( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است. ' . $response['message'] ?? '' ), 'error' );
		}

		$this->update_transaction( [
			'transaction_id' => $response['data']['authority'],
		], $transaction_id );

		wp_redirect( $response['data']['payment_link'] );
		exit;
	}

	public function callback() {

		if ( ( $_GET['wpuf_pay_method'] ?? '' ) != 'gateland' ) {
			return;
		}

		$transaction_id = intval( $_GET['transaction_id'] ?? 0 );
		$secret         = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret !== hash( 'crc32', $transaction_id . AUTH_KEY ) ) {
			wp_die( 'کلید امنیتی صحیح نمی‌باشد.' );
		}

		$transaction = $this->get_transaction( $transaction_id );

		if ( is_null( $transaction ) ) {
			wp_die( 'تراکنش یافت نشد.' );
		}

		if ( $transaction['status'] != 'pending' ) {

			$this->payment_status  = 'failed';
			$this->payment_message = 'تراکنش قبلا پردازش شده است.';

			add_filter( 'the_content', [ $this, 'add_notice' ] );

			return;
		}

		$authority = $transaction['transaction_id'];

		$response = Pay::verify( $authority, Transaction::CLIENT_WPUF );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$this->update_transaction( [
				'status' => 'completed',
			], $transaction_id );

			$coupon_id = get_post_meta( $transaction['post_id'], '_used_coupon_id', true );

			if ( $coupon_id ) {
				$coupon_used = get_post_meta( $coupon_id, '_coupon_used', true );
				update_post_meta( $coupon_id, '_coupon_used', $coupon_used + 1 );
			}

			if ( $transaction['post_id'] ) {

				$form_id = get_post_meta( $transaction['post_id'], '_wpuf_form_id', true );

				$form_settings = wpuf_get_form_settings( $form_id );

				if ( $form_settings['redirect_to'] == 'page' ) {
					$redirect_to = isset( $form_settings['page_id'] ) ? get_permalink( $form_settings['page_id'] ) : false;
				} elseif ( $form_settings['redirect_to'] == 'url' ) {
					$redirect_to = $form_settings['url'] ?? false;
				} else {
					$redirect_to = get_permalink( $transaction['post_id'] );
				}

				if ( ! empty( $redirect_to ) ) {
					wp_redirect( $redirect_to );
					exit();
				}
			}

			$this->payment_status  = 'completed';
			$this->payment_message = wpuf_get_option( 'success_massage_' . $this->id, 'wpuf_payment' );

		} else {

			$this->update_transaction( [
				'status' => 'failed',
			], $transaction_id );

			$this->payment_status  = 'failed';
			$this->payment_message = wpuf_get_option( 'failed_massage_' . $this->id, 'wpuf_payment' );
		}

		$this->payment_message = str_replace( '{authority}', $authority, $this->payment_message );
		$this->payment_message = wpautop( $this->payment_message );

		add_filter( 'the_content', [ $this, 'add_notice' ] );
	}

	public function create_transaction( array $data ): int {
		return \Nabik_Net_Database::DB()
		                          ->table( 'wpuf_transaction' )
		                          ->insertGetId( $data );
	}

	public function get_transaction( int $transaction_id ): ?array {
		return \Nabik_Net_Database::DB()
		                          ->table( 'wpuf_transaction' )
		                          ->where( 'id', $transaction_id )
		                          ->first()
		                          ->toArray();
	}

	public function update_transaction( array $data, int $transaction_id ): int {
		return \Nabik_Net_Database::DB()
		                          ->table( 'wpuf_transaction' )
		                          ->where( 'id', $transaction_id )
		                          ->update( $data );
	}

	public function add_notice( $content ): string {
		return $this->payment_status == 'completed' ? ( $content . $this->payment_message ) : $this->payment_message;
	}
}
