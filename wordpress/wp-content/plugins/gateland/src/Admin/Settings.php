<?php

namespace Nabik\Gateland\Admin;

use Nabik\Gateland\Gateland;
use Nabik\GatelandPro\GatelandPro;
use Nabik_Net_License;

defined( 'ABSPATH' ) || exit;

class Settings extends \Nabik\Utils\V1\Settings {

	protected static $_instance = null;

	public static function output() {

		$instance = self::instance();

		echo '<div class="wrap">';

		$instance->init();
		$instance->show_navigation();
		$instance->show_forms();

		echo '</div>';
	}

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function get_sections(): array {
		$sections = [
			[
				'id'    => 'gateland_general',
				'title' => 'تنظیمات',
			],
			[
				'id'    => 'gateland_sms',
				'title' => 'پیامک',
			],
			[
				'id'    => 'gateland_bot',
				'title' => 'ربات',
			],
			[
				'id'    => 'gateland_proxy',
				'title' => 'پروکسی',
			],
			[
				'id'    => 'gateland_zohal',
				'title' => 'زحل',
			],
			class_exists( GatelandPro::class ) ? [
				'id'    => 'gateland_license',
				'title' => 'لایسنس',
			] : [],
		];

		return array_filter( $sections );
	}

	public function get_fields(): array {

		$color       = '';
		$message     = '';
		$last_log    = null;
		$license_url = null;
		$has_pro     = false;

		if ( class_exists( GatelandPro::class ) ) {

			if ( ! GatelandPro::is_active() &&
			     isset( $_GET['nabik_license'], $_GET['csrf'] ) &&
			     wp_verify_nonce( $_GET['csrf'], 'gateland_save_license' )
			) {
				update_option( 'gateland_pro_license_key', sanitize_text_field( $_GET['nabik_license'] ) );

				Nabik_Net_License::log( GATELAND_PRO_DIR, null );
			}

			if ( GatelandPro::is_active() ) {

				$has_pro = true;

				$color   = 'green';
				$message = 'افزونه با موفقیت فعال شده است.';

				Nabik_Net_License::log( GATELAND_PRO_DIR, null );

			} else {

				$color   = 'red';
				$message = GatelandPro::$error . ' - ' . GatelandPro::$message;

				if ( file_exists( GATELAND_PRO_DIR . '/error.log' ) ) {
					$logs     = file_get_contents( GATELAND_PRO_DIR . '/error.log' );
					$logs     = explode( PHP_EOL, $logs );
					$logs     = array_values( array_filter( array_reverse( $logs ) ) );
					$last_log = $logs[0] ?? null;
				}

				if ( Gateland::get_option( 'license.key' ) ) {
					$callback_url = add_query_arg( [
						'page' => 'gateland-settings',
						'csrf' => wp_create_nonce( 'gateland_save_license' ),
					], admin_url( 'admin.php?page=gateland-settings' ) );

					$license_url = add_query_arg( [
						'callback_url' => urlencode( $callback_url ),
						'key'          => Gateland::get_option( 'license.key' ),
						'ver'          => GatelandPro::get_version(),
					], 'https://nabik.net/wp-json/license/v1/redirect' );
				}

			}

		}

		$message = sprintf( '<span style="color: %s">%s</span>', $color, $message );

		return [
			'gateland_general' => [
				[
					'id'      => 'gateway_order',
					'label'   => 'نوع انتخاب درگاه',
					'default' => 'sort',
					'type'    => 'select',
					'options' => [
						'sort'         => 'ترتیب',
//						'random'       => 'تصادفی',
						'amount'       => 'تقسیم بر ا‌ساس مبلغ',
						'transactions' => 'تقسیم بر اساس تعداد تراکنش',
					],
					'desc'    => 'نحوه اولویت بندی درگاه‌ها',
				],
				[
					'id'      => 'iran_access',
					'label'   => 'ایران اکسس',
					'default' => '0',
					'type'    => 'checkbox',
					'desc'    => 'در صورت فعالسازی این گزینه، پرداخت صرفا از طریق آی.پی‌های ایرانی امکان پذیر خواهد بود.' . ( $has_pro ? '' : '(این امکان فقط در <a href="https://l.nabik.net/gateland-pro/?utm_source=iran_access" target="_blank">نسخه حرفه‌ای</a> فعال می‌باشد)' ),
				],
			],
			'gateland_sms'     => [
				[
					'id'    => 'shortcode',
					'label' => 'راهنما',
					'desc'  => '۱. تنظیمات درگاه پیامک را از منو‌ <b>نابیک > پیامک</b> انجام دهید.<br>
					۲. برای ارسال نکردن پیامک در هر رویدادی، آن را خالی بگذارید.<br>
					۳. برای تنظیم متن پیامک می‌توانید از متغیرهای زیر استفاده کنید:
					<ul>
								<li><strong>{pay_url}</strong> آدرس پرداخت</li>
								<li><strong>{first_name}</strong> نام مشتری</li>
								<li><strong>{last_name}</strong> نام خانوادگی مشتری</li>
								<li><strong>{order_id}</strong> شناسه سفارش</li>
								<li><strong>{transaction_id}</strong> شناسه تراکنش</li>
								<li><strong>{transaction_token}</strong> توکن تراکنش</li>
								<li><strong>{description}</strong> توضیحات تراکنش</li>
								<li><strong>{amount}</strong> مبلغ تراکنش</li>
								</ul>',
					'type'  => 'html',
				],
				[
					'label'   => 'آدرس پرداخت',
					'id'      => 'pay_link',
					'default' => 'pay',
					'type'    => 'text',
					'desc'    => sprintf( 'لینک پرداختی که برای کاربر ارسال می‌شود.
					</br>
					برای مثال اگر شما pay‌ وارد کنید، آدرس پرداخت می‌شود: %s', site_url( 'pay' ) ),
					// @todo add more description, move to advanced section
				],
				[
					'label'   => 'پیامک ایجاد تراکنش',
					'id'      => 'transaction_created_sms',
					'default' => '',
					'type'    => 'textarea',
					'desc'    => 'پس از ساخته شدن تراکنش، این پیامک به کاربر ارسال می‌شود.',
				],
				[
					'label'   => 'پیامک تغییر وضعیت تراکنش به ناموفق',
					'id'      => 'transaction_failed_sms',
					'default' => '',
					'type'    => 'textarea',
					'desc'    => 'پس از تغییر وضعیت تراکنش به <strong>ناموفق</strong>، این پیامک به کاربر ارسال می‌شود.',
				],
				[
					'label'   => 'پیامک تغییر وضعیت تراکنش به پرداخت شده',
					'id'      => 'transaction_paid_sms',
					'default' => '',
					'type'    => 'textarea',
					'desc'    => 'پس از تغییر وضعیت تراکنش به <strong>پرداخت شده</strong>، این پیامک به کاربر ارسال می‌شود.',
				],
//				[
//					'label'   => 'پیامک تغییر وضعیت تراکنش به استرداد شده',
//					'id'      => 'transaction_refund_sms',
//					'default' => '',
//					'type'    => 'textarea',
//					'desc'    => 'پس از تغییر وضعیت تراکنش به <strong>استرداد شده</strong>، این پیامک به کاربر ارسال می‌شود.',
//				],

			],
			'gateland_bot'     => [
				$has_pro ? [] : [
					'id'   => 'gateway_bot',
					'type' => 'html',
					'desc' => 'برای دریافت گزارش تراکنش‌های موفق می‌توانید گیت‌لند حرفه‌ای را بدون نیاز به داشتن سرور مجزا، به ربات‌های تلگرام و بله متصل کنید.<br>
این قابلیت برای کاربران <a href="https://l.nabik.net/gateland-pro?utm-source=bot" target="_blank">نسخه حرفه‌ای</a> افزونه و بدون هزینه و محدودیت قابل استفاده می‌باشد.<br>',
				],
				[
					'id'    => 'help',
					'type'  => 'html',
					'label' => 'راهنما',
					'desc'  => 'برای استفاده از ربات‌های گیت‌لند، کافیست به ربات‌های زیر پیام بدهید و شناسه یکتا خود را دریافت کنید:<br>
ربات گیت لند در تلگرام: <a href="https://t.me/GatelandBot" target="_blank">https://t.me/GatelandBot</a><br>
ربات گیت لند در بله: <a href="https://ble.ir/GatelandBot" target="_blank">https://ble.ir/GatelandBot</a><br>',
				],
				[
					'id'      => 'recipient_ids',
					'label'   => 'شناسه یکتا ربات',
					'default' => null,
					'type'    => 'textarea',
					'desc'    => 'در هر سطر یک شناسه یکتا وارد کنید. (حداکثر ۵ شناسه یکتا)',
				],
			],
			'gateland_proxy'   => [
				[
					'id'      => 'enable',
					'label'   => 'فعالسازی پروکسی',
					'default' => '0',
					'type'    => 'checkbox',
					'desc'    => 'برای فعالسازی استفاده از پروکسی برای اتصال به درگاه‌ها، تیک بزنید.',
				],
				[
					'id'      => 'type',
					'label'   => 'پروتکل',
					'default' => 'http',
					'type'    => 'select',
					'options' => [
						'http'    => 'http',
						'socks4'  => 'socks4',
						'socks4a' => 'socks4a',
						'socks5'  => 'socks5',
					],
				],
				[
					'id'      => 'host',
					'label'   => 'میزبان',
					'default' => null,
					'type'    => 'text',
					'desc'    => 'Host - در صورت استفاده از پروکسی، IP پروکسی را به درگاه اعلام کنید.',
				],
				[
					'id'      => 'port',
					'label'   => 'پورت',
					'default' => null,
					'type'    => 'text',
					'desc'    => 'Port',
				],
				[
					'id'      => 'username',
					'label'   => 'نام کاربری',
					'default' => null,
					'type'    => 'text',
					'desc'    => 'Username',
				],
				[
					'id'      => 'password',
					'label'   => 'کلمه عبور',
					'default' => null,
					'type'    => 'text',
					'desc'    => 'Password',
				],
			],
			'gateland_zohal'   => [
				[
					'id'   => 'introduce',
					'type' => 'html',
					'desc' => 'زحل ارائه دهنده سرویس‌های استعلام و احراز هویت است. با فعالسازی زحل از امکانات استعلام بانکی آن بهره ببرید.',
				],
				[
					'id'      => 'api_key',
					'label'   => 'توکن',
					'type'    => 'text',
					'default' => '',
					'desc'    => 'برای دریافت توکن زحل، <a href="https://l.nabik.net/zohal?utm_source=gateland" target="_blank"> ثبت نام کرده و وارد شوید</a>، سپس از منو توسعه‌دهنگان یک توکن ایجاد کنید',
				],
			],
			'gateland_license' => [
				[
					'label' => 'وضعیت',
					'id'    => 'status',
					'desc'  => $message,
					'type'  => 'html',
				],
				[
					'label'       => 'کلید لایسنس',
					'id'          => 'key',
					'default'     => '',
					'type'        => 'text',
					'placeholder' => 'NGL-PRO-',
					'desc'        => 'کلید لایسنس خود را از بخش حساب کاربری <a href="https://nabik.net/my-account/licenses/" target="_blank">نابیک</a> دریافت کنید.',
				],
				[
					'label' => 'نسخه گیت‌لند',
					'id'    => 'version',
					'desc'  => sprintf( 'رایگان: %s - حرفه‌ای: %s', GATELAND_VERSION, defined( 'GATELAND_PRO_VERSION' ) ? GATELAND_PRO_VERSION : '-' ),
					'type'  => 'html',
				],
				$last_log ? [
					'label' => 'لاگ',
					'id'    => 'log',
					'desc'  => sprintf(
						'خطایی در زمان فعالسازی لایسنس رخ داده است. <a href="%s" target="_blank">مشاهده لاگ</a><br>%s',
						GATELAND_PRO_URL . 'error.log',
						$last_log
					),
					'type'  => 'html',
				] : [],
				$license_url ? [
					'label' => 'فعالسازی دستی',
					'id'    => 'manual',
					'desc'  => sprintf( '<a href="%s">برای فعالسازی دستی لایسنس، اینجا کلیک کنید.</a>', $license_url ),
					'type'  => 'html',
				] : [],
			],
		];
	}

	function admin_init() {
		parent::admin_init();

		Gateland::addRewriteRules();
		// Flush rules for custom urls
		flush_rewrite_rules();
	}

}
