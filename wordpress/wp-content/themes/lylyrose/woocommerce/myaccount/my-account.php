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
    array( 'dashboard', '🏠', __( 'پیشخوان', 'lylyrose' ) ),
    array( 'orders', '📦', __( 'سفارش‌ها', 'lylyrose' ) ),
    array( 'downloads', '⬇️', __( 'دانلودها', 'lylyrose' ) ),
    array( 'edit-address', '📍', __( 'آدرس‌ها', 'lylyrose' ) ),
    array( 'edit-account', '👤', __( 'اطلاعات حساب', 'lylyrose' ) ),
    array( 'notifications', '🔔', __( 'اعلان‌ها', 'lylyrose' ) ),
);
if ( wc_get_page_id( 'wishlist' ) > 0 ) {
    $menu_items[] = array( 'wishlist', '❤️', __( 'علاقه‌مندی‌ها', 'lylyrose' ) );
}
if ( class_exists( 'Woo_Wallet_Frontend' ) ) {
    $menu_items[] = array( 'my-wallet', '💰', __( 'کیف پول', 'lylyrose' ) );
}
?>
<div class="dk-account-page">
    <div class="dk-container">
        <h1 class="dk-cart-title"><?php esc_html_e( 'حساب کاربری', 'lylyrose' ); ?></h1>

        <?php if ( ! $is_logged_in ) : ?>
            <div class="dk-account-login">
                <div class="dk-account-login-card">
                    <div class="dk-account-login-head">
                        <span class="dk-account-login-ic" aria-hidden="true">👤</span>
                        <h2><?php esc_html_e( 'ورود یا ثبت‌نام', 'lylyrose' ); ?></h2>
                        <p><?php esc_html_e( 'برای مشاهده سفارش‌ها و ادامه خرید وارد حساب خود شوید.', 'lylyrose' ); ?></p>
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
                            <span><?php esc_html_e( 'خروج', 'lylyrose' ); ?></span>
                        </a>
                    </nav>
                </aside>

                <div class="dk-account-content">
                    <?php
                    // Fire endpoint-specific action for proper rendering
                    if ( is_wc_endpoint_url( 'notifications' ) ) {
                        do_action( 'woocommerce_account_notifications' );
                    } else {
                        do_action( 'woocommerce_account_content' );
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
