<?php


namespace Nabik\Gateland\Plugins\Bookly;

use Bookly\Lib\CartInfo;
use \Bookly\Lib\CartItem;
use \Bookly\Lib\UserBookingData;
use Bookly\Lib\Payment\Proxy\Shared;
use \Bookly\Lib\DataHolders\Booking\Order;


class PaymentProxy extends Shared {

	public static function getGatewayByName( $gateway, $request ) {

		if ( $gateway === PaymentEntity::TYPE_GATELAND ) {
			return new \Nabik\Gateland\Plugins\Bookly\Gateway( $request );
		}

		return parent::getGatewayByName( $gateway, $request );
	}

	public static function showPaymentSpecificPrices( bool $show ): bool {
		return boolval( get_option( 'bookly_gateland_show_price' ) );
	}

	public static function applyGateway( CartInfo $cart_info, string $gateway ): CartInfo {
		$cart_info->setGateway( PaymentEntity::TYPE_GATELAND );

		return $cart_info;
	}

	public static function create( int $item_key, Order $order, CartItem $cart_item, UserBookingData $userData ) {
		$userData->setPaymentType( PaymentEntity::TYPE_GATELAND );
	}
}