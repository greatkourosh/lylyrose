<?php
/**
 * Persian/branded transactional emails.
 *
 * WooCommerce's fa_IR pack misses the email_improvements-era default
 * subject/heading/additional-content strings, so those emails go out in
 * English. gettext_woocommerce forces Persian for the known English
 * defaults; woocommerce_email_styles adds explicit RTL direction CSS to
 * complement the dir="rtl" attribute on the wrapper.
 */

defined( 'ABSPATH' ) || exit;

class ASC_Emails {

	/**
	 * English source string => Persian replacement (woocommerce text domain).
	 *
	 * @var array<string,string>
	 */
	private static $strings = array(
		// Subjects.
		'[{site_title}]: You\'ve got a new order: #{order_number}'                            => '[{site_title}]: سفارش جدید: #{order_number}',
		'[{site_title}]: New order #{order_number}'                                           => '[{site_title}]: سفارش جدید: #{order_number}',
		'[{site_title}]: Order #{order_number} has been cancelled'                            => '[{site_title}]: سفارش #{order_number} لغو شد',
		'[{site_title}]: Your order #{order_number} has been cancelled'                       => '[{site_title}]: سفارش شما #{order_number} لغو شد',
		'[{site_title}]: Order #{order_number} has failed'                                    => '[{site_title}]: سفارش #{order_number} ناموفق بود',
		'Your order at {site_title} was unsuccessful'                                         => 'سفارش شما در {site_title} ناموفق بود',
		'Your order from {site_title} is on its way!'                                         => 'سفارش شما از {site_title} در راه است!',
		'Your {site_title} order is now complete'                                             => 'سفارش شما در {site_title} تکمیل شد',
		'Your {site_title} order #{order_number} has been refunded'                           => 'سفارش #{order_number} شما در {site_title} بازپرداخت شد',
		'Your {site_title} order #{order_number} has been partially refunded'                 => 'بازپرداخت جزئی سفارش #{order_number} شما در {site_title}',
		'A note has been added to your order from {site_title}'                               => 'یادداشتی به سفارش شما در {site_title} اضافه شد',
		'Note added to your {site_title} order from {order_date}'                             => 'یادداشتی در {order_date} به سفارش شما در {site_title} اضافه شد',
		'Reset your password for {site_title}'                                                => 'بازنشانی رمز عبور برای {site_title}',
		'Password Reset Request for {site_title}'                                             => 'درخواست بازنشانی رمز عبور در {site_title}',
		'Confirm your email address for {site_title}'                                         => 'تأیید آدرس ایمیل شما در {site_title}',
		'[{site_title}] Payment gateway "{gateway_title}" enabled'                            => '[{site_title}] درگاه پرداخت «{gateway_title}» فعال شد',

		// Headings.
		'New order: #{order_number}'                                                          => 'سفارش جدید: #{order_number}',
		'New Order: #{order_number}'                                                          => 'سفارش جدید: #{order_number}',
		'Order cancelled: #{order_number}'                                                    => 'سفارش لغو شد: #{order_number}',
		'Order Cancelled: #{order_number}'                                                    => 'سفارش لغو شد: #{order_number}',
		'Order failed: #{order_number}'                                                       => 'سفارش ناموفق بود: #{order_number}',
		'Order Failed: #{order_number}'                                                       => 'سفارش ناموفق بود: #{order_number}',
		'Sorry, your order was unsuccessful'                                                  => 'متأسفانه سفارش شما ناموفق بود',
		'Good things are heading your way!'                                                   => 'خبرهای خوب در راه است!',
		'Partial refund: Order {order_number}'                                                => 'بازپرداخت جزئی: سفارش {order_number}',
		'Partial Refund: Order {order_number}'                                                => 'بازپرداخت جزئی: سفارش {order_number}',
		'Order refunded: {order_number}'                                                      => 'سفارش بازپرداخت شد: {order_number}',
		'Order Refunded: {order_number}'                                                      => 'سفارش بازپرداخت شد: {order_number}',
		'A note has been added to your order'                                                 => 'یادداشتی به سفارش شما اضافه شد',
		'Reset your password'                                                                 => 'بازنشانی رمز عبور',
		'Confirm your email address'                                                          => 'تأیید آدرس ایمیل',
		'Payment gateway "{gateway_title}" enabled'                                           => 'درگاه پرداخت «{gateway_title}» فعال شد',

		// Additional content.
		'Thanks again! If you need any help with your order, please contact us at {store_email}.'   => 'باز هم سپاسگزاریم! اگر درباره سفارش خود پرسشی دارید، با ما در {store_email} در تماس باشید.',
		'If you need any help with your order, please contact us at {store_email}.'                 => 'اگر درباره سفارش خود پرسشی دارید، با ما در {store_email} در تماس باشید.',
		'Congratulations on sale!'                                                            => 'تبریک! یک فروش جدید ثبت شد.',
		'Congratulations on sale.'                                                            => 'تبریک! یک فروش جدید ثبت شد.',
		'We hope they’ll be back soon! Read more about <a href="https://woocommerce.com/document/managing-orders/">troubleshooting failed payments</a>.' => 'امیدواریم به‌زودی دوباره خرید کنند. <a href="https://woocommerce.com/document/managing-orders/">راهنمای عیب‌یابی پرداخت‌های ناموفق</a>.',
		'We hope to see you again soon.'                                                      => 'امیدواریم به‌زودی دوباره شما را ببینیم.',
		'We look forward to seeing you soon.'                                                 => 'مشتاقانه منتظر دیدار شما هستیم.',
		'Thanks for reading.'                                                                 => 'سپاس از همراهی شما.',
		'Thanks for shopping with us.'                                                        => 'از خرید شما سپاسگزاریم.',
	);

	public static function init() {
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'translate' ), 100, 3 );
		add_filter( 'woocommerce_email_styles', array( __CLASS__, 'rtl_styles' ) );
	}

	public static function translate( $translation, $text, $domain ) {
		// LTR locale (EN): the store's own English email copy is what we want,
		// so don't force the Persian map on it. Under fa_IR we keep applying it
		// because the pack still misses several email_improvements-era strings.
		// Checked at render time (emails are built after locale resolution),
		// not at plugin load, where is_rtl() is unreliable.
		if ( function_exists( 'is_rtl' ) && ! is_rtl() ) {
			return $translation;
		}
		return isset( self::$strings[ $text ] ) ? self::$strings[ $text ] : $translation;
	}

	public static function rtl_styles( $css ) {
		if ( function_exists( 'is_rtl' ) && is_rtl() ) {
			$css .= "\n#wrapper { direction: rtl; }";
		}
		return $css;
	}
}
