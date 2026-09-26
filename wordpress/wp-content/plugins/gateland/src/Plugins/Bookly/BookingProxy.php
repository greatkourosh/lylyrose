<?php

namespace Nabik\Gateland\Plugins\Bookly;

use  \Bookly\Frontend\Modules\Booking\Proxy\Shared;
use Bookly\Frontend\Modules\Booking\Proxy\CustomerGroups;
use Bookly\Lib\CartInfo;

class BookingProxy extends Shared {

	public static function preparePaymentOptions( $options, $form_id, $show_price, CartInfo $cart_info, $userData ) {
		$options = parent::preparePaymentOptions( $options, $form_id, $show_price, $cart_info, $userData );

		$gateway = PaymentEntity::TYPE_GATELAND;

		if ( CustomerGroups::allowedGateway( $gateway, $userData ) !== false ) {

			$cart_info->setGateway( $gateway );

			$options[ $gateway ] = [
				'html' => self::render_template( $form_id, $show_price, $cart_info ),
				'pay'  => $cart_info->getPayNow(),
			];

		}

		return $options;
	}

	public static function render_template( $form_id, $show_price, $cart_info ) {
		return self::renderTemplate( 'gateland_option', compact( 'form_id', 'show_price', 'cart_info' ), false );
	}

	protected static function directory() {
		return __DIR__;
	}
}