<?php
/**
 * Trust/service pages (درباره ما، تماس با ما، پیگیری سفارش، سوالات متداول).
 *
 * The pages are created from code so a fresh database (or the live host, which
 * has its own DB) self-heals on the next request instead of needing manual
 * setup. Page bodies live in the theme's page-<slug>.php templates, so content
 * stays version-controlled rather than trapped in post_content.
 *
 * @package Lylyrose_Core
 */

defined( 'ABSPATH' ) || exit;

class ASC_Store_Pages {

	/**
	 * Attempts allowed per IP within the rate-limit window (order lookup).
	 */
	const LOOKUP_MAX_ATTEMPTS = 12;
	const LOOKUP_WINDOW       = 900;

	/**
	 * Pages owned by the plugin: slug => title.
	 */
	public static function pages() {
		return array(
			'about'       => __( 'درباره ما', 'lylyrose-core' ),
			'contact'     => __( 'تماس با ما', 'lylyrose-core' ),
			'track-order' => __( 'پیگیری سفارش', 'lylyrose-core' ),
			'faq'         => __( 'سوالات متداول', 'lylyrose-core' ),
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_pages' ), 30 );
	}

	/**
	 * Create any missing page once per plugin version (cheap option check on
	 * other requests). Pages the admin trashed on purpose are not recreated
	 * unless the version changes, and an existing slug is never overwritten.
	 */
	public static function ensure_pages() {
		if ( get_option( 'asc_store_pages_version' ) === LYLYROSE_CORE_VERSION ) {
			return;
		}

		foreach ( self::pages() as $slug => $title ) {
			if ( get_page_by_path( $slug ) ) {
				continue;
			}
			wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_name'      => $slug,
					'post_title'     => $title,
					'post_status'    => 'publish',
					'post_content'   => '',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);
		}

		update_option( 'asc_store_pages_version', LYLYROSE_CORE_VERSION );
	}

	/**
	 * URL for one of the plugin pages (falls back to the slug path).
	 */
	public static function url( $slug ) {
		$page = get_page_by_path( $slug );
		return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
	}

