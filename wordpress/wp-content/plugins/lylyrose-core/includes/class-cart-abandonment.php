<?php
/**
 * Cart abandonment SMS follow-up via PWSMS.
 *
 * Hooks the Cart Abandonment Recovery plugin's `wcf_ca_process_abandoned_order`
 * action (fires once per cart when it flips normal → abandoned after the cut-off).
 *
 * Extracts phone from captured checkout fields, normalizes via ASC_OTP,
 * sends Persian SMS with recovery link + coupon (if any).
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_Cart_Abandonment {

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_action( 'wcf_ca_process_abandoned_order', array( $this, 'maybe_send_sms' ), 10, 1 );
	}

	/**
	 * Send Persian SMS reminder when a cart becomes abandoned.
	 *
	 * @param object $checkout_details Cartflows_Ca_Helper::get_checkout_details() result.
	 */
	public function maybe_send_sms( $checkout_details ) {
		if ( ! $checkout_details ) {
			return;
		}

		// Respect customer opt-out (unsubscribe link in follow-ups).
		if ( ! empty( $checkout_details->unsubscribed ) ) {
			return;
		}

		// Only remind abandoned carts, never completed/lost ones.
		if ( ! empty( $checkout_details->order_status ) && 'abandoned' !== $checkout_details->order_status ) {
			return;
		}

		// Extract captured fields from the serialized other_fields column.
		$other_fields = maybe_unserialize( $checkout_details->other_fields );
		if ( ! $other_fields || empty( $other_fields['wcf_phone_number'] ) ) {
			// No phone captured — nothing to do.
			return;
		}

		// Normalize phone (handles Persian digits, +98, 0098, etc.).
		$mobile = ASC_OTP::normalize_mobile( $other_fields['wcf_phone_number'] );
		if ( ! $mobile ) {
			return;
		}

		// Build recovery URL (tokenized checkout link the plugin generates).
		$checkout_id   = $checkout_details->checkout_id ?? 0;
		$session_id    = $checkout_details->session_id ?? '';
		$token_data    = array( 'wcf_session_id' => $session_id );
		$helper        = Cartflows_Ca_Helper::get_instance();
		$recovery_url  = $helper->get_checkout_url( $checkout_id, $token_data );

		// Coupon code if one was generated for this abandonment.
		$coupon_code   = $checkout_details->coupon_code ?? '';
		$coupon_text   = $coupon_code ? sprintf( __( "\nکد تخفیف: %s", 'lylyrose-core' ), $coupon_code ) : '';

		// Persian SMS message.
		$message = sprintf(
			__( "لیلی رز\nسبد خرید شما در انتظار تکمیل است. برای ادامه کلیک کنید: %s%s\nاعتبار: ۳۰ روز", 'lylyrose-core' ),
			$recovery_url,
			$coupon_text
		);

		// Send via PWSMS (Logger gateway writes to wc-logs/pwsms.log in dev).
		$result = PWSMS()->send_sms( array(
			'mobile'  => $mobile,
			'message' => $message,
		) );

		if ( is_wp_error( $result ) || $result !== true ) {
			// Log failure for debugging (dev only).
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'ASC_Cart_Abandonment: SMS failed for ' . $mobile . ' — ' . print_r( $result, true ) );
			}
		}
	}
}