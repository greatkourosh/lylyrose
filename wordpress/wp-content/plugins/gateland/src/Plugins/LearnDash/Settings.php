<?php

namespace Nabik\Gateland\Plugins\LearnDash;

use LearnDash_Settings_Section;
use Nabik\Gateland\Services\GatewayService;

class Settings extends LearnDash_Settings_Section {

	protected function __construct() {

		$this->settings_page_id               = 'learndash_lms_payments';
		$this->setting_option_key             = 'learndash_settings_gateland';
		$this->setting_field_prefix           = 'learndash_settings_gateland';
		$this->settings_section_key           = 'settings_gateland';
		$this->settings_section_label         = 'گیت‌لند';
		$this->settings_parent_section_key    = 'settings_payments_list';
		$this->settings_section_listing_label = 'گیت‌لند';

		parent::__construct();
	}

	public function load_settings_values() {
		parent::load_settings_values();

		if ( empty( $this->setting_option_values['enabled'] ) ) {
			$this->setting_option_values['enabled'] = 'no';
		}

		if ( empty( $this->setting_option_values['gateway_id'] ) ) {
			$this->setting_option_values['gateway_id'] = 0;
		}
	}

	public function load_settings_fields() {

		$this->setting_option_fields = [
			'enabled'    => [
				'name'    => 'enabled',
				'type'    => 'checkbox-switch',
				'label'   => 'فعال',
				'value'   => $this->setting_option_values['enabled'] ?? 'no',
				'options' => [
					'yes' => '',
					'no'  => '',
				],
			],
			'gateway_id' => [
				'name'      => 'gateway_id',
				'label'     => 'درگاه',
				'help_text' => 'از بین درگاه های پرداخت فعال، یکی را انتخاب کنید',
				'type'      => 'select',
				'options'   => self::gateways(),
				'default'   => 0,
				'value'     => $this->setting_option_values['gateway_id'] ?? 0,
			],
		];

		parent::load_settings_fields();
	}

	public static function gateways(): array {
		$gateways = [
			0 => 'درگاه پرداخت هوشمند آنلاین',
		];

		foreach ( GatewayService::activated() as $gateway_id => $gateway ) {
			$gateways[ $gateway_id ] = $gateway['name'];
		}

		return $gateways;
	}

	public function filter_section_save_fields( $value, $old_value, $settings_section_key, $settings_screen_id ): array {
		if ( $settings_section_key !== $this->settings_section_key ) {
			return $value;
		}

		if ( ! isset( $value['enabled'] ) ) {
			$value['enabled'] = 'no';
		}

		if ( ! isset( $value['gateway_id'] ) ) {
			$value['gateway_id'] = 0;
		}

		return $value;
	}

}