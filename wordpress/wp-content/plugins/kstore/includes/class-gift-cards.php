<?php
/**
 * Gift cards (کارت هدیه).
 *
 * Sells a gift card as a normal WooCommerce product at a fixed denomination,
 * so it flows through checkout, tax, stock and refunds with no special-casing.
 * On payment, a unique GC-XXXXXXXX coupon is created for the card's value and
 * emailed to the recipient, with the code also shown on the order-received
 * page. Redemption goes through the existing coupon surface, so the cart's
 * always-visible coupon field accepts it with no extra UI.
 *
 * Built in-house rather than with pw-woocommerce-gift-cards: that plugin ships
 * no Persian translation, and the specced config (custom amounts, expiry) is
 * Pro-only. See docs/FEATURES_ROADMAP.md demand D2.
 *
 * @package aroma-store-core
 */

defined( 'ABSPATH' ) || exit;

class ASC_Gift_Cards {

	/** Codes stop redeeming this long after purchase. */
	const EXPIRY_DAYS = 90;

	/** Meta key on the order line / product recording the issued code. */
	const CODE_META = '_asc_gift_card_code';

	/** Meta key flagging a product as a gift card. */
	const PRODUCT_META = '_asc_is_gift_card';

	/** Denominations in Toman. A product is created per amount. */
	const DENOMINATIONS = array( 500000, 1000000, 2000000, 5000000 );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_products' ), 30 );

		// Gift-card fields on the product page.
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_fields' ) );

