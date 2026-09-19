<?php

namespace Nabik\Gateland\Plugins\Bookly;

use Bookly\Lib\Entities\Payment;
use \Bookly\Lib\CartInfo;

class PaymentEntity extends Payment {
	public const TYPE_GATELAND = 'gateland';

	public function setCartInfo( CartInfo $cart_info ) {

		$cart_info->setGateway( PaymentEntity::TYPE_GATELAND );

		$pay_now = $cart_info->getPayNow();

		if ( $pay_now > 0 ) {

			$type = $cart_info->getGateway();

		} else {

			$type = $cart_info->getSubtotal() + $cart_info->getDiscount() > 0
				? self::TYPE_LOCAL
				: self::TYPE_FREE;

		}

		$this
			->setType( $type )
			->setStatus( Payment::STATUS_PENDING )
			->setTotal( $cart_info->getTotal() )
			->setPaid( $cart_info->getTotal() )
			->setGatewayPriceCorrection( $cart_info->getPriceCorrection() )
			->setPaidType( ( $cart_info->getPayFull() || $cart_info->getTotal() == $pay_now )
				? self::PAY_IN_FULL
				: self::PAY_DEPOSIT
			)
			->setTax( $cart_info->getTotalTax() );

		return $this;
	}

	public static function typeToString( $type ) {

		switch ( $type ) {

			case self::TYPE_GATELAND:
				return 'گیت‌لند';
			default:
				return parent::typeToString( $type );

		}

	}

	public static function getTypes() {
		return array_merge( [ self::TYPE_GATELAND ], parent::getTypes() );
	}
}