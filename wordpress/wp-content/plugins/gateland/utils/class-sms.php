<?php

namespace Nabik\Utils\V1;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Nabik\Utils\V1\SMS' ) ) {

	/**
	 * Class Nabik_Net_SMS
	 *
	 * @author  Nabik
	 */
	class SMS {

		const VERSION = '1.0.0';

		/**
		 * @var string|null
		 */
		private static ?string $error = null;

		/**
		 * @var array
		 */
		private static $options = [];

		public function __construct() {
			add_action( 'admin_init', [ $this, 'admin_init' ] );
			add_action( 'admin_menu', [ $this, 'admin_menu' ], 100 );

			self::$options = get_option( 'nabik_sms', [] );
		}

		public static function has_error( $error ): bool {

			if ( ! is_array( $error ) ) {
				$error = [ $error ];
			}

			return in_array( self::$error, $error );
		}

		public function admin_init() {
			register_setting( 'nabik_sms', 'nabik_sms', [
				'type'              => 'array',
				'sanitize_callback' => function ( $input ) {
					return array_map( 'sanitize_text_field', $input );
				},
			] );

			add_settings_field( 'nabik_sms', 'درگاه', [ $this, 'null_callback' ], 'nabik_sms' );
		}

		public function admin_menu() {
			global $submenu;

			$capability = apply_filters( 'nabik_sms_menu_capability', 'manage_options' );

			add_submenu_page( 'nabik', 'پیامک', 'پیامک', $capability, 'nabik-sms', [
				$this,
				'page_callback',
			] );

			if ( isset( $submenu['nabik'][0][2] ) && $submenu['nabik'][0][2] == 'nabik' ) {
				unset( $submenu['nabik'][0] );
			}
		}

		public function page_callback() {

			$capability = apply_filters( 'nabik_sms_menu_capability', 'manage_options' );

			if ( ! current_user_can( $capability ) ) {
				return;
			}

			if ( isset( $_GET['settings-updated'] ) ) {
				add_settings_error( 'nabik_sms_messages', 'nabik_sms_messages', 'تنظیمات با موفقیت ذخیره شد.', 'updated' );
			}

			settings_errors( 'nabik_sms_messages' );

			?>
			<div class="wrap">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<form method="post" action="options.php">
					<?php settings_fields( 'nabik_sms' ); ?>

					<table class="form-table" role="presentation">
						<tbody>
						<tr>
							<th scope="row">
								<label>پیشنهاد ویژه</label>
							</th>
							<td>
								<p class="description">
									<b>ملی پیامک:</b> پنل پیامک حرفه‌ای و پرسرعت را با کد تخفیف
									۲۰ درصدی <b>nabik20</b> از <a
											href="https://l.nabik.net/melipayamak?utm_source=nabik-sms" target="_blank">ملی
										پیامک</a> تهیه کنید.
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="nabik_sms[gateway]">درگاه پیامک</label>
							</th>
							<td>
								<select class="regular" name="nabik_sms[gateway]" id="nabik_sms[gateway]"
								        style="width: 25em">
									<option value="melipayamak" <?php selected( self::get_option( 'gateway' ), 'melipayamak' ); ?>>
										ملی پیامک
									</option>
									<option value="pwoosms" <?php selected( self::get_option( 'gateway' ), 'pwoosms' ); ?>>
										پیامک حرفه‌ای ووکامرس
									</option>
								</select>
								<p class="description">
								<ul>
									<li>۱. ملی پیامک: ارسال پیامک به صورت متن عادی یا ارسال سریع به صورت پترن با خط
										خدماتی عمومی
									</li>
									<li>۲. پیامک حرفه‌ای ووکامرس: ارسال پیامک با استفاده از درگاه تنظیم شده در
										<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=persian-woocommerce-sms' ) ); ?>"
										   target="_blank">Persian Woocommerce SMS</a>
									</li>
								</ul>
								</p>
							</td>
						</tr>
						<tr class="gateway-options melipayamak">
							<th scope="row">
								<label for="nabik_sms[username]">نام کاربری ملی پیامک</label>
							</th>
							<td>
								<input type="text" class="regular-text " id="nabik_sms[username]"
								       name="nabik_sms[username]"
								       value="<?php echo esc_attr( self::get_option( 'username' ) ); ?>">
								<p class="description">نام کاربری پنل پیامک <a
											href="https://l.nabik.net/melipayamak?utm_source=nabik-sms"
											target="_blank">ملی پیامک</a>
								</p>
							</td>
						</tr>
						<tr class="gateway-options melipayamak">
							<th scope="row">
								<label for="nabik_sms[password]">کلمه عبور ملی پیامک</label>
							</th>
							<td>
								<input type="password" class="regular-text" id="nabik_sms[password]"
								       name="nabik_sms[password]"
								       value="<?php echo esc_attr( self::get_option( 'password' ) ); ?>">
								<p class="description">
									<span style="color: seagreen">
										<?php

										echo wp_kses( self::credit(), [
											'span' => [
												'style' => [],
											],
										] );

										?>
									</span>
								</p>
							</td>
						</tr>
						<tr class="gateway-options melipayamak">
							<th scope="row">
								<label for="nabik_sms[sender]">شماره ارسال کننده پیامک</label>
							</th>
							<td>
								<input type="text" class="regular-text" id="nabik_sms[sender]"
								       name="nabik_sms[sender]"
								       value="<?php echo esc_attr( self::get_option( 'sender' ) ); ?>">
								<p class="description">این شماره صرفا برای ارسال پیامک‌های عادی (غیر پترن) کاربرد
									دارد.</p>
							</td>
						</tr>
						<tr class="gateway-options pwoosms">
							<th scope="row">
								<label>پیامک حرفه‌ای ووکامرس</label>
							</th>
							<td>
								<p class="description">
									تنظیمات درگاه پیامک را از <a
											href="<?php echo esc_url( admin_url( 'admin.php?page=persian-woocommerce-sms-pro' ) ); ?>"
											target="_blank">اینجا</a> انجام دهید.
								</p>
							</td>
						</tr>
						</tbody>
					</table>

					<div style="padding-left: 10px">
						<?php submit_button( 'ذخیره تغییرات' ); ?>
					</div>

				</form>
			</div>

			<script>
                jQuery(document).ready(function ($) {

                    let gateway_selector = $("#nabik_sms\\[gateway\\]");

                    $('.' + gateway_selector.val()).show();

                    gateway_selector.change(function () {
                        $('.gateway-options').hide();
                        $('.' + $(this).val()).show();
                    });

                });
			</script>

			<style>
                .gateway-options {
                    display: none;
                }

                input[id^='nabik_sms'] {
                    direction: ltr;
                    text-align: left;
                }
			</style>
			<?php
		}

		public function null_callback() {
			// Do nothing!
		}

		public static function credit( $flush_cache = false ): string {

			$param['username'] = self::get_option( 'username' );
			$param['password'] = self::get_option( 'password' );

			$credit = get_transient( 'nabik_sms_credit' );

			if ( $flush_cache ) {
				$credit = false;
			}

			if ( $credit === false && $param['username'] && $param['password'] ) {

				$request = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/GetCredit', [
					'body'    => $param,
					'headers' => [
						'content-type'  => 'application/x-www-form-urlencoded',
						'cache-control' => 'no-cache',
					],
				] );

				$credit = json_decode( wp_remote_retrieve_body( $request ) );
				$color  = 'darkred';

				if ( is_wp_error( $request ) || is_null( $credit ) ) {
					$credit = 'اتصال به سامانه ملی پیامک انجام نشده است.';
				} else if ( $credit->RetStatus == 1 ) {
					$credit = 'اعتبار ملی پیامک: ' . intval( $credit->Value );
					$color  = 'seagreen';
				} else {
					$credit = 'خطا در اتصال به ملی پیامک: ' . $credit->StrRetStatus;
				}

				$credit = "<span style='color: {$color}'>{$credit}</span>";

				if ( $color == 'seagreen' ) {
					set_transient( 'nabik_sms_credit', $credit, MINUTE_IN_SECONDS * 10 );
				}
			}

			return $credit;
		}

		/**
		 * @throws Exception
		 */
		public static function add_pattern( string $title, string $body ): int {

			$username = self::get_option( 'username' );
			$password = self::get_option( 'password' );

			if ( empty( $username ) || empty( $password ) ) {
				throw new Exception( 'نام کاربری و کلمه عبور ملی پیامک نمی‌تواند خالی باشد.' );
			}

			try {

				$client = new SoapClient( 'https://api.payamak-panel.com/post/SharedService.asmx?wsdl', [
					'encoding'   => 'UTF-8',
					'cache_wsdl' => WSDL_CACHE_NONE,
				] );

				$data = [
					'username'    => $username,
					'password'    => $password,
					'title'       => $title,
					'body'        => $body,
					'blackListId' => 1,
				];

				$pattern_code = $client->SharedServiceBodyAdd( $data )->SharedServiceBodyAddResult;

				if ( $pattern_code > 0 ) {
					return $pattern_code;
				}

				throw new Exception( $pattern_code );

			} catch ( Exception $e ) {
				throw new Exception( sprintf( 'خطا «%s» در زمان ثبت پترن در ملی پیامک رخ داده است.', esc_html( $e->getMessage() ) ) );
			}

		}

		/**
		 * @throws Exception
		 */
		public static function get_patterns(): array {

			$username = self::get_option( 'username' );
			$password = self::get_option( 'password' );

			if ( empty( $username ) || empty( $password ) ) {
				throw new Exception( 'نام کاربری و کلمه عبور ملی پیامک نمی‌تواند خالی باشد.' );
			}

			try {

				$client = new SoapClient( 'https://api.payamak-panel.com/post/SharedService.asmx?wsdl', [
					'encoding'   => 'UTF-8',
					'cache_wsdl' => WSDL_CACHE_NONE,
				] );

				$data = [
					'username' => $username,
					'password' => $password,
				];

				return (array) $client->GetSharedServiceBody( $data )->GetSharedServiceBodyResult;

			} catch ( Exception $e ) {
				throw new Exception( sprintf( 'خطا «%s» در زمان ثبت دریافت لیست پترن‌ها از ملی پیامک رخ داده است.', esc_html( $e->getMessage() ) ) );
			}

		}

		public static function get_option( string $key, $default = null ) {
			$value = self::$options[ $key ] ?? $default;

			if ( $key == 'gateway' && $value == 'melipayamak_pattern' ) {
				return 'melipayamak';
			}

			return $value;
		}

		public static function send( string $phone, string $message ): bool {

			$message = wp_strip_all_tags( $message );
			$message = str_replace( '&nbsp;', ' ', $message );

			$gateway = self::get_option( 'gateway' );

			if ( $gateway == 'pwoosms' ) {
				return self::pwoosms( $phone, $message );
			} elseif ( $gateway == 'melipayamak' ) {
				return self::melipayamak( $phone, $message );
			}

			return false;
		}

		private static function melipayamak( string $phone, string $message ): bool {

			$username = self::get_option( 'username' );
			$password = self::get_option( 'password' );

			if ( empty( $username ) || empty( $password ) ) {
				return false;
			}

			$message = trim( $message );

			if ( self::is_pattern( $message ) ) {

				$pattern = self::parse_pattern( $message );

				return self::melipayamak_pattern( $phone, $pattern['code'], $pattern['vars'] );
			}

			// Backward compatibility with format: PatternCode;Val1;Val2
			$variables    = explode( ';', $message );
			$pattern_code = $variables[0];
			array_shift( $variables );

			if ( $pattern_code && $variables ) {
				return self::melipayamak_pattern( $phone, $pattern_code, $variables );
			}

			return self::melipayamak_normal( $phone, $message );
		}

		/**
		 * @param string $message
		 *
		 * @return bool
		 *
		 * @version 1.1.0
		 */
		public static function is_pattern( string $message ): bool {
			return str_starts_with( $message, 'pattern:' );
		}

		/**
		 * $message format:
		 *
		 * pattern:<PatternCode>
		 * <Var1>:<Val1>
		 * <Var2>:<Val2>
		 * ...
		 * ...
		 *
		 * @param string $message
		 *
		 * @return array
		 *
		 * @version 1.1.0
		 */
		public static function parse_pattern( string $message ): array {

			$result = [
				'code' => '',
				'vars' => [],
			];

			$message = str_replace( [ "\r\n", "\n", "\\r\\n", "\\n" ], '~', $message );
			$parts   = explode( '~', $message );

			foreach ( $parts as $part ) {

				[ $key, $value ] = explode( ':', $part, 2 );

				$key   = trim( $key, "}{% \n\r\t\v\x00" );
				$value = trim( $value );

				if ( $key === 'pattern' ) {
					$result['code'] = $value;
				} elseif ( strlen( $key ) ) {
					$result['vars'][ $key ] = $value;
				}

			}

			return $result;
		}

		private static function melipayamak_normal( string $phone, string $message ): bool {

			$sender = self::get_option( 'sender' );

			if ( empty( $sender ) ) {
				return false;
			}

			$data = [
				'username' => self::get_option( 'username' ),
				'password' => self::get_option( 'password' ),
				'to'       => $phone,
				'from'     => $sender,
				'text'     => $message,
			];

			$response = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/SendSMS', [
				'body'    => http_build_query( $data ),
				'headers' => [
					'content-type'  => 'application/x-www-form-urlencoded',
					'cache-control' => 'no-cache',
				],
			] );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			$response = intval( $response['Value'] ?? - 1 );

			self::$error = $response;

			return $response > 35;
		}

		private static function melipayamak_pattern( string $phone, string $pattern_id, array $variables ): bool {

			$data = [
				'username' => self::get_option( 'username' ),
				'password' => self::get_option( 'password' ),
				'text'     => implode( ';', $variables ),
				'to'       => $phone,
				'bodyId'   => $pattern_id,
			];

			$response = wp_remote_post( 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', [
				'body'    => $data,
				'headers' => [
					'content-type'  => 'application/x-www-form-urlencoded',
					'cache-control' => 'no-cache',
				],
			] );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
			$response = intval( $response['Value'] ?? - 1 );

			self::$error = $response;

			return $response > 35;
		}

		private static function pwoosms( string $phone, string $message ): bool {

			if ( ! function_exists( 'PWooSMS' ) ) {
				return false;
			}

			$data = [
				'message' => $message,
				'mobile'  => $phone,
			];

			PWOOSMS()->SendSMS( $data );

			return true;
		}
	}

	new SMS();

}