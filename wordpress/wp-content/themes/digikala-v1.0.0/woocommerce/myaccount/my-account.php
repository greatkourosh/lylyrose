<?php
/**
 * My Account — Digikala-style: sidebar dashboard + content card.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

$user = wp_get_current_user();
$is_logged_in = is_user_logged_in();

$menu_items = array(
    array( 'dashboard', '🏠', 'پیشخوان' ),
    array( 'orders', '📦', 'سفارش‌ها' ),
    array( 'downloads', '⬇️', 'دانلودها' ),
    array( 'edit-address', '📍', 'آدرس‌ها' ),
    array( 'edit-account', '👤', 'اطلاعات حساب' ),
);
if ( wc_get_page_id( 'wishlist' ) > 0 ) {
    $menu_items[] = array( 'wishlist', '❤️', 'علاقه‌مندی‌ها' );
}
?>
<div class="dk-account-page">
    <div class="dk-container">
        <h1 class="dk-cart-title">حساب کاربری</h1>

        <?php if ( ! $is_logged_in ) : ?>
            <div class="dk-account-login">
                <div class="dk-account-login-card">
                    <div class="dk-account-login-head">
                        <span class="dk-account-login-ic" aria-hidden="true">👤</span>
                        <h2>ورود یا ثبت‌نام</h2>
                        <p>برای مشاهده سفارش‌ها و ادامه خرید وارد حساب خود شوید.</p>
                    </div>
                    <?php woocommerce_login_form(); ?>
                </div>
            </div>
        <?php else : ?>
            <div class="dk-account-layout">
                <aside class="dk-account-side">
                    <div class="dk-account-user">
                        <span class="dk-account-avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $user->display_name ? $user->display_name : $user->user_login, 0, 1 ) ); ?></span>
                        <div class="dk-account-user-meta">
                            <span class="dk-account-user-name"><?php echo esc_html( $user->display_name ? $user->display_name : $user->user_login ); ?></span>
                            <span class="dk-account-user-phone"><?php echo esc_html( $user->user_email ); ?></span>
                        </div>
                    </div>
                    <nav class="dk-account-nav">
                        <?php foreach ( $menu_items as $mi ) :
                            $url = ( 'wishlist' === $mi[0] ) ? get_permalink( wc_get_page_id( 'wishlist' ) ) : wc_get_account_endpoint_url( $mi[0] );
                            ?>
                            <a class="dk-account-nav-item<?php echo ( 'dashboard' === $mi[0] && ! is_wc_endpoint_url() ) || ( is_wc_endpoint_url( $mi[0] ) && 'wishlist' !== $mi[0] ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
                                <span class="dk-account-nav-ic" aria-hidden="true"><?php echo esc_html( $mi[1] ); ?></span>
                                <span><?php echo esc_html( $mi[2] ); ?></span>
                            </a>
                        <?php endforeach; ?>
                        <a class="dk-account-nav-item dk-account-logout" href="<?php echo esc_url( wc_get_account_endpoint_url( 'customer-logout' ) ); ?>">
                            <span class="dk-account-nav-ic" aria-hidden="true">🚪</span>
                            <span>خروج</span>
                        </a>
                    </nav>
                </aside>

                <div class="dk-account-content">
                    <?php do_action( 'woocommerce_account_content' ); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
