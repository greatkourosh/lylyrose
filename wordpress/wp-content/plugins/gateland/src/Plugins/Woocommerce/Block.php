<?php

namespace Nabik\Gateland\Plugins\Woocommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

class Block extends AbstractPaymentMethodType {

	protected $name;

	protected array $gateway;

	public function __construct( $gateway ) {
		$this->name    = empty( $gateway['id'] ) ? 'gateland' : 'gateland_' . $gateway['id'];
		$this->gateway = $gateway;
	}

	public function initialize() {
		$this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
	}

	public function is_active() {
		return true;
	}

	public function get_payment_method_script_handles() {
		$handle = $this->name . '-wc-payment-block';

		wp_register_script(
			$handle,
			GATELAND_URL . 'assets/js/plugins/woocommerce/wc-payment-block.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			],
			GATELAND_VERSION,
			true
		);

		wp_localize_script( $handle, 'gateland_wc_payment_block', [ 'name' => $this->name ] );

		return [ $handle ];
	}

	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title', $this->gateway['name'] ),
			'icon'        => $this->gateway['icon'],
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
		];
	}

}