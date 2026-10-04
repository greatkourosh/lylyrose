<?php
/**
 * تماس با ما — Contact page with a mail-to-admin form.
 *
 * @package Digikala
 */

$dk_contact = class_exists( 'ASC_Store_Pages' )
	? ASC_Store_Pages::handle_contact_form()
	: array( 'sent' => false, 'error' => '', 'values' => array( 'name' => '', 'email' => '', 'subject' => '', 'message' => '' ) );

get_header();
?>
<main class="dk-main">
	<div class="dk-container dk-page-narrow">
		<article class="dk-page-card">
			<h1 class="dk-page-title"><?php esc_html_e( 'تماس با ما', 'lylyrose' ); ?></h1>
			<p class="dk-page-lead"><?php esc_html_e( 'هر سؤال، انتقاد یا پیشنهادی دارید برای ما بنویسید. پیام‌ها در ساعات کاری بررسی و پاسخ داده می‌شوند.', 'lylyrose' ); ?></p>

			<div class="dk-contact-grid">
				<div class="dk-contact-info">
					<div class="dk-contact-item">
						<span class="ico">☎️</span>
						<div>
							<strong><?php esc_html_e( 'تلفن پشتیبانی', 'lylyrose' ); ?></strong>
							<span><?php esc_html_e( '۰۲۱-۰۰۰۰۰۰۰۰ (شنبه تا پنجشنبه، ۹ تا ۱۸)', 'lylyrose' ); ?></span>
						</div>
					</div>
					<div class="dk-contact-item">
						<span class="ico">✉️</span>
						<div>
							<strong><?php esc_html_e( 'ایمیل', 'lylyrose' ); ?></strong>
							<span><?php echo esc_html( get_option( 'admin_email' ) ); ?></span>
						</div>
					</div>
					<div class="dk-contact-item">
						<span class="ico">💬</span>
						<div>
							<strong><?php esc_html_e( 'گفت‌وگوی آنلاین', 'lylyrose' ); ?></strong>
							<span><?php esc_html_e( 'از طریق آیکن چت در پایین صفحه در دسترس است.', 'lylyrose' ); ?></span>
						</div>
					</div>
					<div class="dk-contact-item">
						<span class="ico">📦</span>
						<div>
							<strong><?php esc_html_e( 'وضعیت سفارش', 'lylyrose' ); ?></strong>
							<span><a href="<?php echo esc_url( class_exists( 'ASC_Store_Pages' ) ? ASC_Store_Pages::url( 'track-order' ) : home_url( '/track-order/' ) ); ?>"><?php esc_html_e( 'صفحه پیگیری سفارش', 'lylyrose' ); ?></a> <?php esc_html_e( 'سریع‌ترین راه است.', 'lylyrose' ); ?></span>
						</div>
					</div>
				</div>

				<div class="dk-contact-form-wrap">
					<?php if ( $dk_contact['sent'] ) : ?>
						<p class="dk-notice dk-notice-ok"><?php esc_html_e( 'پیام شما ثبت شد. به‌زودی پاسخ می‌دهیم.', 'lylyrose' ); ?></p>
					<?php else : ?>
						<?php if ( '' !== $dk_contact['error'] ) : ?>
							<p class="dk-notice dk-notice-err"><?php echo esc_html( $dk_contact['error'] ); ?></p>
						<?php endif; ?>
						<form method="post" class="dk-form">
							<?php wp_nonce_field( 'asc_contact_form', 'asc_contact_nonce' ); ?>
							<label class="dk-field">
								<span><?php esc_html_e( 'نام و نام خانوادگی', 'lylyrose' ); ?></span>
								<input type="text" name="asc_name" value="<?php echo esc_attr( $dk_contact['values']['name'] ); ?>" required>
							</label>
							<label class="dk-field">
								<span><?php esc_html_e( 'ایمیل', 'lylyrose' ); ?></span>
								<input type="email" name="asc_email" value="<?php echo esc_attr( $dk_contact['values']['email'] ); ?>" required>
							</label>
							<label class="dk-field">
								<span><?php esc_html_e( 'موضوع', 'lylyrose' ); ?></span>
								<input type="text" name="asc_subject" value="<?php echo esc_attr( $dk_contact['values']['subject'] ); ?>">
							</label>
							<label class="dk-field">
								<span><?php esc_html_e( 'متن پیام', 'lylyrose' ); ?></span>
								<textarea name="asc_message" rows="6" required><?php echo esc_textarea( $dk_contact['values']['message'] ); ?></textarea>
							</label>
							<p class="dk-hp"><label><?php esc_html_e( 'وب‌سایت', 'lylyrose' ); ?><input type="text" name="asc_website" value="" tabindex="-1" autocomplete="off"></label></p>
							<button type="submit" name="asc_contact_submit" value="1" class="dk-btn dk-btn-primary"><?php esc_html_e( 'ارسال پیام', 'lylyrose' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</article>
	</div>
</main>
<?php
get_footer();
