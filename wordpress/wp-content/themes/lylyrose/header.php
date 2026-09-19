<?php
/**
 * Header — Digikala-style layout: topbar, search header, category nav with mega menu.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

$cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
$shop_url   = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- Announcement top bar -->
<div class="dk-announce" id="dk-announce">
    <div class="dk-container">
        <span><?php esc_html_e( '📲 دریافت اپلیکیشن لیلی رز', 'lylyrose' ); ?></span>
        <button class="dk-announce-close" aria-label="<?php echo esc_attr__( 'بستن', 'lylyrose' ); ?>" onclick="document.getElementById('dk-announce').style.display='none'">&times;</button>
    </div>
</div>

<!-- Demo-mode banner (site is not live yet; no real orders are processed) -->
<div class="dk-demo-banner">
    <div class="dk-container">
        <?php esc_html_e( '🧪 این سایت در ', 'lylyrose' ); ?><strong><?php esc_html_e( 'حالت نمایشی (دمو)', 'lylyrose' ); ?></strong><?php esc_html_e( ' است — سفارش‌ها واقعی نیستند و پرداختی انجام نمی‌شود.', 'lylyrose' ); ?>
    </div>
</div>

<!-- Top bar -->
<div class="dk-topbar">
    <div class="dk-container">
        <div class="dk-topbar-left">
            <span class="dk-demo-badge"><?php esc_html_e( '🧪 حالت نمایش (Demo Mode) — خرید نهایی ثبت نمی‌شود', 'lylyrose' ); ?></span>
            <span class="dk-topbar-sep">|</span>
            <span><?php esc_html_e( '🚚 ارسال به سراسر ایران', 'lylyrose' ); ?></span>
            <span class="dk-topbar-sep">|</span>
            <a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'پیگیری سفارش', 'lylyrose' ); ?></a>
        </div>
        <div class="dk-topbar-right">
            <a href="#" class="dk-sell-link"><?php esc_html_e( 'فروشنده شوید', 'lylyrose' ); ?></a>
            <span class="dk-topbar-sep">|</span>
            <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'تماس با ما', 'lylyrose' ); ?></a>
            <span class="dk-topbar-sep">|</span>
            <span><?php esc_html_e( '🔔 پشتیبانی ۲۴ ساعته', 'lylyrose' ); ?></span>
        </div>
    </div>
</div>

<!-- Main header -->
<header class="dk-header">
    <div class="dk-container dk-header-inner">
        <button class="dk-menu-toggle" aria-label="<?php echo esc_attr__( 'باز کردن منو', 'lylyrose' ); ?>" onclick="document.querySelector('.dk-drawer').classList.add('active');document.querySelector('.dk-drawer-overlay').classList.add('active')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <a class="dk-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">لیلی<span>رز</span></a>

        <form class="dk-search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search" class="dk-search-input" name="s" placeholder="<?php echo esc_attr__( 'جستجو در لیلی رز...', 'lylyrose' ); ?>" value="<?php echo get_search_query(); ?>">
            <input type="hidden" name="post_type" value="product">
            <button class="dk-search-btn" type="submit" aria-label="<?php echo esc_attr__( 'جستجو', 'lylyrose' ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
        </form>

        <div class="dk-header-actions">
            <?php if ( is_user_logged_in() ) : ?>
                <a class="dk-header-action" href="<?php echo esc_url( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : wp_login_url() ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?php esc_html_e( 'حساب کاربری', 'lylyrose' ); ?>
                </a>
            <?php else : ?>
                <a class="dk-header-action" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url() ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    <?php esc_html_e( 'ورود | ثبت‌نام', 'lylyrose' ); ?>
                </a>
            <?php endif; ?>

            <?php if ( is_user_logged_in() && class_exists( 'ASC_Notifications' ) ) : ?>
                <span class="dk-bell-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'asc_notifications' ) ); ?>">
                    <button type="button" class="dk-header-action dk-bell-btn" aria-label="<?php echo esc_attr__( 'اعلان‌ها', 'lylyrose' ); ?>" aria-haspopup="true" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php $bell_count = ASC_Notifications::instance()->get_unread_count( get_current_user_id() ); ?>
                        <?php if ( $bell_count > 0 ) : ?><i class="dk-bell-count"><?php echo esc_html( lylyrose_fa_num( $bell_count ) ); ?></i><?php endif; ?>
                    </button>
                    <div class="dk-bell-dropdown" hidden>
                        <div class="dk-bell-header"><span><?php esc_html_e( 'اعلان‌ها', 'lylyrose' ); ?></span><a class="dk-bell-view-all" href="<?php echo esc_url( wc_get_account_endpoint_url( 'notifications' ) ); ?>"><?php esc_html_e( 'مشاهده همه', 'lylyrose' ); ?></a></div>
                        <ul class="dk-bell-list">
                            <?php foreach ( ASC_Notifications::instance()->get_recent( get_current_user_id(), 5 ) as $n ) :
                                $is_unread = '' === get_post_meta( $n->ID, '_asc_notification_read', true ); ?>
                                <li class="dk-bell-item<?php echo $is_unread ? ' is-unread' : ''; ?>"><a href="<?php echo esc_url( $n->post_content ); ?>"><?php echo esc_html( $n->post_title ); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="dk-bell-footer"><button type="button" class="dk-bell-mark-all"><?php esc_html_e( 'علامتگذاری همه به عنوان خوانده‌شده', 'lylyrose' ); ?></button></div>
                    </div>
                </span>
            <?php endif; ?>

            <span class="dk-cart-wrap">
                <a class="dk-header-action" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" aria-label="<?php echo esc_attr__( 'سبد خرید', 'lylyrose' ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2.5 3h2l2.6 12.5a2 2 0 0 0 2 1.5h8.7a2 2 0 0 0 2-1.6L21.5 7H6"/></svg>
                    <?php if ( $cart_count > 0 ) : ?><i class="dk-cart-count"><?php echo esc_html( lylyrose_fa_num( $cart_count ) ); ?></i><?php endif; ?>
                </a>
            </span>
        </div>
    </div>

    <!-- Category nav -->
    <nav class="dk-catnav" aria-label="<?php echo esc_attr__( 'دسته‌بندی کالاها', 'lylyrose' ); ?>">
        <div class="dk-container">
            <ul class="dk-catnav-list">
                <?php
                $cat_menu = array(
                    'عطر و ادکلن'     => 'perfume',
                    'مردانه'          => 'men',
                    'زنانه'           => 'women',
                    'یونیسکس'         => 'unisex',
                    'ادو پرفیوم'      => 'eau-de-parfum',
                    'ادو تویلت'       => 'eau-de-toilette',
                    'عطر روغنی'       => 'perfume-oil',
                    'بادی اسپلش'      => 'body-spray',
                    'ست هدیه'         => 'gift-sets',
                    'اکسسوری عطر'    => 'accessories',
                );

                // If the shop has perfume categories (WooCommerce), prefer them.
                $product_cats = function_exists( 'get_terms' ) ? get_terms( array(
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => true,
                    'number'     => 9,
                    'exclude'    => array( get_option( 'default_product_cat' ) ),
                ) ) : array();

                if ( ! is_wp_error( $product_cats ) && ! empty( $product_cats ) ) :
                    foreach ( $product_cats as $term ) :
                        $url = get_term_link( $term );
                        if ( is_wp_error( $url ) ) { continue; }
                        ?>
                        <li class="dk-catnav-item"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $term->name ); ?></a></li>
                    <?php endforeach;
                else :
                    foreach ( $cat_menu as $label => $slug ) : ?>
                        <li class="dk-catnav-item"><a href="<?php echo esc_url( add_query_arg( 'product_cat', $slug, $shop_url ) ); ?>"><?php echo esc_html( __( $label, 'lylyrose' ) ); ?></a></li>
                    <?php endforeach;
                endif;
                ?>
                <li class="dk-catnav-item"><a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'همه دسته‌ها', 'lylyrose' ); ?></a></li>
            </ul>
        </div>
    </nav>
</header>

<!-- Mobile drawer -->
<div class="dk-drawer-overlay" onclick="this.classList.remove('active');document.querySelector('.dk-drawer').classList.remove('active')"></div>
<aside class="dk-drawer">
    <div class="dk-drawer-header">
        <span class="dk-logo">لیلی<span>رز</span></span>
        <button class="dk-drawer-close" aria-label="<?php echo esc_attr__( 'بستن منو', 'lylyrose' ); ?>" onclick="this.closest('.dk-drawer').classList.remove('active');document.querySelector('.dk-drawer-overlay').classList.remove('active')">&times;</button>
    </div>
    <nav class="dk-drawer-nav">
        <a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'فروشگاه', 'lylyrose' ); ?></a>
        <?php if ( is_user_logged_in() ) : ?>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'حساب کاربری', 'lylyrose' ); ?></a>
        <?php else : ?>
            <a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url() ); ?>"><?php esc_html_e( 'ورود | ثبت‌نام', 'lylyrose' ); ?></a>
        <?php endif; ?>
        <?php
        if ( ! is_wp_error( $product_cats ) && ! empty( $product_cats ) ) {
            foreach ( $product_cats as $term ) {
                $url = get_term_link( $term );
                if ( is_wp_error( $url ) ) { continue; }
                echo '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
            }
        } else {
            foreach ( $cat_menu as $label => $slug ) {
                echo '<a href="' . esc_url( add_query_arg( 'product_cat', $slug, $shop_url ) ) . '">' . esc_html( __( $label, 'lylyrose' ) ) . '</a>';
            }
        }
        ?>
    </nav>
</aside>

<div id="content">
