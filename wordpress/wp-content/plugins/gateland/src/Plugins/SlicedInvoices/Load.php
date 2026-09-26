<?php

namespace Nabik\Gateland\Plugins\SlicedInvoices;

use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;
use Nabik\Gateland\Services\GatewayService;
use Sliced_Shared;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {
		add_action( 'sliced_do_payment', [ $this, 'request' ] );
		add_action( 'sliced_do_payment', [ $this, 'verify' ], 10 );

		add_action( 'admin_head', [ $this, 'admin_inline_css' ] );
		add_filter( 'sliced_register_payment_method', [ $this, 'add_payment_method' ] );
		add_filter( 'sliced_payment_option_fields', [ $this, 'settings_fields' ] );
		remove_action( 'sliced_loaded', 'sliced_call_paypal_class', 1 );
	}

	public function request(): void {

		if ( ! isset( $_POST['start-payment'], $_POST['sliced_gateway'] ) || $_POST['sliced_gateway'] !== 'gateland' ) {
			return;
		}

		$invoice_id = intval( $_POST['sliced_payment_invoice_id'] ?? 0 );

		if ( empty( $invoice_id ) ) {

			sliced_print_message( $invoice_id, 'صورتحساب شما یافت نشد!', 'failed' );

			return;
		}

		if ( ! isset( $_POST['sliced_payment_nonce'] ) || ! wp_verify_nonce( $_POST['sliced_payment_nonce'], 'sliced_invoices_payment' ) ) {

			sliced_print_message( $invoice_id, 'خطایی در ارسال فرم رخ داد، لطفاً دوباره تلاش کنید', 'failed' );

			return;
		}

		$gateway     = self::gateway();
		$description = sliced_get_invoice_label() . ' ' . sliced_get_invoice_prefix( $invoice_id ) . ' ' . sliced_get_invoice_number( $invoice_id ) . ' ' . sliced_get_invoice_suffix( $invoice_id );

		$callback = add_query_arg( [
			'gateway'    => 'gateland',
			'invoice_id' => $invoice_id,
			'hash'       => md5( $invoice_id . NONCE_SALT ),
		], $gateway['payment_page'] );

		$data = [
			'amount'      => sliced_get_invoice_total_due_raw( $invoice_id ) / 10, // always in IRR
			'client'      => Transaction::CLIENT_SI,
			'user_id'     => get_current_user_id(),
			'order_id'    => $invoice_id,
			'callback'    => $callback,
			'description' => $description,
			'currency'    => 'IRT',
			'gateway_id'  => $gateway['id']
		];

		try {

			$response = Pay::request( $data );

		} catch ( \Exception $e ) {

			$message = 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.';
			sliced_print_message( $invoice_id, $message, 'failed' );

			return;
		}

		if ( ! $response['success'] ) {

			$message = sprintf( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] );
			sliced_print_message( $invoice_id, $message, 'failed' );

			return;
		}

		update_post_meta( $invoice_id, '_sliced_gateland_authority', $response['data']['authority'] );

		wp_redirect( $response['data']['payment_link'] );
	}

	public function verify(): void {

		if ( ! isset( $_GET['gateway'] ) || $_GET['gateway'] !== 'gateland' ) {
			return;
		}

		$invoice_id = intval( $_GET['invoice_id'] ?? 0 );

		if ( empty( $invoice_id ) ) {
			return;
		}

		if ( sanitize_text_field( $_GET['hash'] ?? '' ) !== md5( $invoice_id . NONCE_SALT ) ) {
			sliced_print_message( $invoice_id, 'خطا هنگام پردازش پرداخت: مقدار هش نامعتبر است.', 'failed' );

			return;
		}

		if ( has_term( 'paid', 'invoice_status', $invoice_id ) ) {
			sliced_print_message( $invoice_id, 'این صورتحساب قبلاً پرداخت شده است.', 'alert' );

			return;
		}

		$authority = get_post_meta( $invoice_id, '_sliced_gateland_authority', true );

		$response = Pay::verify( $authority, Transaction::CLIENT_SI );

		$status = 'failed';

		if ( $response['success'] || $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$status  = 'success';
			$message = [
				'<h2>موفق</h2>',
				'پرداخت شما ثبت شد!',
				sprintf( 'کد پیگیری پرداخت شما: %s', $authority )
			];

		} else {

			$message = [
				'<h2>ناموفق</h2>',
				$response['message'] ?? 'پرداخت لغو شده، یا با خطا مواجه شده است.'
			];

		}

		$message [] = sprintf(
			'<a href="%s">جهت بازگشت به %s اینجا کلیک کنید</a></p>',
			sliced_get_the_link( $invoice_id ),
			sliced_get_invoice_label()
		);

		$message = wpautop( implode( PHP_EOL, $message ) );

		$payments = get_post_meta( $invoice_id, '_sliced_payment', true );

		if ( ! is_array( $payments ) ) {
			$payments = [];
		}

		$payments[] = [
			'gateway'    => 'gateland',
			'date'       => date( 'Y-m-d H:i:s' ),
			'amount'     => Sliced_Shared::get_formatted_number( sliced_get_invoice_total_due_raw( $invoice_id ) ),
			'currency'   => 'IRR',
			'payment_id' => $authority,
			'status'     => $status,
			'memo'       => sprintf( 'درگاه: %s، شماره پیگیری: %s', $response['data']['gateway'] ?? '', $response['data']['trans_id'] ?? '' ),
			'extra_data' => json_encode( [
				'response' => $response,
				'clientip' => Helper::get_real_ip(),
			] ),
		];

		update_post_meta( $invoice_id, '_sliced_payment', $payments );

		sliced_print_message( $invoice_id, $message, $status );

		do_action( 'sliced_payment_made', $invoice_id, 'گیت‌لند', $status );
	}

	public function admin_inline_css(): void {
		$page = $_GET['page'] ?? null;

		if ( ! is_admin() || $page !== 'sliced_invoices_settings' ) {
			return;
		}

		?>
		<style>
            #sliced-gateland-settings-header {
                background: #f8f8f8;
                border: 1px solid #e5e5e5;
                border-radius: 3px;
                margin: 10px 20px;
                padding: 15px 25px 15px 12px
            }

            #sliced-gateland-settings-header th {
                cursor: pointer
            }

            #sliced-gateland-settings-header .row-toggle {
                text-align: left
            }

            #sliced-gateland-settings-header .row-title {
                padding: 0 20px
            }

            #sliced-gateland-settings > td {
                padding-left: 40px
            }
		</style>
		<?php

	}

	public function add_payment_method( array $gateways ): array {

		$gateway_config = self::gateway();

		if ( $gateway_config['enabled'] ) {
			$gateways['gateland'] = 'گیت‌لند';
		}

		return $gateways;
	}

	public function settings_fields( array $options ): array {

		$gateways = [
			0 => 'درگاه پرداخت هوشمند آنلاین',
		];

		foreach ( GatewayService::activated() as $gateway_id => $gateway ) {
			$gateways[ $gateway_id ] = $gateway['name'];
		}

		$options['fields'] = [
			[
				'name'       => 'فعالسازی',
				'desc'       => '',
				'type'       => 'checkbox',
				'id'         => 'gateland_enabled',
				'before_row' => [ $this, 'settings_group_before' ],
			],
			[
				'name'      => 'درگاه پرداخت',
				'desc'      => 'یکی از درگاه‌های پرداخت فعال را انتخاب کنید.',
				'id'        => 'gateland_gateway',
				'type'      => 'select',
				'default'   => 0,
				'options'   => $gateways,
				'after_row' => [ $this, 'settings_group_after' ],
			]
		];

		return $options;
	}

	/*@formatter:off*/
    public function settings_group_before() {
		#region settings_group_before
		?>
		<table class="widefat" id="sliced-gateland-settings-wrapper">
			<tr id="sliced-gateland-settings-header">
				<th class="row-title"><h4>گیت لند</h4></th>
				<th class="row-toggle"><span class="dashicons dashicons-arrow-down" id="sliced-gateland-settings-toggle"></span></th>
			</tr>
			<tr id="sliced-gateland-settings" style="display:none;">
				<td colspan="2">
		<?php
		#endregion settings_group_before
	}

	public function settings_group_after() {
		#region settings_group_after
		?>
				</td>
			</tr>
		</table>
		<script type="text/javascript">
	        jQuery('#sliced-gateland-settings-header').click(function () {
	            let settings_element = jQuery('#sliced-gateland-settings');
	            let toggle_element = jQuery('#sliced-gateland-settings-toggle');
	            if (jQuery(settings_element).is(':visible')) {
	                jQuery(settings_element).slideUp();
	                jQuery(toggle_element).removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
	            } else {
	                jQuery(settings_element).slideDown();
	                jQuery(toggle_element).removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
	            }
	        });
		</script>
		<?php
	    #endregion settings_group_after
    }
    /*@formatter:on*/

	public static function gateway(): array {
		$payments = get_option( 'sliced_payments' );

		return [
			'id'           => ! empty( $payments['gateland_gateway'] ) ? $payments['gateland_gateway'] : null,
			'enabled'      => $payments['gateland_enabled'] ?? 'off' === 'on',
			'payment_page' => get_permalink( intval( $payments['payment_page'] ) )
		];
	}

}
