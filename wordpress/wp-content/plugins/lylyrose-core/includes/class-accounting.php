<?php
/**
 * Accounting REST API (حسابداری): sales, summary, invoices and unit costs.
 *
 * Read views over WooCommerce orders plus a per-product unit cost. Each order
 * line snapshots the cost in force at checkout, so a later cost edit never
 * rewrites past profit. Routes live under /wp-json/aroma/v1/ and need
 * manage_woocommerce.
 *
 * Assumption: order totals include VAT (ارزش افزوده), so VAT is backed out of
 * the total at VAT_RATE. Confirm before relying on the VAT figures.
 *
 * @package aroma-store-core
 */

defined( 'ABSPATH' ) || exit;

class ASC_Accounting {

	const REST_NAMESPACE = 'aroma/v1';

	const VAT_RATE = 0.09;

	const COST_META = '_asc_unit_cost';

	const LINE_COST_META = '_asc_line_unit_cost';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'snapshot_cost' ), 10, 4 );
	}

	public static function routes() {
		$range = array(
			'from' => array( 'validate_callback' => array( __CLASS__, 'is_date' ), 'default' => gmdate( 'Y-m-01' ) ),
			'to'   => array( 'validate_callback' => array( __CLASS__, 'is_date' ), 'default' => gmdate( 'Y-m-d' ) ),
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/sales',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_sales' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => $range,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_summary' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => $range,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/invoices/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_invoice' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/products/(?P<id>\d+)/cost',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'set_cost' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'cost' => array(
						'required'          => true,
						'validate_callback' => array( __CLASS__, 'is_cost' ),
					),
				),
			)
		);
	}

	public static function can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function is_date( $value ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value );
	}

	public static function is_cost( $value ) {
		return is_numeric( $value ) && (float) $value >= 0;
	}

	public static function snapshot_cost( $item, $cart_item_key, $values, $order ) {
		$product = $values['data'] ?? null;
		if ( $product ) {
			$item->add_meta_data( self::LINE_COST_META, (float) $product->get_meta( self::COST_META, true ), true );
		}
	}

	public static function get_sales( WP_REST_Request $request ) {
		$rows = array();
		foreach ( self::orders_in( $request['from'], $request['to'] ) as $order ) {
			$rows[] = self::order_row( $order );
		}
		return rest_ensure_response( $rows );
	}

	public static function get_summary( WP_REST_Request $request ) {
		$sum = array(
			'orders'       => 0,
			'gross'        => 0.0,
			'refunds'      => 0.0,
			'vat'          => 0.0,
			'net_sales'    => 0.0,
			'cogs'         => 0.0,
			'gross_profit' => 0.0,
		);

		foreach ( self::orders_in( $request['from'], $request['to'] ) as $order ) {
			$row = self::order_row( $order );
			$sum['orders']++;
			$sum['gross']        += $row['total'];
			$sum['refunds']      += $row['refunded'];
			$sum['vat']          += $row['vat'];
			$sum['net_sales']    += $row['net'];
			$sum['cogs']         += $row['cogs'];
			$sum['gross_profit'] += $row['net'] - $row['cogs'];
		}

		return rest_ensure_response( array_map( 'round', $sum ) );
	}

	public static function get_invoice( WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order ) {
			return new WP_Error( 'asc_not_found', __( 'سفارش پیدا نشد', 'aroma-store-core' ), array( 'status' => 404 ) );
		}

		$lines = array();
		foreach ( $order->get_items() as $item ) {
			$lines[] = array(
				'name'       => $item->get_name(),
				'qty'        => (int) $item->get_quantity(),
				'line_total' => (float) $item->get_total(),
			);
		}

		$total = (float) $order->get_total();
		$vat   = self::vat_of( $total );

		return rest_ensure_response(
			array(
				'invoice_number' => 'INV-' . $order->get_id(),
				'order_number'   => $order->get_order_number(),
				'date'           => $order->get_date_created()->date( 'Y-m-d' ),
				'status'         => $order->get_status(),
				'customer'       => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'lines'          => $lines,
				'net'            => round( $total - $vat ),
				'vat'            => $vat,
				'total'          => round( $total ),
			)
		);
	}

	public static function set_cost( WP_REST_Request $request ) {
		$product = wc_get_product( (int) $request['id'] );
		if ( ! $product ) {
			return new WP_Error( 'asc_not_found', __( 'محصول پیدا نشد', 'aroma-store-core' ), array( 'status' => 404 ) );
		}

		$product->update_meta_data( self::COST_META, (float) $request['cost'] );
		$product->save_meta_data();

		return rest_ensure_response(
			array(
				'product_id' => $product->get_id(),
				'unit_cost'  => (float) $product->get_meta( self::COST_META, true ),
			)
		);
	}

	private static function orders_in( $from, $to ) {
		return wc_get_orders(
			array(
				'status'       => array( 'wc-processing', 'wc-completed', 'wc-refunded' ),
				'limit'        => -1,
				'date_created' => $from . '...' . $to . ' 23:59:59',
				'return'       => 'objects',
			)
		);
	}

	private static function order_row( $order ) {
		$total    = (float) $order->get_total();
		$refunded = (float) $order->get_total_refunded();
		$kept     = $total - $refunded;
		$full     = 'refunded' === $order->get_status();

		$cogs = 0.0;
		if ( ! $full ) {
			foreach ( $order->get_items() as $item ) {
				$cogs += self::line_cost( $item ) * (int) $item->get_quantity();
			}
		}

		return array(
			'id'       => $order->get_id(),
			'number'   => $order->get_order_number(),
			'date'     => $order->get_date_created()->date( 'Y-m-d' ),
			'status'   => $order->get_status(),
			'total'    => $total,
			'refunded' => $refunded,
			'vat'      => self::vat_of( $kept ),
			'net'      => $kept - self::vat_of( $kept ),
			'cogs'     => $cogs,
		);
	}

	private static function line_cost( $item ) {
		$snapshot = $item->get_meta( self::LINE_COST_META, true );
		if ( '' !== $snapshot ) {
			return (float) $snapshot;
		}
		$product = $item->get_product();
		return $product ? (float) $product->get_meta( self::COST_META, true ) : 0.0;
	}

	private static function vat_of( $gross ) {
		return round( $gross * self::VAT_RATE / ( 1 + self::VAT_RATE ) );
	}
}
