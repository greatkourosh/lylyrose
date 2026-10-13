<?php
/**
 * Accounting REST API (حسابداری): sales, summary, invoices, unit costs and expenses.
 *
 * Read views over WooCommerce orders, a per-product unit cost, and a ledger of
 * operating expenses. Each order line snapshots the cost in force at checkout,
 * so a later cost edit never rewrites past profit. Routes live under
 * /wp-json/aroma/v1/ and need manage_woocommerce.
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

	const EXPENSE_POST = 'asc_expense';

	const EXPENSE_AMOUNT = '_asc_expense_amount';

	const EXPENSE_DATE = '_asc_expense_date';

	const EXPENSE_CATEGORY = '_asc_expense_category';

	const PURCHASE_POST = 'asc_purchase';

	const PURCHASE_LINES = '_asc_purchase_lines';

	const PURCHASE_DATE = '_asc_purchase_date';

	const PURCHASE_PAID = '_asc_purchase_paid';

	const PAYMENT_POST = 'asc_payment';

	const PAYMENT_AMOUNT = '_asc_payment_amount';

	const PAYMENT_DATE = '_asc_payment_date';

	const PAYMENT_ACCOUNT = '_asc_payment_account';

	const PAYMENT_ORDER = '_asc_payment_order';

	const ACCOUNTS = array( 'bank', 'cash' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_expense_type' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'snapshot_cost' ), 10, 4 );
	}

	public static function register_expense_type() {
		register_post_type(
			self::EXPENSE_POST,
			array(
				'public'   => false,
				'show_ui'  => false,
				'supports' => array( 'title', 'editor' ),
			)
		);

		register_post_type(
			self::PURCHASE_POST,
			array(
				'public'   => false,
				'show_ui'  => false,
				'supports' => array( 'title' ),
			)
		);

		register_post_type(
			self::PAYMENT_POST,
			array(
				'public'   => false,
				'show_ui'  => false,
				'supports' => array( 'title' ),
			)
		);
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

		register_rest_route(
			self::REST_NAMESPACE,
			'/expenses',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_expenses' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => $range,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_expense' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'amount'   => array(
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'is_cost' ),
						),
						'date'     => array(
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'is_date' ),
						),
						'category' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'note'     => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/balance-sheet',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_balance_sheet' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'as_of' => array( 'validate_callback' => array( __CLASS__, 'is_date' ), 'default' => gmdate( 'Y-m-d' ) ),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/vat',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_vat' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => $range,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/payments',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_payments' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => $range,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_payment' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'amount'  => array(
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'is_cost' ),
						),
						'date'    => array(
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'is_date' ),
						),
						'account' => array(
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'is_account' ),
						),
						'order_id' => array(
							'validate_callback' => array( __CLASS__, 'is_order_id' ),
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/purchases',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_purchases' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => $range,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_purchase' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'supplier' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'date'     => array(
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'is_date' ),
						),
						'paid'     => array(
							'default' => false,
						),
						'lines'    => array(
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'is_lines' ),
						),
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

	public static function is_account( $value ) {
		return in_array( $value, self::ACCOUNTS, true );
	}

	public static function is_order_id( $value ) {
		return is_numeric( $value ) && wc_get_order( (int) $value ) instanceof WC_Order;
	}

	public static function is_lines( $value ) {
		if ( ! is_array( $value ) || ! $value ) {
			return false;
		}
		foreach ( $value as $line ) {
			if ( ! is_array( $line ) || ! isset( $line['product_id'] ) || ! isset( $line['qty'] ) || ! isset( $line['cost'] ) ) {
				return false;
			}
			if ( ! is_numeric( $line['qty'] ) || (int) $line['qty'] < 1 || ! self::is_cost( $line['cost'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Gregorian Y-m-d to Jalali Y-m-d, arithmetic conversion (no plugin).
	 */
	public static function to_jalali( $date ) {
		$parts = array_map( 'intval', explode( '-', (string) $date ) );
		if ( 3 !== count( $parts ) || $parts[1] < 1 || $parts[1] > 12 || $parts[2] < 1 || $parts[2] > 31 ) {
			return '';
		}
		list( $gy, $gm, $gd ) = $parts;
		$days_before = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

		$gy -= 1600;
		$jy  = 979;
		$gy2 = $gm > 2 ? $gy + 1 : $gy;
		$days = 365 * $gy + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) - 80 + $gd + $days_before[ $gm - 1 ];

		$jy   += 33 * intdiv( $days, 12053 );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + $days % 31;
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( $days - 186 ) % 30;
		}

		return sprintf( '%04d-%02d-%02d', $jy, $jm, $jd );
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
			'orders'           => 0,
			'gross'            => 0.0,
			'refunds'          => 0.0,
			'vat'              => 0.0,
			'net_sales'        => 0.0,
			'cogs'             => 0.0,
			'gross_profit'     => 0.0,
			'expenses'         => 0.0,
			'operating_profit' => 0.0,
			'purchases'        => 0.0,
			'payables'         => 0.0,
			'receivables'      => 0.0,
			'cash_in'          => 0.0,
		);

		$paid_on = array();
		foreach ( self::payment_rows( '1970-01-01', '2999-12-31' ) as $payment ) {
			$paid_on[ $payment['order_id'] ] = ( $paid_on[ $payment['order_id'] ] ?? 0.0 ) + $payment['amount'];
		}
		foreach ( self::unpaid_orders_in( $request['from'], $request['to'] ) as $order ) {
			$owed = (float) $order->get_total() - ( $paid_on[ $order->get_id() ] ?? 0.0 );
			$sum['receivables'] += max( 0.0, $owed );
		}

		foreach ( self::payment_rows( $request['from'], $request['to'] ) as $payment ) {
			$sum['cash_in'] += $payment['amount'];
		}

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

		foreach ( self::expense_rows( $request['from'], $request['to'] ) as $expense ) {
			$sum['expenses'] += $expense['amount'];
		}
		$sum['operating_profit'] = $sum['gross_profit'] - $sum['expenses'];

		foreach ( self::purchase_rows( $request['from'], $request['to'] ) as $purchase ) {
			$sum['purchases'] += $purchase['total'];
			$sum['payables']  += $purchase['total'] - $purchase['paid'];
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
				'date_jalali'    => self::to_jalali( $order->get_date_created()->date( 'Y-m-d' ) ),
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

	public static function list_expenses( WP_REST_Request $request ) {
		return rest_ensure_response( self::expense_rows( $request['from'], $request['to'] ) );
	}

	public static function create_expense( WP_REST_Request $request ) {
		$id = wp_insert_post(
			array(
				'post_type'    => self::EXPENSE_POST,
				'post_status'  => 'publish',
				'post_title'   => $request['category'],
				'post_content' => (string) $request['note'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::EXPENSE_AMOUNT, (float) $request['amount'] );
		update_post_meta( $id, self::EXPENSE_DATE, $request['date'] );
		update_post_meta( $id, self::EXPENSE_CATEGORY, $request['category'] );

		return rest_ensure_response( self::expense_row( get_post( $id ) ) );
	}

	public static function get_balance_sheet( WP_REST_Request $request ) {
		$as_of = $request['as_of'];
		$cash  = 0.0;
		foreach ( self::payment_rows( '1970-01-01', $as_of ) as $payment ) {
			$cash += $payment['amount'];
		}
		foreach ( self::purchase_rows( '1970-01-01', $as_of ) as $purchase ) {
			$cash -= $purchase['paid'];
		}

		$receivables = 0.0;
		$paid_on     = array();
		foreach ( self::payment_rows( '1970-01-01', $as_of ) as $payment ) {
			$paid_on[ $payment['order_id'] ] = ( $paid_on[ $payment['order_id'] ] ?? 0.0 ) + $payment['amount'];
		}
		foreach ( self::unpaid_orders_in( '1970-01-01', $as_of ) as $order ) {
			$receivables += max( 0.0, (float) $order->get_total() - ( $paid_on[ $order->get_id() ] ?? 0.0 ) );
		}

		$inventory = 0.0;
		foreach ( wc_get_products( array( 'limit' => -1, 'manage_stock' => true ) ) as $product ) {
			$inventory += max( 0, (int) $product->get_stock_quantity() ) * (float) $product->get_meta( self::COST_META, true );
		}

		$payables = 0.0;
		foreach ( self::purchase_rows( '1970-01-01', $as_of ) as $purchase ) {
			$payables += $purchase['total'] - $purchase['paid'];
		}

		$assets = $cash + $receivables + $inventory;
		return rest_ensure_response(
			array(
				'as_of'       => $as_of,
				'cash'        => round( $cash ),
				'receivables' => round( $receivables ),
				'inventory'   => round( $inventory ),
				'assets'      => round( $assets ),
				'payables'    => round( $payables ),
				'equity'      => round( $assets - $payables ),
			)
		);
	}

	public static function get_vat( WP_REST_Request $request ) {
		$output = 0.0;
		foreach ( self::orders_in( $request['from'], $request['to'] ) as $order ) {
			$row     = self::order_row( $order );
			$output += $row['vat'];
		}

		$input = 0.0;
		foreach ( self::purchase_rows( $request['from'], $request['to'] ) as $purchase ) {
			$input += self::vat_of( $purchase['total'] );
		}

		return rest_ensure_response(
			array(
				'output_vat' => round( $output ),
				'input_vat'  => round( $input ),
				'net_vat'    => round( $output - $input ),
			)
		);
	}

	public static function list_payments( WP_REST_Request $request ) {
		return rest_ensure_response( self::payment_rows( $request['from'], $request['to'] ) );
	}

	public static function create_payment( WP_REST_Request $request ) {
		$id = wp_insert_post(
			array(
				'post_type'   => self::PAYMENT_POST,
				'post_status' => 'publish',
				'post_title'  => $request['account'] . ' ' . $request['date'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::PAYMENT_AMOUNT, (float) $request['amount'] );
		update_post_meta( $id, self::PAYMENT_DATE, $request['date'] );
		update_post_meta( $id, self::PAYMENT_ACCOUNT, $request['account'] );
		update_post_meta( $id, self::PAYMENT_ORDER, (int) $request['order_id'] );

		return rest_ensure_response( self::payment_row( get_post( $id ) ) );
	}

	private static function payment_rows( $from, $to ) {
		$posts = get_posts(
			array(
				'post_type'      => self::PAYMENT_POST,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_key'       => self::PAYMENT_DATE,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => self::PAYMENT_DATE,
						'value'   => array( $from, $to ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);

		return array_map( array( __CLASS__, 'payment_row' ), $posts );
	}

	private static function payment_row( $post ) {
		$date = get_post_meta( $post->ID, self::PAYMENT_DATE, true );
		return array(
			'id'          => $post->ID,
			'date'        => $date,
			'date_jalali' => self::to_jalali( $date ),
			'account'     => get_post_meta( $post->ID, self::PAYMENT_ACCOUNT, true ),
			'order_id'    => (int) get_post_meta( $post->ID, self::PAYMENT_ORDER, true ),
			'amount'      => (float) get_post_meta( $post->ID, self::PAYMENT_AMOUNT, true ),
		);
	}

	public static function list_purchases( WP_REST_Request $request ) {
		return rest_ensure_response( self::purchase_rows( $request['from'], $request['to'] ) );
	}

	public static function create_purchase( WP_REST_Request $request ) {
		$lines = array();
		foreach ( (array) $request['lines'] as $line ) {
			if ( ! wc_get_product( (int) $line['product_id'] ) ) {
				return new WP_Error( 'asc_no_product', __( 'محصول پیدا نشد', 'aroma-store-core' ), array( 'status' => 400 ) );
			}
		}
		foreach ( (array) $request['lines'] as $line ) {
			$product = wc_get_product( (int) $line['product_id'] );
			$qty  = (int) $line['qty'];
			$cost = (float) $line['cost'];
			$lines[] = array(
				'product_id' => $product->get_id(),
				'name'       => $product->get_name(),
				'qty'        => $qty,
				'cost'       => $cost,
			);
			$product->update_meta_data( self::COST_META, $cost );
			$product->set_stock_quantity( (int) $product->get_stock_quantity() + $qty );
			$product->set_stock_status( 'instock' );
			$product->save();
		}

		$id = wp_insert_post(
			array(
				'post_type'   => self::PURCHASE_POST,
				'post_status' => 'publish',
				'post_title'  => $request['supplier'] . ' ' . $request['date'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::PURCHASE_LINES, $lines );
		update_post_meta( $id, self::PURCHASE_DATE, $request['date'] );
		update_post_meta( $id, self::PURCHASE_PAID, $request['paid'] ? (float) self::purchase_total( $lines ) : 0.0 );

		return rest_ensure_response( self::purchase_row( get_post( $id ) ) );
	}

	private static function purchase_rows( $from, $to ) {
		$posts = get_posts(
			array(
				'post_type'      => self::PURCHASE_POST,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_key'       => self::PURCHASE_DATE,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => self::PURCHASE_DATE,
						'value'   => array( $from, $to ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);

		return array_map( array( __CLASS__, 'purchase_row' ), $posts );
	}

	private static function purchase_row( $post ) {
		$lines = (array) get_post_meta( $post->ID, self::PURCHASE_LINES, true );
		$date  = get_post_meta( $post->ID, self::PURCHASE_DATE, true );
		return array(
			'id'          => $post->ID,
			'supplier'    => $post->post_title,
			'date'        => $date,
			'date_jalali' => self::to_jalali( $date ),
			'lines'       => $lines,
			'total'       => self::purchase_total( $lines ),
			'paid'        => (float) get_post_meta( $post->ID, self::PURCHASE_PAID, true ),
		);
	}

	private static function purchase_total( $lines ) {
		$total = 0.0;
		foreach ( $lines as $line ) {
			$total += (float) $line['cost'] * (int) $line['qty'];
		}
		return $total;
	}

	private static function expense_rows( $from, $to ) {
		$posts = get_posts(
			array(
				'post_type'      => self::EXPENSE_POST,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_key'       => self::EXPENSE_DATE,
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'     => self::EXPENSE_DATE,
						'value'   => array( $from, $to ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			)
		);

		return array_map( array( __CLASS__, 'expense_row' ), $posts );
	}

	private static function expense_row( $post ) {
		return array(
			'id'          => $post->ID,
			'date'        => get_post_meta( $post->ID, self::EXPENSE_DATE, true ),
			'date_jalali' => self::to_jalali( get_post_meta( $post->ID, self::EXPENSE_DATE, true ) ),
			'category' => get_post_meta( $post->ID, self::EXPENSE_CATEGORY, true ),
			'note'     => $post->post_content,
			'amount'   => (float) get_post_meta( $post->ID, self::EXPENSE_AMOUNT, true ),
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

	private static function unpaid_orders_in( $from, $to ) {
		return wc_get_orders(
			array(
				'status'       => array( 'wc-on-hold', 'wc-pending' ),
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
			'id'          => $order->get_id(),
			'number'      => $order->get_order_number(),
			'date'        => $order->get_date_created()->date( 'Y-m-d' ),
			'date_jalali' => self::to_jalali( $order->get_date_created()->date( 'Y-m-d' ) ),
			'status'      => $order->get_status(),
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
