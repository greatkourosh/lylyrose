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
        <span>📲 دریافت اپلیکیشن آرومالند</span>
        <button class="dk-announce-close" aria-label="بستن" onclick="document.getElementById('dk-announce').style.display='none'">&times;</button>
    </div>
</div>

<!-- Demo-mode banner (site is not live yet; no real orders are processed) -->
<div class="dk-demo-banner">
    <div class="dk-container">
        🧪 این سایت در <strong>حالت نمایشی (دمو)</strong> است — سفارش‌ها واقعی نیستند و پرداختی انجام نمی‌شود.
    </div>
</div>

<!-- Top bar -->
<div class="dk-topbar">
    <div class="dk-container">
        <div class="dk-topbar-left">
            <span class="dk-demo-badge">🧪 حالت نمایش (Demo Mode) — خرید نهایی ثبت نمی‌شود</span>
            <span class="dk-topbar-sep">|</span>
            <span>🚚 ارسال به سراسر ایران</span>
            <span class="dk-topbar-sep">|</span>
            <a href="<?php echo esc_url( $shop_url ); ?>">پیگیری سفارش</a>
        </div>
        <div class="dk-topbar-right">
            <a href="#" class="dk-sell-link">فروشنده شوید</a>
            <span class="dk-topbar-sep">|</span>
            <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">تماس با ما</a>
            <span class="dk-topbar-sep">|</span>
            <span>🔔 پشتیبانی ۲۴ ساعته</span>
        </div>
    </div>
</div>

<!-- Main header -->
<header class="dk-header">
    <div class="dk-container dk-header-inner">
        <button class="dk-menu-toggle" aria-label="باز کردن منو" onclick="document.querySelector('.dk-drawer').classList.add('active');document.querySelector('.dk-drawer-overlay').classList.add('active')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>

        <a class="dk-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">آروما<span>لند</span></a>

        <form class="dk-search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
            <input type="search" class="dk-search-input" name="s" placeholder="جستجو در آرومالند..." value="<?php echo get_search_query(); ?>">
            <input type="hidden" name="post_type" value="product">
            <button class="dk-search-btn" type="submit" aria-label="جستجو">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
        </form>

        <div class="dk-header-actions">
            <?php if ( is_user_logged_in() ) : ?>
                <a class="dk-header-action" href="<?php echo esc_url( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : wp_login_url() ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    حساب کاربری
                </a>
            <?php else : ?>
                <a class="dk-header-action" href="<?php echo esc_url( wp_login_url() ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    ورود | ثبت‌نام
                </a>
            <?php endif; ?>

            <span class="dk-cart-wrap">
                <a class="dk-header-action" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" aria-label="سبد خرید">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2.5 3h2l2.6 12.5a2 2 0 0 0 2 1.5h8.7a2 2 0 0 0 2-1.6L21.5 7H6"/></svg>
                    <?php if ( $cart_count > 0 ) : ?><i class="dk-cart-count"><?php echo esc_html( digikala_fa_num( $cart_count ) ); ?></i><?php endif; ?>
                </a>
            </span>
        </div>
    </div>

    <!-- Category nav -->
    <nav class="dk-catnav" aria-label="دسته‌بندی کالاها">
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
                        <li class="dk-catnav-item"><a href="<?php echo esc_url( add_query_arg( 'product_cat', $slug, $shop_url ) ); ?>"><?php echo esc_html( $label ); ?></a></li>
                    <?php endforeach;
                endif;
                ?>
                <li class="dk-catnav-item"><a href="<?php echo esc_url( $shop_url ); ?>">همه دسته‌ها</a></li>
            </ul>
        </div>
    </nav>
</header>

<!-- Mobile drawer -->
<div class="dk-drawer-overlay" onclick="this.classList.remove('active');document.querySelector('.dk-drawer').classList.remove('active')"></div>
<aside class="dk-drawer">
    <div class="dk-drawer-header">
        <span class="dk-logo">آروما<span>لند</span></span>
        <button class="dk-drawer-close" aria-label="بستن منو" onclick="this.closest('.dk-drawer').classList.remove('active');document.querySelector('.dk-drawer-overlay').classList.remove('active')">&times;</button>
    </div>
    <nav class="dk-drawer-nav">
        <a href="<?php echo esc_url( $shop_url ); ?>">فروشگاه</a>
        <?php if ( is_user_logged_in() ) : ?>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>">حساب کاربری</a>
        <?php else : ?>
            <a href="<?php echo esc_url( wp_login_url() ); ?>">ورود | ثبت‌نام</a>
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
                echo '<a href="' . esc_url( add_query_arg( 'product_cat', $slug, $shop_url ) ) . '">' . esc_html( $label ) . '</a>';
            }
        }
        ?>
    </nav>
</aside>

<div id="content">
