<?php

namespace Nabik\Gateland\Plugins\Bookly;

use Bookly\Backend\Modules\Settings\Proxy\Shared;

class SettingsProxy extends Shared {

	public static function preparePaymentGatewaySettings( $payment_data ) {

		$payment_data[ PaymentEntity::TYPE_GATELAND ] = self::renderTemplate(
			'gateland_settings',
			[ 'type' => PaymentEntity::TYPE_GATELAND ],
			false
		);

		return $payment_data;
	}

	public static function saveSettings( array $alert, $tab, array $params ) {

		if ( $tab === 'payments' ) {

			$options = [
				'bookly_gateland_enabled',
				'bookly_gateland_show_price',
				'bookly_gateland_selected_gateway',
			];

			foreach ( $options as $option_name ) {

				if ( array_key_exists( $option_name, $params ) ) {

					update_option( $option_name, trim( $params[ $option_name ] ) );

				}

			}

		}

		return parent::saveSettings( $alert, $tab, $params );
	}

	public static function renderTemplate( $template, $variables = [], $echo = true ) {
		$template_path = __DIR__ . '/templates/' . $template . '.php';

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		extract( $variables );
		ob_start();
		include $template_path;
		$html = ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}
}