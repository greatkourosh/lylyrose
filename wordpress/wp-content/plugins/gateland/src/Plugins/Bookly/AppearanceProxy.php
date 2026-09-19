<?php

namespace Nabik\Gateland\Plugins\Bookly;

use Bookly\Backend\Modules\Appearance\Proxy\Shared;

use BooklyPro\Lib\Config;
use Nabik\Gateland\Plugins\Bookly\PaymentEntity;


class AppearanceProxy extends Shared {

	public static function prepareOptions(array $options_to_save, array $options): array
	{

		if (! isset($options['bookly_gateland_option_name']) | empty($options['bookly_gateland_option_name'])) {
			$options['bookly_gateland_option_name'] = 'پرداخت با گیت‌لند';
		}

		return $options;
	}

	public static function paymentGateways( array $gateways ) {

		if ( ! get_option( 'bookly_gateland_enabled' ) ) {
			return $gateways;
		}

		$gateways[ PaymentEntity::TYPE_GATELAND ] = [
			'title'             => 'گیت‌لند',
			'label_option_name' => 'bookly_gateland_option_name',
			'with_card'         => false,
			'logo_url'          => GATELAND_URL . '/assets/images/gateland.png'
		];

		return $gateways;
	}
}