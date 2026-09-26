<?php

namespace Nabik\Gateland\Plugins\MyCred;

use myCRED_Payment_Gateway;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

class Gateway extends myCRED_Payment_Gateway {

	public function __construct( $gateway_prefs ) {

		$types = mycred_get_types();

		$default_exchange = [];

		foreach ( $types as $type => $label ) {
			$default_exchange[ $type ] = 1000;
		}

		parent::__construct( [
			'id'               => 'gateland',
			'label'            => 'پرداخت آنلاین',
			'documentation'    => 'https://l.nabik.net/gateland-pro/',
			'gateway_logo_url' => '',
			'defaults'         => [
				'title'    => 'پرداخت آنلاین',
				'logo_url' => GATELAND_URL . 'assets/images/shaparak.png',
				'currency' => 'IRT',
				'exchange' => $default_exchange,
			],
		], $gateway_prefs );

		$this->redirect();
	}

	public function ajax_buy() {

		// Construct the checkout box content
		$content = $this->checkout_header();
		$content .= $this->checkout_logo();
		$content .= $this->checkout_order();
		$content .= $this->checkout_cancel();
		$content .= $this->checkout_footer();

		// Return a JSON response
		$this->send_json( $content );

	}

	public function checkout_page_body() {

		echo wp_kses_post( $this->checkout_header() );
		echo wp_kses_post( $this->checkout_logo( false ) );

		echo wp_kses_post( $this->checkout_order() );
		echo wp_kses_post( $this->checkout_cancel() );

		echo wp_kses_post( $this->checkout_footer() );
	}

	function preferences() {

		$prefs = $this->prefs;

		?>
		<div class="row">
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<h3><?php esc_html_e( 'Details', 'gateland' ); ?></h3>
				<div class="form-group">
					<label for="<?php echo esc_attr( $this->field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'gateland' ); ?></label>
					<input type="text" name="<?php echo esc_attr( $this->field_name( 'title' ) ); ?>"
						   id="<?php echo esc_attr( $this->field_id( 'title' ) ); ?>"
						   value="<?php echo esc_attr( $prefs['title'] ); ?>" class="form-control"/>
				</div>
				<div class="form-group">
					<label for="<?php echo esc_attr( $this->field_id( 'logo_url' ) ); ?>"><?php esc_html_e( 'Logo URL', 'gateland' ); ?></label>
					<input type="text" name="<?php echo esc_attr( $this->field_name( 'logo_url' ) ); ?>"
						   id="<?php echo esc_attr( $this->field_id( 'logo_url' ) ); ?>" style="direction: ltr;"
						   value="<?php echo esc_attr( $prefs['logo_url'] ); ?>" class="form-control"/>
				</div>
			</div>
			<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
				<h3><?php esc_html_e( 'Setup', 'gateland' ); ?></h3>
				<div class="form-group">
					<label for="<?php echo esc_attr( $this->field_id( 'currency' ) ); ?>"><?php esc_html_e( 'Currency', 'gateland' ); ?></label>
					<select name="<?php echo esc_attr( $this->field_name( 'currency' ) ); ?>"
							id="<?php echo esc_attr( $this->field_id( 'currency' ) ); ?>"
							class="currency form-control">
						<option value="IRT" <?php selected( 'IRT', esc_attr( $prefs['currency'] ) ); ?>>تومان</option>
						<option value="IRR" <?php selected( 'IRR', esc_attr( $prefs['currency'] ) ); ?>>ریال</option>
					</select>
				</div>
				<div class="form-group">
					<label><?php esc_html_e( 'Exchange Rates', 'gateland' ); ?></label>

					<?php $this->exchange_rate_setup(); ?>

				</div>
			</div>
		</div>

		<script type="text/javascript">
            jQuery(function ($) {

                $('#mycred-gateway-prefs-gateland-currency').change(function () {
                    $('span.mycred-gateway-gateland-currency').text($(this).val());
                });

            });
		</script>
		<?php
	}

