<?php
/**
 * پیگیری سفارش — Order lookup by order number + email/phone (no login).
 *
 * @package Digikala
 */

$dk_track = class_exists( 'ASC_Store_Pages' )
	? ASC_Store_Pages::handle_order_lookup()
	: array( 'order' => null, 'error' => '' );

get_header();
?>
<main class="dk-main">
	<div class="dk-container dk-page-narrow">
		<article class="dk-page-card">
			<h1 class="dk-page-title"><?php esc_html_e( 'پیگیری سفارش', 'lylyrose' ); ?></h1>
			<p class="dk-page-lead"><?php esc_html_e( 'شماره سفارش و ایمیل یا شماره موبایلی که هنگام خرید وارد کردید را بنویسید تا وضعیت سفارش را ببینید.', 'lylyrose' ); ?></p>

			<?php if ( $dk_track['order'] instanceof WC_Order ) : ?>
				<?php
				$dk_order       = $dk_track['order'];
				$dk_status      = wc_get_order_status_name( $dk_order->get_status() );
				$dk_status_flow = array(
					'pending'    => array( __( 'ثبت سفارش', 'lylyrose' ), true ),
					'processing' => array( __( 'در حال پردازش', 'lylyrose' ), true ),
					'on-hold'    => array( __( 'در انتظار پرداخت', 'lylyrose' ), true ),
					'completed'  => array( __( 'تحویل داده شد', 'lylyrose' ), true ),
					'cancelled'  => array( __( 'لغو شده', 'lylyrose' ), false ),
					'refunded'   => array( __( 'بازپرداخت شده', 'lylyrose' ), false ),
					'failed'     => array( __( 'ناموفق', 'lylyrose' ), false ),
				);
				$dk_steps = array( 'pending', 'processing', 'completed' );
				$dk_current = $dk_order->get_status();
				$dk_done    = isset( $dk_status_flow[ $dk_current ] ) ? $dk_status_flow[ $dk_current ][1] : true;
				?>
				<div class="dk-track-result">
					<div class="dk-track-head">
						<span class="dk-track-no"><?php echo esc_html( sprintf( __( 'سفارش %s', 'lylyrose' ), lylyrose_to_persian_digits( '#' . $dk_order->get_order_number() ) ) ); ?></span>
						<span class="dk-track-status st-<?php echo esc_attr( $dk_order->get_status() ); ?>"><?php echo esc_html( $dk_status ); ?></span>
					</div>

					<?php if ( in_array( $dk_current, $dk_steps, true ) ) : ?>
						<ol class="dk-track-steps">
							<?php
							$dk_idx = array_search( $dk_current, $dk_steps, true );
							foreach ( $dk_steps as $dk_i => $dk_step ) :
								$dk_labels = array( __( 'ثبت سفارش', 'lylyrose' ), __( 'در حال پردازش', 'lylyrose' ), __( 'تحویل شده', 'lylyrose' ) );
								$dk_state  = 'done';
								if ( $dk_i > $dk_idx ) {
									$dk_state = 'todo';
								}
								if ( 'on-hold' === $dk_current && 1 === $dk_i ) {
									$dk_state = 'current';
								}
								?>
								<li class="step-<?php echo esc_attr( $dk_state ); ?>">
									<span class="dot"></span><span><?php echo esc_html( $dk_labels[ $dk_i ] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>

					<table class="dk-track-meta">
						<tr><th><?php esc_html_e( 'تاریخ ثبت', 'lylyrose' ); ?></th><td><?php echo esc_html( lylyrose_to_persian_digits( $dk_order->get_date_created() ? wp_date( 'Y/m/d', $dk_order->get_date_created()->getTimestamp() ) : '—' ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'مبلغ کل', 'lylyrose' ); ?></th><td><?php echo wp_kses_post( $dk_order->get_formatted_order_total() ); ?></td></tr>
						<tr><th><?php esc_html_e( 'روش پرداخت', 'lylyrose' ); ?></th><td><?php echo esc_html( $dk_order->get_payment_method_title() ? $dk_order->get_payment_method_title() : '—' ); ?></td></tr>
					</table>

					<?php if ( $dk_order->get_customer_note() ) : ?>
						<p class="dk-track-note"><?php echo esc_html( sprintf( __( 'یادداشت شما: %s', 'lylyrose' ), $dk_order->get_customer_note() ) ); ?></p>
					<?php endif; ?>

					<p class="dk-track-hint"><?php esc_html_e( 'برای جزئیات کامل سبد سفارش، لینک «مشاهده سفارش» در ایمیل تأیید سفارش را باز کنید یا با پشتیبانی تماس بگیرید.', 'lylyrose' ); ?></p>
				</div>
			<?php else : ?>
				<?php if ( '' !== $dk_track['error'] ) : ?>
					<p class="dk-notice dk-notice-err"><?php echo esc_html( $dk_track['error'] ); ?></p>
				<?php endif; ?>

				<form method="post" class="dk-form dk-track-form">
					<?php wp_nonce_field( 'asc_track_order', 'asc_track_nonce' ); ?>
					<div class="dk-form-row">
						<label class="dk-field">
							<span><?php esc_html_e( 'شماره سفارش', 'lylyrose' ); ?></span>
							<input type="text" name="asc_order_id" inputmode="numeric" placeholder="<?php echo esc_attr__( 'مثلاً ۱۲۳۴', 'lylyrose' ); ?>" required>
						</label>
						<label class="dk-field">
							<span><?php esc_html_e( 'ایمیل یا شماره موبایل', 'lylyrose' ); ?></span>
							<input type="text" name="asc_contact" placeholder="<?php echo esc_attr__( 'مثلاً ۰۹۱۲۱۲۳۴۵۶۷', 'lylyrose' ); ?>" required>
						</label>
					</div>
					<button type="submit" name="asc_track_submit" value="1" class="dk-btn dk-btn-primary"><?php esc_html_e( 'پیگیری سفارش', 'lylyrose' ); ?></button>
				</form>
			<?php endif; ?>

			<div class="dk-track-help">
				<h2><?php esc_html_e( 'سؤال‌های پرتکرار درباره پیگیری', 'lylyrose' ); ?></h2>
				<ul class="dk-page-list">
					<li><?php esc_html_e( 'شماره سفارش در ایمیل تأیید خرید و در صفحه «سفارش‌های من» در حساب کاربری قابل مشاهده است.', 'lylyrose' ); ?></li>
					<li><?php esc_html_e( 'بعد از ارسال، کد رهگیری پست برایتان پیامک می‌شود و می‌توانید بسته را در', 'lylyrose' ); ?> <a href="https://tracking.post.ir" target="_blank" rel="noopener"><?php esc_html_e( 'سایت پست ایران', 'lylyrose' ); ?></a> <?php esc_html_e( 'دنبال کنید.', 'lylyrose' ); ?></li>
					<li><?php esc_html_e( 'اگر سفارش شما بیش از زمان اعلام‌شده به دستتان نرسیده، با پشتیبانی تماس بگیرید.', 'lylyrose' ); ?></li>
				</ul>
			</div>
		</article>
	</div>
</main>
<?php
get_footer();
