<?php

namespace Nabik\Gateland\Plugins\LearnDash;

use Exception;
use InvalidArgumentException;
use LearnDash\Core\Models\Invoice;
use LearnDash\Core\Models\Product;
use LearnDash\Core\Models\Transaction as LearnDashTransaction;
use Learndash_DTO_Validation_Exception;
use Learndash_Payment_Button;
use Learndash_Payment_Gateway;
use Learndash_Pricing_DTO;
use LearnDash_Settings_Section;
use Learndash_Transaction_Gateway_Transaction_DTO;
use Learndash_Transaction_Meta_DTO;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Models\Gateway as GatelandGateway;
use Nabik\Gateland\Models\Transaction as GatelandTransaction;
use Nabik\Gateland\Pay;
use Throwable;
use WP_Post;

class Gateway extends Learndash_Payment_Gateway {

	protected static ?int $gateway_id = null;
	protected static ?GatelandGateway $gateway = null;
	protected string $settings_section_key = 'settings_gateland';
	protected string $nonce;

	public function __construct( $gateway_id = 0 ) {
		parent::__construct();

		self::$gateway_id = $gateway_id;

		try {
			self::$gateway = GatelandGateway::query()->findOrFail( $gateway_id );
		} catch ( Exception $e ) {
			$this->log_error( $e->getMessage() );
		}

		$this->nonce = wp_create_nonce( 'gateland_ld_message_nonce' );
	}

	protected function configure(): void {
		$this->settings = LearnDash_Settings_Section::get_section_settings_all( Settings::class );
	}

	public function get_checkout_meta_html(): string {
		return sprintf( '<img alt="پرداخت امن آنلاین" class="ld-svglogo" src="%s">', ( ! self::$gateway ? '' : self::$gateway->build()->icon() ) );
	}

	public static function get_name(): string {
		return self::$gateway_id === 0 ? 'gateland' : 'gateland_' . self::$gateway_id;
	}

	public static function get_label(): string {
		return esc_html( ! self::$gateway ? 'پرداخت آنلاین هوشمند' : self::$gateway->build()->name() );
	}

	public function get_checkout_label(): string {
		return esc_html( ! self::$gateway ? 'پرداخت آنلاین هوشمند' : self::$gateway->build()->name() );
	}

	public function is_ready(): bool {
		return 'yes' === ( $this->settings['enabled'] ?? 'no' );
	}

	public function supports_transactions_management(): bool {
		return true;
	}

	protected function is_test_mode(): bool {
		return false;
	}

	public function add_extra_hooks(): void {
		add_action( 'wp_body_open', [ $this, 'display_message' ], 10, 1 );
	}

	public function enqueue_scripts(): void {
	}

	public function get_type( $post ): string {
		return learndash_is_group_post( $post ) ? 'پکیج' : 'دوره';
	}

