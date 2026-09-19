<?php
/**
 * Mobile OTP login & registration (Digikala-style two-step flow).
 *
 * Step 1 (asc_otp_request): user submits mobile -> normalize, rate-limit,
 *           generate 6-digit code, store hashed with expiry, SMS it.
 * Step 2 (asc_otp_verify):  user submits mobile+code -> check, then log in
 *           the matching user or create one (role customer, billing_phone set).
 *
 * SMS delivery goes through persian-woocommerce-sms (PWSMS()->send_sms()).
 * When no real gateway is configured it uses the Logger gateway, which
 * writes to wc-logs/pwsms.log — the dev/test sink.
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_OTP {

	const CODE_TTL      = 120;   // seconds
	const RESEND_WAIT   = 60;    // seconds between sends
	const MAX_ATTEMPTS  = 5;     // verify attempts per code
	const MAX_PER_HOUR  = 5;     // requests per mobile per hour
	const CODE_TRANSIENT = 'asc_otp_%s';
	const RATE_TRANSIENT = 'asc_otp_rate_%s';

	public function __construct() {
		add_action( 'wp_ajax_nopriv_asc_otp_request', array( $this, 'ajax_request' ) );
		add_action( 'wp_ajax_asc_otp_request', array( $this, 'ajax_request' ) );
		add_action( 'wp_ajax_nopriv_asc_otp_verify', array( $this, 'ajax_verify' ) );
		add_action( 'wp_ajax_asc_otp_verify', array( $this, 'ajax_verify' ) );
	}

	/**
	 * Normalize any mobile format (Persian digits, +98, 0098, dashes) to 09xxxxxxxxx.
	 * Self-contained so it doesn't depend on PWSMS being active.
	 */
	public static function normalize_mobile( $raw ) {
		$raw = trim( (string) $raw );
		if ( function_exists( 'PWSMS' ) ) {
			$mobile = PWSMS()->modify_mobile( $raw );
		} else {
			$fa = array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' );
			$mobile = strtr( $raw, $fa );
			$mobile = preg_replace( '/\D/', '', $mobile );
			if ( strpos( $mobile, '0098' ) === 0 ) {
				$mobile = '0' . substr( $mobile, 4 );
			} elseif ( strpos( $mobile, '+98' ) === 0 ) {
				$mobile = '0' . substr( $mobile, 3 );
			} elseif ( strpos( $mobile, '98' ) === 0 && strlen( $mobile ) === 12 ) {
				$mobile = '0' . substr( $mobile, 2 );
			} elseif ( strpos( $mobile, '9' ) === 0 && strlen( $mobile ) === 10 ) {
				$mobile = '0' . $mobile;
			}
		}
		if ( preg_match( '/^\+989(\d{9})$/', $mobile ) ) {
			$mobile = '0' . substr( $mobile, 3 );
		}
		return ( preg_match( '/^09\d{9}$/', $mobile ) ) ? $mobile : '';
	}

	public static function find_user_by_mobile( $mobile ) {
		$users = get_users( array(
			'meta_key'   => 'billing_phone',
			'meta_value' => $mobile,
			'number'     => 1,
			'fields'     => 'all',
		) );
		return $users ? $users[0] : null;
	}

	public function ajax_request() {
		check_ajax_referer( 'asc_otp', 'nonce' );

		$mobile = self::normalize_mobile( isset( $_POST['mobile'] ) ? wp_unslash( $_POST['mobile'] ) : '' );
		if ( ! $mobile ) {
			wp_send_json_error( array( 'message' => __( 'شماره موبایل معتبر نیست.', 'lylyrose-core' ) ) );
		}

		// Rate limit: MAX_PER_HOUR requests per mobile per hour.
		$rate_key = sprintf( self::RATE_TRANSIENT, md5( $mobile ) );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= self::MAX_PER_HOUR ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست‌ها بیش از حد مجاز است. بعداً تلاش کنید.', 'lylyrose-core' ) ) );
		}
		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

		// Resend cooldown.
		$code_key = sprintf( self::CODE_TRANSIENT, md5( $mobile ) );
		$pending  = get_transient( $code_key );
		if ( $pending && ( time() - (int) $pending['sent_at'] ) < self::RESEND_WAIT ) {
			$wait = self::RESEND_WAIT - ( time() - (int) $pending['sent_at'] );
			wp_send_json_error( array( 'message' => sprintf( __( 'برای ارسال مجدد %d ثانیه صبر کنید.', 'lylyrose-core' ), $wait ) ) );
		}

		$code = (string) wp_rand( 100000, 999999 );

		set_transient( $code_key, array(
			'code'      => wp_hash_password( $code ),
			'sent_at'   => time(),
			'attempts'  => 0,
		), self::CODE_TTL );

		$sent = $this->send_code( $mobile, $code );
		if ( is_wp_error( $sent ) ) {
			delete_transient( $code_key );
			wp_send_json_error( array( 'message' => __( 'ارسال پیامک ناموفق بود. دوباره تلاش کنید.', 'lylyrose-core' ) ) );
		}

		$masked = substr( $mobile, 0, 4 ) . '***' . substr( $mobile, 7 );
		wp_send_json_success( array( 'message' => __( 'کد تایید پیامک شد.', 'lylyrose-core' ), 'mobile' => $masked ) );
	}

	public function ajax_verify() {
		check_ajax_referer( 'asc_otp', 'nonce' );

		$mobile = self::normalize_mobile( isset( $_POST['mobile'] ) ? wp_unslash( $_POST['mobile'] ) : '' );
		$code   = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['code'] ) ) : '';

		if ( ! $mobile || strlen( $code ) !== 6 ) {
			wp_send_json_error( array( 'message' => __( 'شماره یا کد وارد شده معتبر نیست.', 'lylyrose-core' ) ) );
		}

		$code_key = sprintf( self::CODE_TRANSIENT, md5( $mobile ) );
		$pending  = get_transient( $code_key );
		if ( ! $pending ) {
			wp_send_json_error( array( 'message' => __( 'کد منقضی شده است. دوباره درخواست کنید.', 'lylyrose-core' ) ) );
		}

		if ( (int) $pending['attempts'] >= self::MAX_ATTEMPTS ) {
			delete_transient( $code_key );
			wp_send_json_error( array( 'message' => __( 'تعداد تلاش‌های ناموفق زیاد است. کد جدید درخواست کنید.', 'lylyrose-core' ) ) );
		}

		$pending['attempts']++;
		set_transient( $code_key, $pending, self::CODE_TTL );

		if ( ! wp_check_password( $code, $pending['code'] ) ) {
			wp_send_json_error( array( 'message' => __( 'کد وارد شده صحیح نیست.', 'lylyrose-core' ) ) );
		}

		delete_transient( $code_key );

		$user = self::find_user_by_mobile( $mobile );
		if ( ! $user ) {
			$user = $this->create_user( $mobile );
			if ( is_wp_error( $user ) ) {
				wp_send_json_error( array( 'message' => __( 'خطا در ثبت‌نام. دوباره تلاش کنید.', 'lylyrose-core' ) ) );
			}
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		wp_send_json_success( array( 'message' => __( 'خوش آمدید!', 'lylyrose-core' ), 'redirect' => wc_get_account_endpoint_url( 'dashboard' ) ) );
	}

	protected function send_code( $mobile, $code ) {
		$message = sprintf( __( "لیلی رز\nکد ورود شما: %s\nاعتبار: ۲ دقیقه", 'lylyrose-core' ), $code );

		if ( function_exists( 'PWSMS' ) ) {
			$result = PWSMS()->send_sms( array(
				'mobile'  => $mobile,
				'message' => $message,
			) );
			// send_sms returns true on success or an error string.
			if ( $result === true ) {
				return true;
			}
			return new WP_Error( 'asc_otp_sms', is_string( $result ) ? $result : 'sms failed' );
		}

		return new WP_Error( 'asc_otp_sms', 'sms plugin missing' );
	}

	protected function create_user( $mobile ) {
		$username = 'user_' . substr( $mobile, 2 ); // 09xxxxxxxxx -> user_xxxxxxxxxx
		if ( username_exists( $username ) ) {
			$username .= wp_rand( 10, 99 );
		}
		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_pass'    => wp_generate_password( 20 ),
			'display_name' => __( 'کاربر لیلی رز', 'lylyrose-core' ),
			'role'         => 'customer',
		) );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, 'billing_phone', $mobile );
		update_user_meta( $user_id, 'billing_country', 'IR' );
		do_action( 'asc_otp_user_created', $user_id, $mobile );
		return get_user_by( 'id', $user_id );
	}
}
