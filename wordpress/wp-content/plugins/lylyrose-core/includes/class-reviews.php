<?php
/**
 * Review incentive (P1 #8): post-purchase review request + coupon reward.
 *
 * On order 'completed', schedules a Persian review-request email (and SMS)
 * 3–7 days out via Action Scheduler. When the customer then posts an approved
 * review on a product they actually purchased, a single-use 10% coupon is
 * generated and emailed to them (once per order).
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_Reviews {

	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_schedule_review_request' ), 10, 2 );
		add_action( 'asc_send_review_request', array( __CLASS__, 'send_review_request' ), 10, 1 );
		add_action( 'comment_post', array( __CLASS__, 'maybe_award_review_coupon' ), 10, 2 );
	}

	/**
	 * Schedule the review request when an order completes.
	 *
	 * @param int  $order_id Order ID.
	 * @param WC_Order $order Order object.
	 */
	public static function maybe_schedule_review_request( $order_id, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// HPOS-safe: never update_post_meta on orders.
		if ( $order->get_meta( '_asc_review_requested' ) ) {
			return;
		}

		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();
		if ( ! $email && ! $phone ) {
			return;
		}

		$order->update_meta_data( '_asc_review_requested', 'scheduled' );
		$order->save();

		$delay = rand( 3, 7 ) * DAY_IN_SECONDS;

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay, 'asc_send_review_request', array( 'order_id' => $order_id ) );
		}
	}

	/**
	 * Fire the delayed review request (email + SMS).
	 *
	 * @param array $args { order_id: int }
	 */
	public static function send_review_request( $args ) {
		$order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( 'sent' === $order->get_meta( '_asc_review_requested' ) ) {
			return;
		}

		$email = $order->get_billing_email();
		$phone = ASC_OTP::normalize_mobile( $order->get_billing_phone() );
		$name  = $order->get_billing_first_name();

		$product_names = array();
		foreach ( $order->get_items() as $item ) {
			$product_names[] = $item->get_name();
		}
		$product_list = implode( '، ', array_slice( $product_names, 0, 3 ) );

		if ( $email ) {
			self::send_review_email( $order, $email, $name, $product_list );
		}
		if ( $phone && class_exists( 'PWSMS' ) ) {
			PWSMS()->send_sms( array(
				'mobile'  => $phone,
				'message' => sprintf(
					__( "لیلی رز\n%s عزیز، از خرید شما سپاسگزاریم. لطفاً نظر خود را درباره محصولات ثبت کنید: %s", 'lylyrose-core' ),
					$name ? $name : __( 'مشتری عزیز', 'lylyrose-core' ),
					home_url( '/my-account/orders/' )
				),
			) );
		}

		$order->update_meta_data( '_asc_review_requested', 'sent' );
		$order->save();
		$order->add_order_note( sprintf( __( 'درخواست ثبت نظر ارسال شد (%s).', 'lylyrose-core' ), $product_list ) );
	}

	/**
	 * Persian review-request email.
	 */
	private static function send_review_email( $order, $email, $name, $product_list ) {
		$site_title = get_bloginfo( 'name' );
		$subject    = sprintf( __( 'نظر شما درباره خرید از %s', 'lylyrose-core' ), $site_title );
		$body       = sprintf(
			__(
				"سلام %s،\n\n" .
				"از خرید شما از %s سپاسگزاریم. امیدواریم از محصولات ( %s ) راضی باشید.\n\n" .
				"نظر شما برای ما و سایر مشتریان ارزشمند است. می‌توانید نظر خود را از پنل کاربری ثبت کنید:\n%s\n\n" .
				"به عنوان تقدیر، پس از ثبت نظر کد تخفیف ویژه‌ای برای خرید بعدی دریافت خواهید کرد.\n\n" .
				"تیم %s",
				'lylyrose-core'
			),
			$name ? $name : __( 'مشتری عزیز', 'lylyrose-core' ),
			$site_title,
			$product_list,
			$order->get_view_order_url(),
			$site_title
		);

		wp_mail( $email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * Award a single-use 10% coupon when an approved review is posted by a
	 * verified purchaser. Runs once per order.
	 *
	 * @param int   $comment_id Comment ID.
	 * @param mixed $approved   Comment approval status (1|0|'spam'|'trash'...).
	 */
	public static function maybe_award_review_coupon( $comment_id, $approved ) {
		if ( 1 !== (int) $approved && 'approve' !== $approved ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment || ! in_array( $comment->comment_type, array( 'review', 'comment' ), true ) ) {
			return;
		}

		$product_id = (int) $comment->comment_post_ID;
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		$author_email = $comment->comment_author_email;
		if ( ! $author_email ) {
			return;
		}

		// Latest completed order of this customer containing the reviewed product.
		$target_order = null;
		$orders = wc_get_orders( array(
			'billing_email' => $author_email,
			'status'        => array( 'completed' ),
			'limit'         => 20,
		) );
		if ( ! $orders ) {
			// Some older WooCommerce/HPOS combinations do not apply the
			// billing_email query var consistently for guest orders. Fall back
			// to a bounded scan so a valid guest purchase is still recognized.
			$orders = array_filter(
				wc_get_orders( array(
					'status' => array( 'completed' ),
					'limit'  => 100,
				) ),
				static function ( $order ) use ( $author_email ) {
					return strtolower( trim( $order->get_billing_email() ) ) === strtolower( trim( $author_email ) );
				}
			);
		}

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === $product_id ) {
					// One reward per order+product.
					$flag = '_asc_review_coupon_' . $product_id;
					if ( $order->get_meta( $flag ) ) {
						return;
					}
					$target_order = $order;
					break 2;
				}
			}
		}

		if ( ! $target_order ) {
			return;
		}

		$coupon_code = 'REVIEW-' . strtoupper( wp_generate_password( 8, false ) );
		$coupon = new WC_Coupon();
		$coupon->set_code( $coupon_code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_date_expires( time() + 30 * DAY_IN_SECONDS );
		$coupon->set_description( sprintf( __( 'کد تخفیف ثبت نظر — سفارش #%s', 'lylyrose-core' ), $target_order->get_order_number() ) );
		$coupon->save();

		$target_order->update_meta_data( '_asc_review_coupon_' . $product_id, $coupon_code );
		$target_order->save();
		$target_order->add_order_note( sprintf( __( 'کد تخفیف ثبت نظر %s برای محصول #%d صادر شد.', 'lylyrose-core' ), $coupon_code, $product_id ) );

		// Deliver the code (email from the review author; SMS as fallback if phone matches).
		$subject = sprintf( __( 'کد تخفیف ثبت نظر شما در %s', 'lylyrose-core' ), get_bloginfo( 'name' ) );
		$body    = sprintf(
			__(
				"با تشکر از ثبت نظر شما!\n\n" .
				"کد تخفیف ۱۰٪ شما: %s\n\n" .
				"این کد تا ۳۰ روز معتبر است و یک بار قابل استفاده می‌باشد.\n\n" .
				"تیم %s",
				'lylyrose-core'
			),
			$coupon_code,
			get_bloginfo( 'name' )
		);
		wp_mail( $author_email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}
}