	public function setup_payment(): void {

		if ( ! check_ajax_referer( $this->get_nonce_name(), 'nonce' ) ) {
			wp_send_json_error( [ 'message' => 'اعتبار سنجی توکن امنیتی با موفقیت انجام نشد.' ] );

			return;
		}

		$product = Product::find( intval( $_POST['post_id'] ?? 0 ) );

		if ( ! $product ) {
			wp_send_json_error( [ 'message' => 'دوره یا پکیج مد نظر شما یافت نشد.' ] );

			return;
		}

		if ( $product->is_price_type_subscribe() ) {
			wp_send_json_error( [ 'message' => 'دوره یا پکیج مد نظر شما از روش پرداخت قسطی، قابل خریداری نیست.' ] );

			return;
		}

		if ( empty( $this->user->ID ) ) {
			wp_send_json_error( [ 'message' => 'کاربر یافت نشد.' ] );

			return;
		}

		if ( ! $product->can_be_purchased( $this->user ) ) {
			wp_send_json_error( [ 'message' => sprintf( '%s قابل خرید نمی‌باشد.', $this->get_type( $product->get_post() ) ) ] );

			return;
		}

		$price = $product->get_final_price( $this->user->ID );
		$price = preg_replace( '/\D/', '', $price );

		if ( $price <= 0 ) {
			wp_send_json_error( [ 'message' => sprintf( 'قیمت %s، نامعتبر است.', $this->get_type( $product->get_post() ) ) ] );

			return;
		}

		if ( $this->currency_code == 'IRR' ) {
			$price /= 10;
		} elseif ( $this->currency_code == 'IRHR' ) {
			$price *= 100;
		} elseif ( $this->currency_code == 'IRHT' ) {
			$price *= 1000;
		}

		$user_hash = $this->generate_user_purchase_hash( $product->get_id(), $this->user->ID, $price );

		try {

			$transaction_id = $this->record_transaction(
				$this->map_transaction_meta( [
					'gateway' => self::get_label(),
					'status'  => 'pending',
					'price'   => $price,
				], $product )->to_array(),
				$product->get_post(),
				$this->user
			);

			$this->log_info( 'تراکنش برای محصول با آیدی ' . $product->get_id() . ' ثبت شد.' );

		} catch ( Learndash_DTO_Validation_Exception $e ) {

			$this->log_error( 'خطا در ثبت تراکنش: ' . $e->getMessage() );
			wp_send_json_error( [ 'message' => 'خطا در ثبت تراکنش: ' . $e->getMessage() ] );

			return;
		}

		$callback = add_query_arg( [
			'ld_gateway' => self::get_name(),
			'user_hash'  => $user_hash,
			'secret'     => md5( $product->get_post()->ID . NONCE_SALT ),
			'tr_id'      => $transaction_id
		], get_permalink( $product->get_post()->ID ) );

		$args = [
			'amount'      => $price,
			'callback'    => $callback,
			'user_id'     => $this->user->ID,
			'client'      => GatelandTransaction::CLIENT_LD,
			'description' => sprintf( 'خرید %s %s از طریق گیت‌لند', $this->get_type( $product->get_post() ), get_the_title( $product->get_post()->ID ) ),
			'currency'    => 'IRT',
			'order_id'    => $transaction_id,
			'gateway'     => self::$gateway_id === 0 ? null : self::$gateway_id
		];

		try {
			$response = Pay::request( $args );
		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => 'خطا در ارتباط با درگاه پرداخت.' ] );

