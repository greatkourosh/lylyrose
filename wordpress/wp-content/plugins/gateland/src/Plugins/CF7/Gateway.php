<?php

namespace Nabik\Gateland\Plugins\CF7;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;
use WPCF7_ContactForm;
use WPCF7_Submission;

defined( 'ABSPATH' ) || exit;

class Gateway {

	public function __construct() {
		add_action( 'wpcf7_before_send_mail', [ $this, 'request' ], 10, 3 );
		add_action( 'wpcf7_shortcode_callback', [ $this, 'redirect' ], 10, 2 );
		add_action( 'wpcf7_shortcode_callback', [ $this, 'verify' ], 10, 2 );
	}

	public function request( WPCF7_ContactForm $form, &$abort, WPCF7_Submission $submission ) {

		$options = self::get_options( $form );

		if ( ! $options['enable'] ) {
			return;
		}

		$callback = add_query_arg( [
			'gateway'    => 'gateland',
			'submission' => $submission->get_posted_data_hash(),
		], $submission->get_meta( 'url' ) );

		$amount = $options['price'];

		if ( $options['price_tag'] != '___' ) {
			$amount = $submission->get_posted_data( $options['price_tag'] );

			if ( is_array( $amount ) ) {
				$amount = current( $amount );
			}
		}

		$data = [
			'amount'      => intval( $amount ),
			'client'      => Transaction::CLIENT_CF7,
			'user_id'     => $submission->get_meta( 'current_user_id' ),
			'order_id'    => $form->id(),
			'callback'    => $callback,
			'description' => $form->title(),
			'mobile'      => $submission->get_posted_data( $options['phone_tag'] ),
			'currency'    => CurrenciesEnum::IRT,
		];

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			$abort = true;
			$submission->set_response( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است.' );

			return;
		}

		if ( ! $response['success'] ) {
			$abort = true;
			$submission->set_response( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است. ' . $response['message'] );

			return;
		}

		set_transient(
			self::transient_key( $submission->get_posted_data_hash() ),
			$response['data']['authority'],
			DAY_IN_SECONDS
		);

		wp_send_json( [
			'redirect' => $response['data']['payment_link'],
		] );
		exit;
	}

	public function redirect() {

		if ( ! self::is_frontend() ) {
			return;
		}

		?>
		<script>
            const {fetch: originalFetch} = window;

            window.fetch = async (...args) => {

                let [resource, config] = args;

                const response = await originalFetch(resource, config);

                if (!resource.includes('contact-form-7')) {
                    return response;
                }

                const json = await response.json();

                if ('redirect' in json) {
                    window.location = json.redirect;
                }

                return new Response(JSON.stringify(json));
            };
		</script>
		<?php
	}

	public function verify( WPCF7_ContactForm $form ) {

		if ( ! self::is_frontend() ) {
			return;
		}

		if ( ! isset( $_GET['gateway'], $_GET['submission'] ) ) {
			return;
		}

		$options = self::get_options( $form );

		if ( ! $options['enable'] ) {
			return;
		}

		$authority = (int) get_transient( self::transient_key( $_GET['submission'] ) );

		if ( ! $authority ) {
			return;
		}


		$response = Pay::verify( $authority, Transaction::CLIENT_CF7 );

		if ( $response['success'] || $response['data']['status'] == StatusesEnum::STATUS_PAID ) {

			$tags = [
				'{authority}' => $authority,
				'{amount}'    => intval( $response['data']['amount'] ),
			];

			printf(
				'<div style="padding: 5px; margin: 10px 0; border: 2px green solid; border-radius: 2px;">%s</div>',
				esc_html( self::tags( __( 'پرداخت شما با شماره پیگیری {authority} با موفقیت ثبت شد.', 'gateland' ), $tags ) )
			);

		} else {

			$tags = [
				'{authority}' => $authority,
				'{amount}'    => intval( $response['data']['amount'] ?? '0' ),
			];

			printf(
				'<div style="padding: 5px; margin: 10px 0; border: 2px red solid; border-radius: 2px;">%s</div>',
				esc_html( self::tags( __( 'پرداخت شما با شماره پیگیری {authority} ناموفق شد. لطفا مجددا تلاش کنید.', 'gateland' ), $tags ) )
			);

		}

		delete_transient( self::transient_key( $_GET['submission'] ) );
	}

	public static function is_frontend(): bool {
		return ! ( is_admin() || defined( 'REST_REQUEST' ) || defined( 'DOING_AJAX' ) );
	}

	public static function tags( string $message, array $tags ) {
		return str_replace(
			array_keys( $tags ),
			array_values( $tags ),
			$message
		);
	}

	public static function transient_key( string $submission_hash ): string {
		return 'gateland_cf7_authority_' . sanitize_text_field( $submission_hash );
	}

	public static function set_options( WPCF7_ContactForm $form, array $options ) {
		update_option( 'gateland_cf7_' . $form->id(), $options );
	}

	public static function get_options( WPCF7_ContactForm $form ): array {
		$options = (array) get_option( 'gateland_cf7_' . $form->id(), [] );

		return wp_parse_args( $options, [
			'enable'    => 0,
			'price_tag' => '___',
			'price'     => null,
			'email_tag' => '___',
			'phone_tag' => '___',
		] );
	}
}