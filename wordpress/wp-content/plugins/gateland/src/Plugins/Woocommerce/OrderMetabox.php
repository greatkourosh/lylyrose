<?php

namespace Nabik\Gateland\Plugins\Woocommerce;

use Nabik\Gateland\Models\Gateway;
use Nabik\Gateland\Models\Transaction;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

class OrderMetabox {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'addOrderMetaBox' ] );
	}

	public function addOrderMetaBox() {
		add_meta_box( 'gateland_transactions', 'تراکنش‌های گیت‌لند', [
			$this,
			'metaBoxCallback',
		], [
			'shop_order',
			wc_get_page_screen_id( 'shop-order' ),
		], 'advanced',
			'high' );
	}

	// Get Gateways

	/**
	 * @param WC_Order|WP_Post $post_or_order_object
	 *
	 * @return void
	 */
	public function metaBoxCallback( $post_or_order_object ) {

		$order_id = $post_or_order_object instanceof WC_Order ? $post_or_order_object->get_id() : $post_or_order_object->ID;

		$transactions = Transaction::query()
		                           ->where( 'order_id', $order_id )
		                           ->whereIn( 'client', [ 'woocommerce', 'naghdineh' ] )
		                           ->orderByDesc( 'created_at' )
		                           ->with( 'gateway' )
		                           ->get();

		include GATELAND_DIR . '/templates/woocommerce/order-metabox.php';
	}

}

