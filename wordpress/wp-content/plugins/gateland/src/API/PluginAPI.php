<?php

namespace Nabik\Gateland\API;

use WP_REST_Request;

class PluginAPI extends RESTAPI {

	public function register_routes() {

		register_rest_route( 'gateland/plugin', 'list', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'list' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

	}

	public function list( WP_REST_Request $request ) {

		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugins = [
			[
				'file'         => 'woocommerce/woocommerce.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/woocommerce.svg', GATELAND_FILE ),
				'title'        => 'ووکامرس',
				'author'       => 'Automattic',
				'description'  => 'ووکامرس، یک فروشگاه ساز محبوب و پرکاربرد جهت ایجاد فروشگاه کالاهای فیزیکی و مجازی می‌باشد. این افزونه یکی از محبوب‌ترین نرم‌افزارهای فروشگاه‌ساز دنیاست.',
				'document_url' => 'https://nabik.net/education/connect-woocommerce-to-gateway-gateland/',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=woocommerce' ),
			],
			[
				'file'         => 'gravityforms/gravityforms.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/gravityforms.jpg', GATELAND_FILE ),
				'title'        => 'گرویتی فرمز',
				'author'       => 'GravityForms',
				'description'  => 'افزونه تجاری گرویتی فرمز یکی از فرم‌سازهای پرکاربرد می‌باشد که در ایران نیز محبوب و استفاده زیادی دارد. این افزونه دارای بومی ساز رایگان «گرویتی فرم فارسی» نیز می‌باشد.',
				'document_url' => 'https://nabik.net/education/connect-gravityforms-to-gateway-gateland/',
				'install_url'  => 'https://www.gravityforms.com',
			],
			[
				'file'         => 'woo-wallet/woo-wallet.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/woo-wallet.png', GATELAND_FILE ),
				'title'        => 'ترا والت',
				'author'       => 'StandaloneTech',
				'description'  => 'کیف پول ترا والت (ترا ولت) یک افزونه رایگان و متن باز برای ووکامرس می‌باشد. قابلیت شارژ ترا والت توسط گیت‌لند به صورت انحصاری ارائه می شود.',
				'document_url' => 'https://nabik.net/education/connect-terawallet-to-gateway-gateland/',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-wallet' ),
			],
			[
				'file'         => 'contact-form-7/wp-contact-form-7.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/contact-form-7.svg', GATELAND_FILE ),
				'title'        => 'فرم تماس ۷',
				'author'       => 'Takayuki Miyoshi',
				'description'  => 'فرم تماس ۷ یکی از فرم‌سازهای رایگان، متن‌باز و قدیمی وردپرس می‌باشد. این فرم ساز معمولا برای فرم‌های تماس با ما استفاده می‌شود.',
				'document_url' => 'https://nabik.net/education/connect-contactform7-to-gateway-gateland/',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=contact-form-7' ),
			],
			[
				'file'         => 'easy-digital-downloads/easy-digital-downloads.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/easy-digital-downloads.svg', GATELAND_FILE ),
				'title'        => 'Easy Digital Downloads',
				'author'       => 'Syed Balkhi',
				'description'  => 'اگر ووکامرس نیاز شما را برای فروش فایل برآورده نمی‌کند، ایزی دیجیتال دانلودز تمام آنچه برای فروش فایل و اشتراک نیاز دارید را در اختیار شما می‌گذارد.',
				'document_url' => 'https://nabik.net/education/connect-edd-to-gateway-gateland',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=easy-digital-downloads' ),
			],
			[
				'file'         => 'learndash/learndash.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/learndash.png', GATELAND_FILE ),
				'title'        => 'LearnDash',
				'author'       => 'LearnDash',
				'description'  => 'افزونه لرن‌دش یک محصول تجاری و پریمیوم برای مدیریت سیستم‌های آموزشی می‌باشد. برای خرید این افزونه می‌توانید به سایت لرن‌دش مراجعه کنید.',
				'document_url' => 'https://nabik.net/education/connect-learndash-to-gateway-gateland/',
				'install_url'  => 'https://www.learndash.com/',
			],
			[
				'file'         => 'learnpress/learnpress.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/learnpress.gif', GATELAND_FILE ),
				'title'        => 'LearnPress',
				'author'       => 'ThimPress',
				'description'  => 'لرن‌پرس یک افزونه متن‌باز و رایگان برای مدیریت آموزشگاه‌ها می‌باشد. با استفاده از این افزونه می‌توانید فرآیند فروش دوره و برگزاری آزمون را پیاده‌سازی کنید.',
				'document_url' => 'https://nabik.net/education/connect-learnpress-to-gateway-gateland/',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=learnpress' ),
			],
			[
				'file'         => 'lifterlms/lifterlms.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/lifterlms.svg', GATELAND_FILE ),
				'title'        => 'LifterLMS',
				'author'       => 'chrisbadgett',
				'description'  => 'سیستم مدیریت آموزش لیفتر، یک افزونه جدید، رایگان و متن باز برای برگزاری دوره‌های آموزش مجازی و همچنین برگزاری آزمون می‌باشد.',
				'document_url' => 'https://nabik.net/education/connect-lifter-lms-to-gateway-gateland/',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=lifterlms' ),
			],
			[
				'file'         => 'mycred/mycred.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/mycred.gif', GATELAND_FILE ),
				'title'        => 'myCred',
				'author'       => 'Saad Iqbal',
				'description'  => 'افزونه مای کرد یا امتیاز من، یک افزونه رایگان و متن‌باز برای پیاده سازی سیستم پاداش و مدال‌دهی می‌باشد. این افزونه به نوعی یک باشگاه مشتریان برای شما ایجاد می‌کند.',
				'document_url' => 'https://nabik.net/education/connect-mycred-to-gateway-gateland/',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=mycred' ),
			],
			[
				'file'         => 'paid-memberships-pro/paid-memberships-pro.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/paid-memberships-pro.png', GATELAND_FILE ),
				'title'        => 'Paid Memberships Pro',
				'author'       => 'Paid Memberships Pro',
				'description'  => 'افزونه عضویت ویژه Paid Memberships Pro به صورت رایگان و متن باز با امکاناتی مانند عضویت، انجمن، دوره، خبرنامه و... از سایت سازنده آن قابل دریافت و نصب می‌باشد.',
				'document_url' => 'https://nabik.net/education/connect-paid-memberships-pro-to-gateway-gateland/',
				'install_url'  => 'https://www.paidmembershipspro.com',
			],
			[
				'file'         => 'restrict-content/restrictcontent.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/restrict-content.svg', GATELAND_FILE ),
				'title'        => 'Restrict Content Pro',
				'author'       => 'StellarWP',
				'description'  => 'افزونه عضویت ویژه Restrict Content Pro یکی از افزونه‌های قدیمی برای ایجاد و فروش اشتراک ویژه می‌باشد. این افزونه به صورت رایگان از مخزن وردپرس قابل نصب می‌باشد.',
				'document_url' => 'https://nabik.net/education/connect-restrict-content-pro-to-gateway-gateland',
				'install_url'  => 'https://www.restrictcontent.com/',
			],
			[
				'file'         => 'wp-user-frontend/wp-user-frontend.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/wp-user-frontend.gif', GATELAND_FILE ),
				'title'        => 'WP User Frontend',
				'author'       => 'Tareq Hasan',
				'description'  => 'ایجاد فرم ارسال محتوا، ایجاد پروفایل، فرم عضویت، فروش اشتراک و محدودسازی محتوا از قابلیت‌های این افزونه کاربردی می‌باشد.',
				'document_url' => 'https://nabik.net/education/connect-wp-user-frontend-to-gateway-gateland/',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=wp-user-frontend' ),
			],
			[
				'file'         => 'give/give.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/give.jpg', GATELAND_FILE ),
				'title'        => 'GiveWP',
				'author'       => 'GiveWP',
				'description'  => 'افزونه گیو، راهکاری رایگان و متن‌باز برای راه‌اندازی سامانه‌های دریافت کمک مالی (دونیت) است که از مخزن وردپرس قابل دریافت است.',
				'document_url' => null,
				'coming_soon'  => true,
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=give' ),
			],
			[
				'file'         => 'wpforms-lite/wpforms.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/wpforms-lite.svg', GATELAND_FILE ),
				'title'        => 'WP Forms',
				'author'       => 'Syed Balkhi',
				'description'  => 'این افزونه‌ی ساده و قدرتمند که به صورت رایگان و متن باز منتشر شده است، یکی از پرنصب‌ترین و پرکاربردترین افزونه‌های فرم ساز در سطح دنیا می‌باشد.',
				'document_url' => null,
				'coming_soon'  => true,
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpforms-lite' ),
			],
			[
				'file'         => 'sliced-invoices/sliced-invoices.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/sliced-invoices.svg', GATELAND_FILE ),
				'title'        => 'Sliced Invoices',
				'author'       => 'SlicedInvoices',
				'author_url'   => 'https://profiles.wordpress.org/slicedinvoices',
				'description'  => 'این افزونه به صورت رایگان و متن باز در مخزن وردپرس قابل نصب می‌باشد. صدور صورتحساب و فاکتور و قابلیت پرداخت آنلاین از مهم‌ترین ویژگی‌های آن می‌باشد.',
				'document_url' => 'https://nabik.net/education/connect-sliced-invoices-to-gateway-gateland',
				'install_url'  => admin_url( 'plugin-install.php?tab=plugin-information&plugin=sliced-invoices' ),
			],
			[
				'file'         => 'bookly-addon-pro/main.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/bookly.png', GATELAND_FILE ),
				'title'        => 'Bookly Pro',
				'author'       => 'Nota-Info',
				'description'  => 'Bookly افزونه‌ای تجاری، حرفه‌ای و کاربرپسند برای مدیریت نوبت‌دهی و رزرو آنلاین است که امکان مدیریت خدمات، رزرو توسط مشتریان و پرداخت آنلاین را فراهم می‌کند.',
				'document_url' => null,
				'install_url'   => 'https://www.booking-wp-plugin.com/bookly-pro/',
			],
			[
				'file'         => 'wallet-for-woocommerce/wallet-for-woocommerce.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/wallet-for-woocommerce.png', GATELAND_FILE ),
				'title'        => 'Wallet for WooCommerce',
				'author'       => 'Flintop',
				'description'  => 'افزونه تجاری کیف پول ووکامرس به کاربران کمک می‌کند تا با افزودن اعتبار به حساب خود، با استفاده از موجودی کیف پول و خیلی سریع، خرید های خود را ثبت کنند.',
				'document_url' => null,
				'install_url'=> 'https://woocommerce.com/products/wallet-for-woocommerce/',
			],
			[
				'file'         => 'jet-appointments-booking/jet-appointments-booking.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/jet-appointments-booking.png', GATELAND_FILE ),
				'title'        => 'Jet Appointments Booking',
				'author'       => 'Crocoblock',
				'description'  => 'یک ابزار تجاری و ضروری برای ایجاد سیستم نوبت دهی و اوتوماسیون رزرو آنلاین برای استفاده در وبسایت های سرویس دهی و خدماتی می‌باشد.',
				'document_url' => null,
				'install_url'   => 'https://crocoblock.com/plugins/jetappointment/',
			],
			[
				'file'         => 'jet-booking/jet-booking.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/jetbooking.jpg', GATELAND_FILE ),
				'title'        => 'JetBooking',
				'author'       => 'Crocoblock',
				'description'  => 'یک افزونه تجاری و حرفه‌ای برای رزرو آنلاین اقامتگاه که با بررسی ظرفیت و زمان‌های قابل رزرو، امکان انتخاب تاریخ ورود و خروج و رزرو آسان اقامتگاه را فراهم می‌کند.',
				'document_url' => null,
				'install_url' => 'https://crocoblock.com/plugins/jetbooking/',
			],
			[
				'file'         => 'jet-engine/jet-engine.php',
				'icon_url'     => plugins_url( 'assets/images/plugins/jet-engine.png', GATELAND_FILE ),
				'title'        => 'JetEngine',
				'author'       => 'Crocoblock',
				'description'  => 'این افزونه تجاری راهکاری حرفه‌ای برای ایجاد و مدیریت پست‌تایپ‌ها، طبقه‌بندی‌ها و متاباکس‌های سفارشی است و ساخت فرم و پرداخت آنلاین را فراهم می‌کند.',
				'document_url' => null,
				'install_url' => 'https://crocoblock.com/plugins/jetengine/',
			],
		];

		foreach ( $plugins as &$plugin ) {

			$plugin['is_activated'] = is_plugin_active( $plugin['file'] );
			$plugin['is_installed'] = file_exists( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin['file'] );

			if ( ! isset( $plugin['author_url'] ) ) {
				$plugin['author_url'] = 'https://wordpress.org/plugins/' . strtok( $plugin['file'], '/' );
			}

			if ( ! isset( $plugin['install_url'] ) ) {
				$plugin['install_url'] = admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . strtok( $plugin['file'], '/' ) );
			} else {
				$plugin['author_url'] = $plugin['install_url'];
			}

			$plugin['activate_url'] = $this->activate_url( $plugin['file'] );
		}

		usort( $plugins, function ( $a, $b ) {

			$_a = $a['coming_soon'] ?? false;
			$_b = $b['coming_soon'] ?? false;

			if ( $_a != $_b ) {
				return $_a <=> $_b;
			}

			if ( $a['is_activated'] === $b['is_activated'] ) {
				return $b['is_installed'] <=> $a['is_installed'];
			}

			return $b['is_activated'] <=> $a['is_activated'];
		} );

		self::response( true, null, $plugins );
	}

	private function activate_url( $plugin_file ): string {
		$activate_url = admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin_file ) . '&plugin_status=all' );

		return wp_nonce_url( $activate_url, 'activate-plugin_' . $plugin_file );
	}
}