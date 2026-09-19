<?php
/**
 * Footer — Digikala-style: services strip, link columns, newsletter, socials.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;
?>
</div><!-- #content -->

<footer class="dk-footer">
    <div class="dk-container">
        <div class="dk-footer-top">
            <span><?php esc_html_e( 'همراه باشید و از تخفیف‌ها باخبر شوید', 'lylyrose' ); ?></span>
            <div class="dk-footer-services">
                <div class="dk-footer-service"><span class="ico">🚚</span><span><?php esc_html_e( 'امکان تحویل اکسپرس', 'lylyrose' ); ?></span></div>
                <div class="dk-footer-service"><span class="ico">💳</span><span><?php esc_html_e( 'پرداخت در محل', 'lylyrose' ); ?></span></div>
                <div class="dk-footer-service"><span class="ico">↩️</span><span><?php esc_html_e( '۷ روز ضمانت بازگشت', 'lylyrose' ); ?></span></div>
                <div class="dk-footer-service"><span class="ico">🛡️</span><span><?php esc_html_e( 'ضمانت اصل بودن کالا', 'lylyrose' ); ?></span></div>
            </div>
        </div>

        <?php if ( is_active_sidebar( 'footer-widgets' ) ) : ?>
            <div class="dk-footer-grid">
                <?php dynamic_sidebar( 'footer-widgets' ); ?>
            </div>
        <?php else : ?>
        <div class="dk-footer-grid">
            <div class="dk-footer-col">
                <h4><?php esc_html_e( 'فروشگاه', 'lylyrose' ); ?></h4>
                <p><?php esc_html_e( 'فروشگاه اینترنتی لیلی رز با هزاران محصول، ضمانت اصالت کالا و ارسال سریع به سراسر ایران. خرید آسان و مطمئن را تجربه کنید.', 'lylyrose' ); ?></p>
                <form class="dk-newsletter" onsubmit="return false;">
                    <input type="email" placeholder="<?php echo esc_attr__( 'ایمیل خود را وارد کنید', 'lylyrose' ); ?>">
                    <button class="dk-btn dk-btn-secondary" type="submit"><?php esc_html_e( 'ثبت', 'lylyrose' ); ?></button>
                </form>
            </div>
            <div class="dk-footer-col">
                <h4><?php esc_html_e( 'با ما همراه باشید', 'lylyrose' ); ?></h4>
                <ul>
                    <li><a href="<?php echo esc_url( class_exists( 'ASC_Store_Pages' ) ? ASC_Store_Pages::url( 'about' ) : home_url( '/about/' ) ); ?>"><?php esc_html_e( 'درباره ما', 'lylyrose' ); ?></a></li>
                    <li><a href="<?php echo esc_url( class_exists( 'ASC_Store_Pages' ) ? ASC_Store_Pages::url( 'contact' ) : home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'تماس با ما', 'lylyrose' ); ?></a></li>
                    <li><a href="#"><?php esc_html_e( 'فرصت‌های شغلی', 'lylyrose' ); ?></a></li>
                    <li><a href="#"><?php esc_html_e( 'فروشنده شوید', 'lylyrose' ); ?></a></li>
                </ul>
            </div>
            <div class="dk-footer-col">
                <h4><?php esc_html_e( 'خدمات مشتریان', 'lylyrose' ); ?></h4>
                <ul>
                    <li><a href="<?php echo esc_url( class_exists( 'ASC_Store_Pages' ) ? ASC_Store_Pages::url( 'track-order' ) : home_url( '/track-order/' ) ); ?>"><?php esc_html_e( 'پیگیری سفارش', 'lylyrose' ); ?></a></li>
                    <li><a href="<?php echo esc_url( class_exists( 'ASC_Store_Pages' ) ? ASC_Store_Pages::url( 'faq' ) : home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'سوالات متداول', 'lylyrose' ); ?></a></li>
                    <li><a href="#"><?php esc_html_e( 'شرایط بازگشت کالا', 'lylyrose' ); ?></a></li>
                    <li><a href="#"><?php esc_html_e( 'راهنمای خرید', 'lylyrose' ); ?></a></li>
                </ul>
            </div>
            <div class="dk-footer-col">
                <h4><?php esc_html_e( 'ما را دنبال کنید', 'lylyrose' ); ?></h4>
                <div class="dk-socials">
                    <a href="#" aria-label="<?php echo esc_attr__( 'اینستاگرام', 'lylyrose' ); ?>">📷</a>
                    <a href="#" aria-label="<?php echo esc_attr__( 'تلگرام', 'lylyrose' ); ?>">✈️</a>
                    <a href="#" aria-label="<?php echo esc_attr__( 'توییتر', 'lylyrose' ); ?>">🐦</a>
                    <a href="#" aria-label="<?php echo esc_attr__( 'لینکدین', 'lylyrose' ); ?>">💼</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="dk-footer-bottom">
            <span><?php esc_html_e( '© تمامی حقوق برای لیلی رز محفوظ است.', 'lylyrose' ); ?></span>
            <span><?php esc_html_e( 'استفاده از مطالب فروشگاه فقط برای مقاصد غیرتجاری و با ذکر منبع مجاز است.', 'lylyrose' ); ?></span>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
