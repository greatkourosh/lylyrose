<?php
/**
 * Back-in-Stock Notifier (موجود شد به من خبر بده).
 *
 * Customers subscribe to out-of-stock products with their phone number; on
 * restock, every pending subscriber gets a Persian SMS (via PWSMS) plus a
 * bell notification if they're a logged-in customer. Subscriptions live in a
 * dedicated DB table, one per phone+product, with a 24h resubscribe cooldown.
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_Stock_Notifier {

	const TABLE_SUFFIX = 'asc_stock_subs';
	const COOLDOWN     = DAY_IN_SECONDS; // min seconds between re-subscribing.

	private static function sms_template() {
		return __( "لیلی رز\nکالای «%s» اکنون موجود است! برای خرید به این نشانی مراجعه کنید:\n%s", 'lylyrose-core' );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'create_table' ), 99 );
		add_action( 'wp_ajax_nopriv_asc_stock_subscribe', array( __CLASS__, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_asc_stock_subscribe', array( __CLASS__, 'ajax_subscribe' ) );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_stock_change' ), 10, 2 );
	}

	/**
	 * Table name with WordPress prefix.
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create the subscription table (idempotent dbDelta).
	 */
	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::table();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			phone VARCHAR(20) NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			sent_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone_product (phone, product_id),
			KEY product_id (product_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Look up a subscription row for a phone+product.
	 *
	 * @param string $phone      Normalized mobile (09xxxxxxxxx).
	 * @param int    $product_id Product ID.
	 * @return object|null       Row, or null when not subscribed.
	 */
	public static function is_subscribed( $phone, $product_id ) {
		global $wpdb;
		if ( ! $phone ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . self::table() . " WHERE phone = %s AND product_id = %d LIMIT 1",
			$phone,
			(int) $product_id
		) );
		return $row ? $row : null;
	}

	/**
	 * AJAX: subscribe a phone to a product.
	 */
	public static function ajax_subscribe() {
		check_ajax_referer( 'asc_stock_subscribe', 'nonce' );

		$product_id = absint( isset( $_POST['product_id'] ) ? $_POST['product_id'] : 0 );
		$product    = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'محصول نامعتبر است.', 'lylyrose-core' ) ) );
		}
		if ( $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'این کالا اکنون موجود است.', 'lylyrose-core' ) ) );
		}

		// Phone: prefer billing_phone (logged-in); fall back to the POSTed number
		// (guests, or logged-in users without a stored phone).
		$user_id = get_current_user_id();
		$phone   = $user_id ? self::normalize_phone( get_user_meta( $user_id, 'billing_phone', true ) ) : '';
		if ( ! $phone ) {
			$phone = self::normalize_phone( isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '' );
		}
		if ( ! $phone ) {
			wp_send_json_error( array( 'message' => __( 'شماره موبایل معتبر نیست.', 'lylyrose-core' ) ) );
		}

		global $wpdb;
		$existing = self::is_subscribed( $phone, $product_id );
		if ( $existing ) {
			if ( 'sent' === $existing->status ) {
				wp_send_json_error( array(
					'message' => __( 'قبلاً موجود شدن این کالا به شما اطلاع‌رسانی شده است.', 'lylyrose-core' ),
					'marked'  => true,
				) );
			}
			$since = time() - (int) strtotime( $existing->created_at );
			$wait  = max( 0, self::COOLDOWN - $since );
			if ( $wait > 0 ) {
				wp_send_json_error( array(
					'message' => sprintf( __( 'شما قبلاً ثبت‌نام کرده‌اید. %d ساعت دیگر می‌توانید دوباره تلاش کنید.', 'lylyrose-core' ), ceil( $wait / HOUR_IN_SECONDS ) ),
				) );
			}
		}

		// Fresh subscription, or re-subscribe after cooldown.
		if ( $existing ) {
			$wpdb->update(
				self::table(),
				array( 'created_at' => current_time( 'mysql' ), 'user_id' => $user_id, 'status' => 'pending', 'sent_at' => null ),
				array( 'id' => (int) $existing->id )
			);
		} else {
			$wpdb->insert(
				self::table(),
				array(
					'product_id' => $product_id,
					'phone'      => $phone,
					'user_id'    => $user_id,
					'status'     => 'pending',
				),
				array( '%d', '%s', '%d', '%s' )
			);
		}

		wp_send_json_success( array( 'message' => __( 'ثبت‌نام شد. به محض موجود شدن کالا، به شما اطلاع می‌دهیم.', 'lylyrose-core' ) ) );
	}

	/**
	 * On restock, notify every pending subscriber for the product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     New stock status (WC product stock status).
	 */
	public static function on_stock_change( $product_id, $status ) {
		if ( 'instock' !== $status ) {
			return;
		}
		self::send_notifications( $product_id );
	}

	/**
	 * Send SMS (+ bell notification for logged-in users) to all pending
	 * subscribers of a freshly restocked product, then mark them sent.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function send_notifications( $product_id ) {
		global $wpdb;
		$product_id = (int) $product_id;

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . self::table() . " WHERE product_id = %d AND status = 'pending'",
			$product_id
		) );
		if ( ! $rows ) {
			return;
		}

		$product_name = get_the_title( $product_id );
		$product_url  = get_permalink( $product_id );
		$message      = sprintf( self::sms_template(), $product_name, $product_url );

		$now = current_time( 'mysql' );

		foreach ( $rows as $row ) {
			if ( function_exists( 'PWSMS' ) ) {
				PWSMS()->send_sms( array(
					'mobile'  => $row->phone,
					'message' => $message,
				) );
			}

			$wpdb->update(
				self::table(),
				array( 'status' => 'sent', 'sent_at' => $now ),
				array( 'id' => (int) $row->id )
			);

			if ( (int) $row->user_id && class_exists( 'ASC_Notifications' ) ) {
				ASC_Notifications::instance()->create(
					(int) $row->user_id,
					sprintf( __( 'کالای «%s» اکنون موجود است!', 'lylyrose-core' ), $product_name ),
					$product_url
				);
			}
		}
	}

	/**
	 * Normalize a raw phone via ASC_OTP (falls back to PWSMS when absent).
	 */
	private static function normalize_phone( $raw ) {
		if ( class_exists( 'ASC_OTP' ) ) {
			return ASC_OTP::normalize_mobile( $raw );
		}
		return '';
	}
}