			return;
		}

		if ( empty( $response['success'] ) ) {
			wp_send_json_error( [ 'message' => $response['message'] ?? 'درخواست پرداخت با خطا مواجه شد.' ] );

			return;
		}

		$transaction = LearnDashTransaction::find( $transaction_id );

		if ( ! $transaction ) {
			wp_send_json_error( [ 'message' => 'در ثبت اطلاعات سفارش، خطایی رخ داده است.' ] );

			return;
		}

		$transaction->set_meta( 'authority', $response['data']['authority'] );
		$transaction->set_meta( 'status', 'pending' );

		wp_send_json_success( [ 'redirect' => $response['data']['payment_link'] ] );
	}

	protected function map_transaction_meta( $data, Product $product ): Learndash_Transaction_Meta_DTO {
		$pricing_array = $product->get_pricing()->to_array();

		try {

			$pricing_info = ! empty( $this->user_hash[ LearnDashTransaction::$meta_key_pricing_info ] ) ?
				Learndash_Pricing_DTO::create( $this->user_hash[ LearnDashTransaction::$meta_key_pricing_info ] ) :
				Learndash_Pricing_DTO::create( $pricing_array );

			$gateway_info = Learndash_Transaction_Gateway_Transaction_DTO::create(
				[
					'id'          => $data['data']['authority'],
					'customer_id' => $this->user->ID ?? '',
					'event'       => $data,
				]
			);

		} catch ( Learndash_DTO_Validation_Exception $e ) {

			$this->log_error( 'در ایجاد ساختار داده قیمت محصول، خطایی رخ داد: ' . $e->getMessage() );

			return new Learndash_Transaction_Meta_DTO();
		}

		$meta_data = [
			LearnDashTransaction::$meta_key_gateway_name        => self::get_label(),
			LearnDashTransaction::$meta_key_gateway_transaction => $gateway_info,
			LearnDashTransaction::$meta_key_is_test_mode        => self::is_test_mode(),
			LearnDashTransaction::$meta_key_pricing_info        => $pricing_info,
			LearnDashTransaction::$meta_key_price_type          => $product->is_price_type_subscribe() ? LEARNDASH_PRICE_TYPE_SUBSCRIBE : LEARNDASH_PRICE_TYPE_PAYNOW,
			LearnDashTransaction::$meta_key_has_trial           => ! empty( $product->get_pricing()->trial_price ),
			LearnDashTransaction::$meta_key_has_free_trial      => ! empty( $product->get_pricing()->trial_price ) && $product->get_pricing()->trial_price > 0,
		];

		try {
			return Learndash_Transaction_Meta_DTO::create( $meta_data );
		} catch ( Learndash_DTO_Validation_Exception $e ) {
			$this->log_error( 'در ایجاد تراکنش خطایی رخ داد: ' . $e->getMessage() );

			return new Learndash_Transaction_Meta_DTO();
		}
	}

	public function process_webhook(): void {

		if ( ! isset( $_GET['ld_gateway'], $_GET['user_hash'], $_GET['secret'], $_GET['tr_id'] ) ) {
			return;
		}

		if ( $_GET['ld_gateway'] !== self::get_name() ) {
			return;
		}

		$transaction = LearnDashTransaction::find( intval( $_GET['tr_id'] ) );

		if ( ! $transaction ) {

			$this->log_error( 'تراکنش یافت نشد: ' . $_GET['tr_id'] );
			wp_safe_redirect( add_query_arg( [
				'message' => 'تراکنش یافت نشد: ' . $_GET['tr_id'] ?? 0,
				'type'    => 'error',
				'none'    => $this->nonce
			], home_url( '/' ) ) );
			exit;

		}

		$user    = wp_get_current_user();
		$product = $transaction->get_product();

		if ( $product->user_has_access( $user ) ) {

			wp_safe_redirect( add_query_arg( [
				'message' => 'قبلا در این دوره/پکیج آموزشی شرکت کرده اید.',
				'type'    => 'info',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;

		}

		$authority = $transaction->getAttribute( 'authority' );
		$post_id   = $transaction->getAttribute( 'post_id' );

		$user_hash = $this->get_user_purchase_hash( sanitize_text_field( $_GET['user_hash'] ) );

		if ( ! $user_hash ) {
			$this->log_error( 'رمزینه کاربر با مشکل مواجه شده است.' );
			wp_safe_redirect( add_query_arg( [
				'message' => 'رمزینه کاربر با مشکل مواجه شده است.',
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;
		}

		if ( empty( $user->ID ) ) {
			$this->log_error( 'کاربر گرامی، حساب کاربری شما دچار خطا شده یا دوره مد نظر وجود ندارد.' );
			wp_safe_redirect( add_query_arg( [
				'message' => 'کاربر گرامی، حساب کاربری شما دچار خطا شده یا دوره مد نظر وجود ندارد.',
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;
		}

		if ( intval( $user_hash['user_id'] ) !== intval( $user->ID ) || intval( $user_hash['product_id'] ) !== intval( $product->get_id() ) ) {
			$this->log_error( 'اجزاء سفارش با یکدیگر تطابق ندارند.' );

			wp_safe_redirect( add_query_arg( [
				'message' => 'اجزاء سفارش با یکدیگر تطابق ندارند.',
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;
		}

		if ( sanitize_text_field( $_GET['secret'] ) !== md5( $post_id . NONCE_SALT ) ) {
			$this->log_error( 'تطابق رمزینه های شناسه پست با مشکل مواجه شد.' );
			wp_safe_redirect( add_query_arg( [
				'message' => 'تطابق رمزینه های شناسه پست با مشکل مواجه شد.',
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;
		}

		try {
			$response = Pay::verify( $authority, GatelandTransaction::CLIENT_LD );
		} catch ( Throwable $e ) {
			$this->log_error( 'خطایی در بررسی پرداخت رخ داد.' );

			wp_safe_redirect( add_query_arg( [
				'message' => 'خطایی در بررسی پرداخت رخ داد.',
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;
		}

		if ( abs( $user_hash['paid_price'] - floatval( $response['data']['amount'] ) ) > 0.01 ) {
			$this->log_error( 'مبلغ پرداختی با اطلاعات خرید همخوانی ندارد.' );

			wp_safe_redirect( add_query_arg( [
				'message' => 'مبلغ پرداختی با اطلاعات خرید همخوانی ندارد.',
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;
		}

		$this->delete_user_purchase_hash( sanitize_text_field( $_GET['user_hash'] ) );

		if ( $response['success'] === false || ( $response['data']['status'] ) !== StatusesEnum::STATUS_PAID ) {
			$transaction->set_meta( 'status', 'failed' );
			$this->log_error( 'پرداخت سفارش، ناموفق بود.' );

			wp_safe_redirect( add_query_arg( [
				'message' => 'پرداخت شما ناموفق بود.',
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;
		}

		$transaction->set_meta( 'status', 'paid' );
		$enrolled = $product->enroll( $user );

		if ( ! $enrolled ) {
			$this->log_error( 'در افزودن شما بعنوان دانشجوی دوره، خطایی رخ داده است.' );
			wp_safe_redirect( add_query_arg( [
				'message' => 'در افزودن شما بعنوان دانشجوی دوره، خطایی رخ داده است.',
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;
		}

		try {

			$invoice = Invoice::create_from_transaction( $transaction );
			$invoice->send_email();
			$this->log_info( 'پرداخت با موفقیت انجام و سفارش ثبت شد.' );

		} catch ( Exception $e ) {

			$this->log_error( 'در ثبت سفارش شما خطایی رخ داده است: ' . $e->getMessage() );
			wp_safe_redirect( add_query_arg( [
				'message' => 'در ثبت سفارش شما خطایی رخ داده است: ' . $e->getMessage(),
				'type'    => 'error',
				'nonce'   => $this->nonce
			], self::get_url_fail( [ $product ] ) ) );
			exit;

		}

		wp_safe_redirect( add_query_arg( [
			'payment_status' => 'success',
			'message'        => 'پرداخت با موفقیت انجام شد.',
			'type'           => 'success',
			'nonce'          => $this->nonce
		], $product->get_permalink() ) );
	}

	private function generate_user_purchase_hash( int $product_id, int $user_id, float $final_price ): string {

		$hash_nonce = wp_create_nonce( $user_id . '-' . $product_id . '-' . time() );

		set_transient(
			'ld_gateland_user_hash_' . $hash_nonce,
			[
				'user_id'       => $user_id,
				'product_id'    => $product_id,
				'paid_price'    => $final_price,
				'currency_code' => $this->currency_code ?? 'IRT',
				'time'          => time(),
			],
			DAY_IN_SECONDS * 2
		);

		return $hash_nonce;
	}

	private function get_user_purchase_hash( string $hash_nonce ): ?array {
		return get_transient( 'ld_gateland_user_hash_' . $hash_nonce ) ?: null;
	}

	private function delete_user_purchase_hash( string $hash_nonce ): void {
		delete_transient( 'ld_gateland_user_hash_' . $hash_nonce );
	}

	protected function map_payment_button_markup( array $params, WP_Post $post ): string {

		try {
			$product = Product::create_from_post( $post );
		} catch ( InvalidArgumentException $e ) {
			$this->log_error( $e->getMessage() );

			return '<b>این دوره/پکیج قابل فروش نمی‌باشد.</b>';
		}

		if ( $product->is_price_type_subscribe() ) {
			return '<b>پرداخت دوره‌ای پشتیبانی نمی‌شود.</b>';
		}

		if ( ! $product->can_be_purchased( $this->user->ID ) ) {
			$this->log_error( 'غیرقابل فروش برای کاربر: ' . $this->user->ID );

			return '<b>این دوره/پکیج قابل فروش نمی‌باشد.</b>';
		}

		$button_label = esc_html( $this->map_payment_button_label( self::get_label(), $post ) );
		$form_class   = esc_attr( $this->get_form_class_name() );
		$button_class = esc_attr( Learndash_Payment_Button::map_button_class_name() );

		$form_parts = [
			sprintf( '<form class="%s" method="post">', esc_attr( $form_class ) ),
			sprintf( '<input type="hidden" name="action" value="%s">', esc_attr( $this->get_ajax_action_name_setup() ) ),
			sprintf( '<input type="hidden" name="nonce" value="%s">', esc_attr( wp_create_nonce( $this->get_nonce_name() ) ) ),
			sprintf( '<input type="hidden" name="post_id" value="%s">', esc_attr( $post->ID ) ),
			sprintf(
				'<button type="submit" id="%s" class="%s" aria-label="%s">',
				esc_attr( Learndash_Payment_Button::map_button_id() ),
				esc_attr( $button_class ),
				esc_attr( $button_label )
			),
			esc_html( $button_label ),
			'</button>',
			'</form>',
		];

		$button = implode( "\n", $form_parts );

		ob_start(); ?>
		<script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.querySelector('form.<?php echo $form_class; ?>');
                if (!form) return;

                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const button = form.querySelector('button');
                    const originalText = button.textContent;
                    button.disabled = true;
                    button.textContent = 'در حال انتقال به درگاه...';

                    const formData = new FormData(form);
                    try {
                        const response = await fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success && result.data?.redirect) {
                            window.location.href = result.data.redirect;
                        } else {
                            alert(result.data?.message || 'خطا در شروع پرداخت.');
                            button.disabled = false;
                            button.textContent = originalText;
                        }
                    } catch (error) {
                        console.error('Gateland error: ', error);
                        alert('خطا در ارتباط با سرور.');
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                });
            });
		</script>
		<style>
            button.<?php echo $button_class; ?> {
                background-color: #009688;
                color: #fff;
                padding: 12px 24px;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            button.<?php echo $button_class; ?>:hover {
                background-color: #007d73;
            }

            button.<?php echo $button_class; ?>:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
		</style>
		<?php
		$button .= ob_get_clean();

		return $button;
	}

	public function display_message() {

		if ( ! isset( $_GET['message'], $_GET['type'], $_GET['nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_GET['nonce'] , 'gateland_ld_message_nonce') ) {
			return;
		}

		$type    = sanitize_text_field( wp_unslash( $_GET['type'] ) );
		$message = wp_strip_all_tags( wp_unslash( $_GET['message'] ) );

		echo $this->get_preformatted_message_html( $message, $type );
	}

	private function get_preformatted_message_html( string $message, string $type = 'info' ): string {
		$classes = [
			'info'    => 'ld-message ld-message--info',
			'success' => 'ld-message ld-message--success',
			'error'   => 'ld-message ld-message--error',
			'warning' => 'ld-message ld-message--warning',
		];

		$class = $classes[ $type ] ?? $classes['info'];

		return sprintf(
			'<div class="%1$s" role="alert" style="%2$s">%3$s</div>',
			esc_attr( $class ),
			esc_attr( $this->get_inline_message_style( $type ) ),
			esc_html( $message )
		);
	}

	private function get_inline_message_style( string $type ): string {
		$base = 'margin:20px auto;padding:15px 20px;border-radius:8px;max-width:800px;font-size:16px;font-weight:500;';

		$colors = [
			'success' => 'background:#e6ffed;color:#0a6720;border:1px solid #b5e4c0;',
			'error'   => 'background:#ffe6e6;color:#a40000;border:1px solid #e5b5b5;',
			'warning' => 'background:#fff8e1;color:#7a5600;border:1px solid #f2da8b;',
			'info'    => 'background:#e6f0ff;color:#003366;border:1px solid #b5c9e5;',
		];

		return $base . ( $colors[ $type ] ?? $colors['info'] );
	}

}