	/**
	 * Latin digits for a string that may contain Persian/Arabic-Indic digits.
	 */
	public static function latin_digits( $value ) {
		return str_replace(
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ),
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			(string) $value
		);
	}

	/**
	 * Last 9 digits of an Iranian mobile number, so 09121234567,
	 * +989121234567 and 9121234567 all compare equal.
	 */
	public static function normalize_phone( $phone ) {
		$digits = preg_replace( '/[^0-9]/', '', self::latin_digits( $phone ) );
		return strlen( $digits ) > 9 ? substr( $digits, -9 ) : $digits;
	}

	/**
	 * Per-IP attempt counter; true when the caller is over the limit.
	 *
	 * Guards the order-lookup form against order-number enumeration.
	 */
	private static function rate_limited( $bucket ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'asc_rl_' . $bucket . '_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= self::LOOKUP_MAX_ATTEMPTS ) {
			return true;
		}
		set_transient( $key, $hits + 1, self::LOOKUP_WINDOW );
		return false;
	}

	/**
	 * Handle the پیگیری سفارش form.
	 *
	 * Requires the order number AND a matching billing email or phone, so the
	 * form only confirms what the visitor already knows.
	 *
	 * @return array{order:?WC_Order,error:string} Empty error when nothing was submitted.
	 */
	public static function handle_order_lookup() {
		$result = array(
			'order' => null,
			'error' => '',
		);

		if ( empty( $_POST['asc_track_submit'] ) ) {
			return $result;
		}

		if ( ! isset( $_POST['asc_track_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['asc_track_nonce'] ) ), 'asc_track_order' ) ) {
			$result['error'] = __( 'درخواست معتبر نیست. لطفاً صفحه را دوباره بارگذاری کنید.', 'lylyrose-core' );
			return $result;
		}

		if ( self::rate_limited( 'track' ) ) {
			$result['error'] = __( 'تعداد تلاش‌های شما زیاد بوده است. لطفاً چند دقیقه بعد دوباره تلاش کنید.', 'lylyrose-core' );
			return $result;
		}

		$order_id = (int) self::latin_digits( ltrim( sanitize_text_field( wp_unslash( $_POST['asc_order_id'] ?? '' ) ), '#' ) );
		$contact  = sanitize_text_field( wp_unslash( $_POST['asc_contact'] ?? '' ) );

		if ( ! $order_id || '' === $contact ) {
			$result['error'] = __( 'شماره سفارش و ایمیل یا شماره موبایل را وارد کنید.', 'lylyrose-core' );
			return $result;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() || ! self::contact_matches( $order, $contact ) ) {
			// Same message for "no such order" and "wrong contact" so the form
			// can't be used to discover which order numbers exist.
			$result['error'] = __( 'سفارشی با این اطلاعات پیدا نشد. شماره سفارش و ایمیل/موبایل را بررسی کنید.', 'lylyrose-core' );
			return $result;
		}

		$result['order'] = $order;
		return $result;
	}

	/**
	 * True when the submitted email or phone belongs to the order.
	 */
	private static function contact_matches( $order, $contact ) {
		if ( is_email( $contact ) ) {
			return strtolower( $contact ) === strtolower( (string) $order->get_billing_email() );
		}
		$phone = self::normalize_phone( $contact );
		if ( strlen( $phone ) < 9 ) {
			return false;
		}
		foreach ( array( $order->get_billing_phone(), $order->get_shipping_phone() ) as $candidate ) {
			if ( $phone === self::normalize_phone( $candidate ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handle the تماس با ما form: mails the site admin.
	 *
	 * @return array{sent:bool,error:string,values:array}
	 */
	public static function handle_contact_form() {
		$result = array(
			'sent'   => false,
			'error'  => '',
			'values' => array( 'name' => '', 'email' => '', 'subject' => '', 'message' => '' ),
		);

		if ( empty( $_POST['asc_contact_submit'] ) ) {
			return $result;
		}

		if ( ! isset( $_POST['asc_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['asc_contact_nonce'] ) ), 'asc_contact_form' ) ) {
			$result['error'] = __( 'درخواست معتبر نیست. لطفاً صفحه را دوباره بارگذاری کنید.', 'lylyrose-core' );
			return $result;
		}

		// Honeypot: bots fill every field, humans never see this one.
		if ( ! empty( $_POST['asc_website'] ) ) {
			$result['sent'] = true;
			return $result;
		}

		$values = array(
			'name'    => sanitize_text_field( wp_unslash( $_POST['asc_name'] ?? '' ) ),
			'email'   => sanitize_email( wp_unslash( $_POST['asc_email'] ?? '' ) ),
			'subject' => sanitize_text_field( wp_unslash( $_POST['asc_subject'] ?? '' ) ),
			'message' => sanitize_textarea_field( wp_unslash( $_POST['asc_message'] ?? '' ) ),
		);
		$result['values'] = $values;

		if ( '' === $values['name'] || '' === $values['message'] ) {
			$result['error'] = __( 'نام و متن پیام را وارد کنید.', 'lylyrose-core' );
			return $result;
		}
		if ( '' === $values['email'] || ! is_email( $values['email'] ) ) {
			$result['error'] = __( 'ایمیل معتبر وارد کنید تا بتوانیم پاسخ دهیم.', 'lylyrose-core' );
			return $result;
		}
		if ( self::rate_limited( 'contact' ) ) {
			$result['error'] = __( 'تعداد پیام‌های ارسالی شما زیاد بوده است. لطفاً بعداً تلاش کنید.', 'lylyrose-core' );
			return $result;
		}

		$body = sprintf( __( 'نام: %s', 'lylyrose-core' ), $values['name'] ) . "\n"
			. sprintf( __( 'ایمیل: %s', 'lylyrose-core' ), $values['email'] ) . "\n"
			. sprintf( __( 'موضوع: %s', 'lylyrose-core' ), ( '' !== $values['subject'] ? $values['subject'] : '—' ) ) . "\n\n"
			. $values['message'];

		$sent = wp_mail(
			get_option( 'admin_email' ),
			sprintf( __( 'پیام تماس با ما — %s', 'lylyrose-core' ), ( '' !== $values['subject'] ? $values['subject'] : $values['name'] ) ),
			$body,
			array( 'Reply-To: ' . $values['email'] )
		);

		if ( ! $sent ) {
			$result['error'] = __( 'ارسال پیام با خطا مواجه شد. لطفاً از طریق تلفن یا شبکه‌های اجتماعی با ما تماس بگیرید.', 'lylyrose-core' );
			return $result;
		}

		$result['sent']   = true;
		$result['values'] = array( 'name' => '', 'email' => '', 'subject' => '', 'message' => '' );
		return $result;
	}
}
