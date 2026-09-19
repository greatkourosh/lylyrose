<?php

namespace Nabik\Gateland\Plugins\Bookly;

use Bookly\Frontend\Components\Booking\InfoText;
use Bookly\Frontend\Modules\Booking\Lib\Errors;
use Bookly\Frontend\Modules\Payment\Ajax;
use Bookly\Frontend\Modules\Payment\Request;
use Bookly\Lib\CartInfo;
use Bookly\Lib\UserBookingData;
use Bookly\Lib\Utils\Collection;
use Bookly\Lib\Utils\Common;


class PaymentAjax {

	public static function init() {
		add_action( 'wp_ajax_bookly_create_payment_intent', [ PaymentAjax::class, 'createPaymentIntent' ], 1 );
		add_action( 'wp_ajax_nopriv_bookly_create_payment_intent', [ PaymentAjax::class, 'createPaymentIntent' ], 1 );

		add_action( 'wp_ajax_bookly_back_from_payment_system', [ PaymentAjax::class, 'backFromPaymentSystem' ] );
		add_action( 'wp_ajax_nopriv_bookly_back_from_payment_system', [ PaymentAjax::class, 'backFromPaymentSystem' ] );
	}

	public static function createPaymentIntent() {
		$gateway = $_POST['gateway'] ?? '';

		if ( $gateway !== PaymentEntity::TYPE_GATELAND ) {
			Ajax::createPaymentIntent();

			return;
		}

		$request = Request::getInstance();

		$userData = $request->getUserData();

		$failed_cart_key = $userData->cart->getFailedKey();

		if ( $failed_cart_key === null ) {

			try {

				wp_send_json_success( $request->getGateway()->isOnSite()
					? $request->getGateway()->createIntent()
					: $request->getGateway()->createCheckout()
				);

			} catch ( \Error $e ) {

				$request->getGateway()->fail();
				wp_send_json( [ 'success' => false, 'error' => Errors::PAYMENT_ERROR, 'error_message' => $e->getMessage() ] );

			} catch ( \Exception $e ) {

				$request->getGateway()->fail();
				wp_send_json( [ 'success' => false, 'error' => Errors::PAYMENT_ERROR, 'error_message' => $e->getMessage() ] );

			}

		}

		wp_send_json( [ 'success' => false, 'error' => Errors::CART_ITEM_NOT_AVAILABLE, 'failed_cart_key' => $failed_cart_key, ] );
	}

	public static function backFromPaymentSystem() {

		$request = Request::getInstance();
		try {
			$gateway = new Gateway( $request );
		} catch ( \Exception $e ) {
			error_log( 'Error: Gateland -> Bookly -> PaymentAjax -> backFromPaymentSystem :: find zero gateways in $request.' );
			$gateway = null;
		}

		$order = new \Bookly\Lib\Entities\Order();

		if ( $order->loadBy( [ 'token' => $request->get( 'bookly_order' ) ] ) && $gateway ) {

			try {

				switch ( $request->get( 'bookly_event' ) ) {

					case \Bookly\Lib\Base\Gateway::EVENT_CANCEL:
						$gateway->fail();
						break;

					case \Bookly\Lib\Base\Gateway::EVENT_RETRIEVE:
						$gateway->retrieve();
						break;

				}

			} catch ( \Exception $e ) {

				$gateway->fail();
			}

		}

		Common::redirect( self::parameter( 'bookly_referer' ) );
	}

	protected static function getRequest() {
		static $parameters;

		if ( $parameters === null ) {

			$parameters = isset( $_REQUEST['json_data'] ) ?
				array_map( function ( $value ) {
					return $value !== '' ? $value : null;
				}, json_decode( stripslashes_deep( $_REQUEST['json_data'] ), true ) ?: [] ) :
				stripslashes_deep( $_REQUEST );

			if ( ! current_user_can( 'unfiltered_html' ) ) {

				$parameters = Common::arrayMapRecursive( function ( $value ) {
					return is_string( $value ) ? wp_kses( stripslashes( $value ), 'post' ) : $value;
				}, $parameters );

			}

			$parameters = new Collection( $parameters );

		}

		return $parameters;
	}

	protected static function parameter( $name, $default = null ) {
		return self::getRequest()->get( $name, $default );
	}
}