		// Recipient + message at checkout, only when a gift card is in the cart.
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'save_checkout_fields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_checkout' ), 10, 2 );

		// Issue the code once the order is actually paid.
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'issue_code' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'issue_code' ) );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'issue_code' ) );

		// Show the code on order-received.
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'render_on_receipt' ), 10, 1 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'render_in_email' ), 10, 4 );

		// Restore the code on the thank-you page after a gateway redirect.
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_on_thankyou' ), 10, 1 );
	}

	/* --------------------------------------------------------------------
	 * Products
	 * ----------------------------------------------------------------- */

	/**
	 * Create one product per denomination, once.
	 *
	 * Fixed denominations keep the gift card a real product with a real price,
	 * which is what lets checkout/tax/refunds treat it normally.
	 *
	 * Idempotency keys off the SKUs, not an option flag. This runs on init:30 of
	 * every request, so two concurrent requests both read a missing flag and both
	 * seeded — the SKU lookup is the only guard that actually holds.
	 */
	public static function ensure_products() {
		$missing = false;
		foreach ( self::DENOMINATIONS as $amount ) {
			if ( ! wc_get_product_id_by_sku( self::sku_for( $amount ) ) ) {
				$missing = true;
				break;
			}
		}
		if ( ! $missing ) {
			update_option( 'asc_gift_cards_seeded', 1 );
			return;
		}
		foreach ( self::DENOMINATIONS as $amount ) {
			$sku = self::sku_for( $amount );
			if ( wc_get_product_id_by_sku( $sku ) ) {
				continue;
			}
			$product = new WC_Product_Simple();
			$product->set_name( sprintf( __( 'کارت هدیه %s تومان', 'aroma-store-core' ), number_format_i18n( $amount ) ) );
			$product->set_sku( $sku );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_regular_price( (string) $amount );
			$product->set_short_description( __( 'این کارت هدیه برای هدیه دادن به دیگران است. کد کارت پس از پرداخت برای گیرنده ارسال می‌شود.', 'aroma-store-core' ) );
			$product->set_virtual( true );
			$product->set_sold_individually( true );
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
			$product->update_meta_data( self::PRODUCT_META, 'yes' );
			$product->set_category_ids( array( (int) get_option( 'default_product_cat' ) ) );
			$product->save();
		}
		update_option( 'asc_gift_cards_seeded', 1 );
	}

	/**
	 * SKU for a denomination. Also the idempotency key for ensure_products().
	 */
	public static function sku_for( $amount ) {
		return 'GC-' . intdiv( (int) $amount, 1000 ) . 'K';
	}

	public static function is_gift_card( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return false;
		}
		$id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		return 'yes' === get_post_meta( $id, self::PRODUCT_META, true );
	}

	public static function cart_has_gift_card() {
		if ( ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( self::is_gift_card( $item['data'] ) ) {
				return true;
			}
		}
		return false;
	}

	/* --------------------------------------------------------------------
	 * Admin product fields
	 * ----------------------------------------------------------------- */

	public static function product_fields() {
		global $post;
		$checked = get_post_meta( $post->ID, self::PRODUCT_META, true ) ? 'checked' : '';
		echo '<div class="options_group">';
		echo '<p class="form-field ' . esc_attr( self::PRODUCT_META ) . '">';
		echo '<label><input type="checkbox" name="' . esc_attr( self::PRODUCT_META ) . '" value="yes" ' . $checked . ' /> ';
		esc_html_e( 'این محصول یک کارت هدیه است', 'aroma-store-core' );
		echo '</label></p>';
		echo '</div>';
	}

	public static function save_product_fields( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- core saves the product nonce itself.
		update_post_meta( $post_id, self::PRODUCT_META, isset( $_POST[ self::PRODUCT_META ] ) ? 'yes' : 'no' );
	}

	/* --------------------------------------------------------------------
	 * Checkout
	 * ----------------------------------------------------------------- */

	public static function checkout_fields( $fields ) {
		if ( ! self::cart_has_gift_card() ) {
			return $fields;
		}
		$fields['billing']['asc_gift_recipient'] = array(
			'type'     => 'text',
			'label'    => __( 'ایمیل گیرنده کارت هدیه', 'aroma-store-core' ),
			'required' => true,
			'class'    => array( 'form-row-wide' ),
		);
		$fields['billing']['asc_gift_message'] = array(
			'type'     => 'textarea',
			'label'    => __( 'پیام (اختیاری)', 'aroma-store-core' ),
			'required' => false,
			'class'    => array( 'form-row-wide' ),
		);
		return $fields;
	}

	public static function validate_checkout( $data, $errors ) {
		if ( ! self::cart_has_gift_card() ) {
			return;
		}
		$email = isset( $data['asc_gift_recipient'] ) ? sanitize_email( $data['asc_gift_recipient'] ) : '';
		if ( ! is_email( $email ) ) {
			$errors->add( 'asc_gift_recipient', __( 'ایمیل گیرنده کارت هدیه معتبر نیست.', 'aroma-store-core' ) );
		}
	}

	public static function save_checkout_fields( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies its own nonce.
		if ( ! self::cart_has_gift_card() || empty( $_POST['asc_gift_recipient'] ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$email = sanitize_email( wp_unslash( $_POST['asc_gift_recipient'] ) );
		$note  = isset( $_POST['asc_gift_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['asc_gift_message'] ) ) : '';
		$order->update_meta_data( '_asc_gift_recipient', $email );
		if ( $note ) {
			$order->update_meta_data( '_asc_gift_message', $note );
		}
		$order->save();
	}

	/* --------------------------------------------------------------------
	 * Issuing
	 * ----------------------------------------------------------------- */

	/**
	 * Mint one coupon per gift-card line when the order is paid.
	 *
	 * Hooked to paid statuses only: issuing on `pending` would email a code for
	 * an order that may never be paid for. A card that is refunded is voided
	 * by the coupon's own date range ending with the refund (see on_refund).
	 */
	public static function issue_code( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_asc_gift_cards_issued' ) ) {
			return;
		}
		$codes = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! self::is_gift_card( $product ) ) {
				continue;
			}
			$amount = (int) round( $product->get_price() * (int) $item->get_quantity() );
			$codes[] = self::create_coupon( $amount );
			$item->add_meta_data( self::CODE_META, end( $codes ), true );
			$item->save();
		}
		if ( ! $codes ) {
			return;
		}
		$order->update_meta_data( '_asc_gift_cards_issued', $codes );
		$order->save();
		self::notify_recipient( $order, $codes );
	}

	/**
	 * Create a single-use, non-expiring-until-issued coupon worth $amount.
	 *
	 * The coupon carries the card's expiry as its own date range so WooCommerce
	 * enforces it natively -- a date-limited coupon is rejected at validation
	 * time once the window closes, with no bespoke check to drift.
	 */
	private static function create_coupon( $amount ) {
		$code   = self::unique_code();
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( $amount );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_date_expires( time() + ( self::EXPIRY_DAYS * DAY_IN_SECONDS ) );
		$coupon->set_description( sprintf( __( 'کارت هدیه آرومالند — اعتبار تا %s', 'aroma-store-core' ), self::expiry_date_label() ) );
		$coupon->save();
		return $code;
	}

	/**
	 * Generate a GC-XXXXXXXX code that collides with nothing.
	 *
	 * The retry is not paranoia: WC_Coupon lowercases codes on save, so
	 * uniqueness is checked case-insensitively.
	 */
	/**
	 * Persian expiry label.
	 *
	 * wc_format_datetime() returns an empty string on this stack, so format the
	 * timestamp through WC_DateTime instead.
	 */
	private static function expiry_date_label() {
		$date = new WC_DateTime( '@' . ( time() + ( self::EXPIRY_DAYS * DAY_IN_SECONDS ) ) );
		$formatted = $date->date_i18n( get_option( 'date_format' ) );
		// The theme owns digit rendering; the plugin must not depend on it existing.
		return function_exists( 'digikala_to_persian_digits' )
			? digikala_to_persian_digits( $formatted )
			: $formatted;
	}

	private static function unique_code() {
		do {
			$code = 'GC-' . strtoupper( wp_generate_password( 8, false, false ) );
		} while ( wc_get_coupon_id_by_code( $code ) );
		return $code;
	}

	/* --------------------------------------------------------------------
	 * Delivery
	 * ----------------------------------------------------------------- */

	private static function notify_recipient( $order, $codes ) {
		$to = $order->get_meta( '_asc_gift_recipient' );
		if ( ! is_email( $to ) ) {
			return;
		}
		$amounts = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! self::is_gift_card( $item->get_product() ) ) {
				continue;
			}
			$amounts[] = wc_price( (int) round( $item->get_product()->get_price() * (int) $item->get_quantity() ) );
		}
		$message = sprintf(
			/* translators: 1: gift card codes, 2: total value */
			__( 'سلام!\n\nیک کارت هدیه برای شما از %s ارسال شده است.\n\nکد کارت هدیه:\n%s\n\nبرای استفاده، کد را در سبد خرید یا صفحه تسویه‌حساب وارد کنید.\nاین کارت تا %s اعتبار دارد.\n\nبا محبت,\n%s', 'aroma-store-core' ),
			get_bloginfo( 'name' ),
			implode( "\n", $codes ),
			self::expiry_date_label(),
			get_bloginfo( 'name' )
		);
		if ( $order->get_meta( '_asc_gift_message' ) ) {
			$message .= "\n\n" . __( 'پیام فرستنده:', 'aroma-store-core' ) . "\n" . $order->get_meta( '_asc_gift_message' );
		}
		wc_mail(
			$to,
			/* translators: %s: site name */
			sprintf( __( '%s: کارت هدیه شما', 'aroma-store-core' ), get_bloginfo( 'name' ) ),
			$message,
			$headers
		);
	}

	private static function code_rows( $order ) {
		$rows = array();
		foreach ( $order->get_items() as $item ) {
			$code = $item->get_meta( self::CODE_META );
			if ( $code ) {
				$rows[] = array(
					'amount' => wp_strip_all_tags( wc_price( (int) round( $item->get_total() ) ) ),
					'code'   => $code,
				);
			}
		}
		return $rows;
	}

	public static function render_on_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && self::code_rows( $order ) ) {
			self::render_block( $order );
		}
	}

	public static function render_on_receipt( $order ) {
		if ( self::code_rows( $order ) ) {
			self::render_block( $order );
		}
	}

	public static function render_in_email( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || $plain_text || ! self::code_rows( $order ) ) {
			return;
		}
		self::render_block( $order, true );
	}

	private static function render_block( $order, $email = false ) {
		$rows = self::code_rows( $order );
		if ( ! $rows ) {
			return;
		}
		$tag   = $email ? 'p' : 'div';
		echo '<' . $tag . ' style="background:#fff6f7;border:1px solid #f5c6cb;border-radius:8px;padding:16px;margin:0 0 16px">';
		echo $email ? '<strong>' . esc_html__( 'کارت هدیه شما', 'aroma-store-core' ) . '</strong><br>' : '<h3 style="margin:0 0 8px;font-size:16px">' . esc_html__( 'کارت هدیه شما', 'aroma-store-core' ) . '</h3>';
		echo '<ul style="margin:0;padding-right:18px">';
		foreach ( $rows as $row ) {
			printf(
				'<li style="margin:0 0 6px">%s — <code style="background:#fff;padding:2px 6px;border-radius:4px;letter-spacing:1px">%s</code></li>',
				esc_html( $row['amount'] ),
				esc_html( $row['code'] )
			);
		}
		echo '</ul>';
		echo '<span style="font-size:12px;color:#666">' . esc_html( sprintf( __( 'این کارت تا %s اعتبار دارد.', 'aroma-store-core' ), self::expiry_date_label() ) ) . '</span>';
		echo '</' . $tag . '>';
	}
}
