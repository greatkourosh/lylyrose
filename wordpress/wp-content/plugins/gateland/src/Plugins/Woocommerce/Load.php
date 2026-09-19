<?php

namespace Nabik\Gateland\Plugins\Woocommerce;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Nabik\Gateland\Gateland;
use Nabik\Gateland\Plugins\WooCommerce\Gateway;
use Nabik\Gateland\Services\GatewayService;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		include 'Gateway.php';

		new OrderMetabox();

		add_action( 'woocommerce_before_thankyou', 'wc_print_notices' );
		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateways' ] );
		add_action( 'woocommerce_blocks_payment_method_type_registration', [ $this, 'register_payment_block_methods' ] );
	}

	public function register_payment_block_methods( PaymentMethodRegistry $payment_method_registry ) {

		$gateways = array_merge( [
			[
				'id'          => 0,
				'name'        => 'گیت‌لند',
				'icon'        => GATELAND_URL . '/assets/images/shaparak.png',
				'description' => 'گیت‌لند - درگاه پرداخت آنلاین هوشمند'
			]
		], GatewayService::activated() );

		foreach ( $gateways as $gateway ) {
			
			$block = new Block( $gateway );

			if ( $payment_method_registry->is_registered( $block->get_name() ) ) {
				continue;
			}

			$payment_method_registry->register( $block );

		}

	}

	public function add_gateways( array $gateways ): array {

		$gateways[] = Gateway::class;

		foreach ( GatewayService::activated() as $gateway_id => $gateway ) {
			$gateways[] = new Gateway( $gateway_id );
		}

		return $gateways;
	}
}