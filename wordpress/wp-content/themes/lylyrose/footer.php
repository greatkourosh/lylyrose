<?php
/**
 * Footer — Digikala-style: services strip, link columns, newsletter, socials.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

/*
 * The four service chips carry an icon each, so they are a fixed list of icons with
 * owner-editable labels rather than one free-text string: the emoji is markup, and
 * letting the owner rewrite it would let them silently drop the icons.
 */
$lylyrose_footer_service_icons = array( '🚚', '💳', '↩️', '🛡️' );
$lylyrose_footer_service_labels = array_map(
	'trim',
	explode( ',', lylyrose_contact( 'contact.footer_services', 'امکان تحویل اکسپرس, پرداخت در محل, ۷ روز ضمانت بازگشت, ضمانت اصل بودن کالا' ) )
);
?>
</div><!-- #content -->

<footer class="dk-footer">
    <div class="dk-container">
        <div class="dk-footer-top">
            <span><?php esc_html_e( 'همراه باشید و از تخفیف‌ها باخبر شوید', 'lylyrose' ); ?></span>
            <div class="dk-footer-services">
                <?php foreach ( $lylyrose_footer_service_icons as $lylyrose_si => $lylyrose_sico ) : ?>
                    <?php if ( isset( $lylyrose_footer_service_labels[ $lylyrose_si ] ) && '' !== $lylyrose_footer_service_labels[ $lylyrose_si ] ) : ?>
                        <div class="dk-footer-service"><span class="ico"><?php echo esc_html( $lylyrose_sico ); ?></span><span><?php echo esc_html( $lylyrose_footer_service_labels[ $lylyrose_si ] ); ?></span></div>
                    <?php endif; ?>
                <?php endforeach; ?>
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
                <p><?php echo esc_html( lylyrose_contact( 'contact.footer_about', __( 'فروشگاه اینترنتی لیلی رز با هزاران محصول، ضمانت اصالت کالا و ارسال سریع به سراسر ایران. خرید آسان و مطمئن را تجربه کنید.', 'lylyrose' ) ) ); ?></p>
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
                <?php
                /*
                 * A social with no URL set keeps its old href="#" rather than losing
                 * the icon: the shop has no account yet, and a vanished icon reads as
                 * a broken footer. Setting the URL is what makes it a real link.
                 */
                $lylyrose_socials = array(
                    'instagram' => array( 'اینستاگرام', '📷' ),
                    'telegram'  => array( 'تلگرام', '✈️' ),
                    'twitter'   => array( 'توییتر', '🐦' ),
                    'linkedin'  => array( 'لینکدین', '💼' ),
                );
                ?>
                <div class="dk-socials">
                    <?php foreach ( $lylyrose_socials as $lylyrose_social_key => $lylyrose_social ) : ?>
                        <?php
                        $lylyrose_social_url = lylyrose_contact( 'contact.social_' . $lylyrose_social_key );
                        $lylyrose_social_ext = $lylyrose_social_url ? ' target="_blank" rel="noopener nofollow"' : '';
                        ?>
                        <a href="<?php echo $lylyrose_social_url ? esc_url( $lylyrose_social_url ) : '#'; ?>" aria-label="<?php echo esc_attr( $lylyrose_social[0] ); ?>"<?php echo $lylyrose_social_ext; // phpcs:ignore WordPress.Security.EscapeOutput -- static attribute string ?>><?php echo esc_html( $lylyrose_social[1] ); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="dk-footer-bottom">
            <span><?php echo esc_html( lylyrose_contact( 'contact.copyright', __( '© تمامی حقوق برای لیلی رز محفوظ است.', 'lylyrose' ) ) ); ?></span>
            <span><?php esc_html_e( 'استفاده از مطالب فروشگاه فقط برای مقاصد غیرتجاری و با ذکر منبع مجاز است.', 'lylyrose' ); ?></span>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
