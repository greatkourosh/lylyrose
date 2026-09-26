<?php


use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

class PMProGateway_gateland extends PMProGateway {

	public function __construct() {

		add_filter( 'pmpro_payment_option_fields', [ $this, 'payment_option_fields', ], 10, 2 );

		$gateway = pmpro_getGateway();

		if ( $gateway == 'gateland' ) {
			add_action( 'pmpro_checkout_before_change_membership_level', [ $this, 'request', ], 10, 2 );
			add_action( 'init', [ $this, 'callback' ] );
			add_filter( 'pmpro_include_billing_address_fields', '__return_false' );
			add_filter( 'pmpro_include_payment_information_fields', '__return_false' );
			add_filter( 'pmpro_required_billing_fields', [ $this, 'required_billing_fields', ] );
		}
	}

	public static function get_description_for_gateway_settings() {
		return 'گیت‌لند - پشتیبانی از واحد‌های پولی تومان، ریال، هزار تومان و هزار ریال';
	}

	public static function required_billing_fields( $fields ) {
		unset( $fields['bfirstname'] );
		unset( $fields['blastname'] );
		unset( $fields['baddress1'] );
		unset( $fields['bcity'] );
		unset( $fields['bstate'] );
		unset( $fields['bzipcode'] );
		unset( $fields['bphone'] );
		unset( $fields['bemail'] );
		unset( $fields['bcountry'] );
		unset( $fields['CardType'] );
		unset( $fields['AccountNumber'] );
		unset( $fields['ExpirationMonth'] );
		unset( $fields['ExpirationYear'] );
		unset( $fields['CVV'] );

		return $fields;
	}

	public static function show_settings_fields() {

		$gateway_id = get_option( 'pmpro_gateland_gateway' );

		?>
		<div id="pmpro_gateland" class="pmpro_section" data-visibility="shown" data-activated="true">
			<div class="pmpro_section_toggle">
				<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
					تنظیمات
				</button>
			</div>
			<div class="pmpro_section_inside">
				<table class="form-table">
					<tbody>
					<tr class="gateway gateway_gateland gateway_gateland gateway_gateland">
						<th scope="row" valign="top">
							<label for="gateland_gateway">انتخاب درگاه</label>
						</th>
						<td>

							<select name="gateland_gateway" id="gateland_gateway">
								<option value="">درگاه پرداخت آنلاین هوشمند</option>
								<?php
								foreach ( \Nabik\Gateland\Services\GatewayService::activated() as $_gateway_id => $_gateway ) {
									printf(
										'<option value="%s" %s>%s</option>>',
										esc_attr( $_gateway_id ),
										selected( $gateway_id, $_gateway_id, false ),
										esc_html( $_gateway['name'] )
									);
								}
								?>
							</select>
							<p class="description">انتخاب کنید که پرداخت از چه درگاهی انجام شود. پیشفرض: درگاه پرداخت
								آنلاین هوشمند</p>
						</td>
					</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	public static function save_settings_fields() {
		$settings_to_save = [
			'gateland_gateway',
		];

		foreach ( $settings_to_save as $setting ) {
			if ( isset( $_REQUEST[ $setting ] ) ) {
				update_option( 'pmpro_' . $setting, intval( $_REQUEST[ $setting ] ) );
			}
		}
	}

	public static function request( int $user_id, MemberOrder $order ) {
		global $discount_code, $pmpro_currency;

		if ( $order->payment_transaction_id ) {
			return;
		}

		if ( 'gateland' !== $order->gateway ) {
			return;
		}

		$user = get_userdata( $user_id );

		$order->user_id                         = $user_id;
		$order->status                          = 'pending';
		$order->membership_level->discount_code = $discount_code;
		$order->billing->name                   = $user->display_name;
		$order->billing->phone                  = $user->user_login;
		$order->saveOrder();

		$amount = intval( $order->total );

		if ( $pmpro_currency == 'IRR' ) {
			$amount /= 10;
		} elseif ( $pmpro_currency == 'IRHR' ) {
			$amount *= 100;
		} elseif ( $pmpro_currency == 'IRHT' ) {
			$amount *= 1000;
		}

		$callback = add_query_arg( [
			'pmp_pay_method' => 'gateland',
			'order_id'       => $order->id,
			'secret'         => hash( 'crc32', $order->id . AUTH_KEY ),
		], site_url() );

		$data = [
			'amount'      => $amount,
			'client'      => Transaction::CLIENT_PMP,
			'user_id'     => $order->user_id,
			'order_id'    => $order->id,
			'callback'    => $callback,
			'description' => $order->membership_level->name,
			'currency'    => CurrenciesEnum::IRT,
		];

		$gateway_id = pmpro_getOption( 'gateland_gateway' );

		if ( $gateway_id ) {
			$data['gateway_id'] = $gateway_id;
		}

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			wp_die( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.', 'error' );
		}

		if ( ! $response['success'] ) {
			wp_die( esc_html( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است. ' . $response['message'] ?? '' ), 'error' );
		}

		$order->payment_transaction_id = $response['data']['authority'];
		$order->saveOrder();

		wp_redirect( $response['data']['payment_link'] );
		exit;
	}

	public static function callback() {
		global $pmpro_level;

		if ( ( $_GET['pmp_pay_method'] ?? '' ) != 'gateland' ) {
			return;
		}

		$order_id = intval( $_GET['order_id'] ?? 0 );
		$secret   = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret !== hash( 'crc32', $order_id . AUTH_KEY ) ) {
			wp_die( 'کلید امنیتی صحیح نمی‌باشد.' );
		}

		try {
			$order       = new MemberOrder( $order_id );
			$pmpro_level = $order->getMembershipLevel();
			$order->getUser();
		} catch ( Exception $exception ) {
			wp_die( 'سفارش یافت نشد.' );
		}

		if ( $order->status != 'pending' ) {
			wp_die( 'سفارش قبلا پردازش شده است.' );
		}

		$response = Pay::verify( $order->payment_transaction_id, Transaction::CLIENT_PMP );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			pmpro_complete_checkout( $order );

		} else {
			$order->cancel();
			$order->notes = sprintf( 'تراکنش %s ناموفق بود.', $order->payment_transaction_id );
			$order->saveOrder();
		}

		wp_redirect( pmpro_url( 'confirmation', '?level=' . $order->membership_level->id ) );
		exit;
	}

}