	public function sanitise_preferences( $data ) {

		$new_data = [];

		$new_data['title']    = sanitize_text_field( $data['title'] );
		$new_data['logo_url'] = sanitize_text_field( $data['logo_url'] );
		$new_data['currency'] = sanitize_text_field( $data['currency'] );

		// If exchange is less then 1 we must start with a zero
		if ( isset( $data['exchange'] ) ) {
			foreach ( (array) $data['exchange'] as $type => $rate ) {
				if ( $rate != 1 && in_array( substr( $rate, 0, 1 ), [ '.', ',' ] ) ) {
					$data['exchange'][ $type ] = (float) '0' . $rate;
				}
			}
		}
		$new_data['exchange'] = $data['exchange'];

		update_option( 'gateland_mycred_title', $new_data['title'] );

		return $new_data;
	}

	public function prep_sale( $new_transaction = false ) {

		$this->currency = $this->prefs['currency'];
		$this->cost     = (int) $this->get_cost( $this->amount, $this->point_type, true );

		$this->redirect_to = add_query_arg( 'gateland_action', 'pay' );
	}

	public function redirect() {

		if ( ( $_GET['gateland_action'] ?? '' ) != 'pay' ) {
			return;
		}

		// Type
		$type   = $this->get_point_type();
		$mycred = mycred( $type );


		// Amount
		$amount = $mycred->number( $_REQUEST['amount'] );
		$amount = abs( $amount );

		// Get Cost
		$cost = $this->get_cost( $amount, $type );

		if ( $this->prefs['currency'] == 'IRR' ) {
			$cost /= 10;
		}

		$to   = $this->get_to();
		$from = $this->current_user_id;

		$payment_id = $this->add_pending_payment( [
			$to,
			$from,
			$amount,
			$cost,
			'IRT',
			$type,
		] );

		$item_name = str_replace( '%number%', $amount, __( 'خرید %number% امتیاز', 'gateland' ) );
		$item_name = $mycred->template_tags_general( $item_name );

		$from_user = get_userdata( $from );

		$return_url = add_query_arg( [
			'payment_id' => $payment_id,
			'secret'     => hash( 'crc32', $payment_id . AUTH_KEY ),
		], $this->callback_url() );

		$data = [
			'amount'      => $cost,
			'client'      => Transaction::CLIENT_MYCRED,
			'user_id'     => $from,
			'order_id'    => $payment_id,
			'callback'    => $return_url,
			'description' => $item_name . ' - ' . $from_user->first_name . ' ' . $from_user->last_name,
			'currency'    => CurrenciesEnum::IRT,
		];

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			wp_die( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.' );
		}

		if ( ! $response['success'] ) {
			wp_die( esc_html( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است. ' . $response['message'] ?? '' ) );
		}

		update_post_meta( $payment_id, 'authority', $response['data']['authority'] );

		wp_redirect( $response['data']['payment_link'] );
		exit;
	}

	public function process() {

		$payment_id = intval( $_GET['payment_id'] ?? 0 );
		$secret     = sanitize_text_field( $_GET['secret'] ?? null );

		if ( $secret !== hash( 'crc32', $payment_id . AUTH_KEY ) ) {
			wp_die( 'کلید امنیتی صحیح نمی‌باشد.' );
		}

		$payment = $this->get_pending_payment( $payment_id );

		if ( ! is_object( $payment ) ) {
			wp_die( 'تراکنش پرداخت یافت نشد.' );
		}

		if ( get_post_status( $payment_id ) != 'publish' ) {
			wp_die( 'تراکنش قبلا پردازش شده است.' );
		}

		$authority = get_post_meta( $payment_id, 'authority', true );

		if ( ! $authority ) {
			wp_die( 'کلید اتصال به درگاه یافت نشد.' );
		}

		$response = Pay::verify( $authority, Transaction::CLIENT_MYCRED );

		if ( $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$this->complete_payment( $payment, $authority );
			$this->trash_pending_payment( $payment_id );
			$redirect = $this->get_thankyou();

		} else {
			$redirect = $this->get_cancelled( "" );
		}

		wp_redirect( $redirect );
		exit;
	}

}
