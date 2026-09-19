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
            <span>همراه باشید و از تخفیف‌ها باخبر شوید</span>
            <div class="dk-footer-services">
                <div class="dk-footer-service"><span class="ico">🚚</span><span>امکان تحویل اکسپرس</span></div>
                <div class="dk-footer-service"><span class="ico">💳</span><span>پرداخت در محل</span></div>
                <div class="dk-footer-service"><span class="ico">↩️</span><span>۷ روز ضمانت بازگشت</span></div>
                <div class="dk-footer-service"><span class="ico">🛡️</span><span>ضمانت اصل بودن کالا</span></div>
            </div>
        </div>

        <?php if ( is_active_sidebar( 'footer-widgets' ) ) : ?>
            <div class="dk-footer-grid">
                <?php dynamic_sidebar( 'footer-widgets' ); ?>
            </div>
        <?php else : ?>
        <div class="dk-footer-grid">
            <div class="dk-footer-col">
                <h4>فروشگاه</h4>
                <p>فروشگاه اینترنتی آرومالند با هزاران محصول، ضمانت اصالت کالا و ارسال سریع به سراسر ایران. خرید آسان و مطمئن را تجربه کنید.</p>
                <form class="dk-newsletter" onsubmit="return false;">
                    <input type="email" placeholder="ایمیل خود را وارد کنید">
                    <button class="dk-btn dk-btn-secondary" type="submit">ثبت</button>
                </form>
            </div>
            <div class="dk-footer-col">
                <h4>با ما همراه باشید</h4>
                <ul>
                    <li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">درباره ما</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">تماس با ما</a></li>
                    <li><a href="#">فرصت‌های شغلی</a></li>
                    <li><a href="#">فروشنده شوید</a></li>
                </ul>
            </div>
            <div class="dk-footer-col">
                <h4>خدمات مشتریان</h4>
                <ul>
                    <li><a href="<?php echo esc_url( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : '#' ); ?>">پیگیری سفارش</a></li>
                    <li><a href="#">شرایط بازگشت کالا</a></li>
                    <li><a href="#">راهنمای خرید</a></li>
                    <li><a href="#">سوالات متداول</a></li>
                </ul>
            </div>
            <div class="dk-footer-col">
                <h4>ما را دنبال کنید</h4>
                <div class="dk-socials">
                    <a href="#" aria-label="اینستاگرام">📷</a>
                    <a href="#" aria-label="تلگرام">✈️</a>
                    <a href="#" aria-label="توییتر">🐦</a>
                    <a href="#" aria-label="لینکدین">💼</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="dk-footer-bottom">
            <span>© تمامی حقوق برای آرومالند محفوظ است.</span>
            <span>استفاده از مطالب فروشگاه فقط برای مقاصد غیرتجاری و با ذکر منبع مجاز است.</span>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
