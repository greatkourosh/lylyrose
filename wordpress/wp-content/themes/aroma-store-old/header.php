<?php
defined( 'ABSPATH' ) || exit;
$cart_count = class_exists( 'WooCommerce' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
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

<div class="dk-topbar">
<div class="dk-container">
<div class="dk-topbar-left">
<span class="dk-location"><span>🌍</span> ارسال به همه شهرهای ایران</span>
<a href="<?php echo esc_url( home_url( '/' ) ); ?>">فروشگاه عطر استور</a>
<span class="dk-topbar-sep">|</span>
<a href="<?php echo esc_url( $shop_url ); ?>">دسته‌بندی کالاها</a>
</div>
<div class="dk-topbar-right">
<?php if ( is_user_logged_in() ) : ?>
<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>">حساب کاربری</a>
<?php else : ?>
<a href="<?php echo esc_url( wp_login_url() ); ?>">ورود | ثبت‌نام</a>
<?php endif; ?>
<span class="dk-topbar-sep">|</span>
<a href="#">پشتیبانی ۲۴ ساعته</a>
</div>
</div>
</div>

<header class="dk-header">
<div class="dk-container">
<button class="dk-menu-toggle" aria-label="منو" onclick="document.querySelector('.dk-drawer').classList.add('active');document.querySelector('.dk-drawer-overlay').classList.add('active')">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dk-logo">
عطر<span>استور</span>
</a>

<form class="dk-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
<span class="dk-search-icon" onclick="this.previousElementSibling.submit()">
<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
</span>
<input type="search" name="s" placeholder="جستجوی عطر، برند، رایحه..." value="<?php echo get_search_query(); ?>" aria-label="جستجو">
<input type="hidden" name="post_type" value="product">
</form>

<div class="dk-actions">
<?php if ( is_user_logged_in() ) : ?>
<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dk-action-btn" title="حساب کاربری">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
<span class="dk-action-text">حساب من</span>
</a>
<?php else : ?>
<a href="<?php echo esc_url( wp_login_url() ); ?>" class="dk-action-btn" title="ورود">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
<span class="dk-action-text">ورود</span>
</a>
<?php endif; ?>

<a href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" class="dk-action-btn" title="سبد خرید">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
<span class="dk-action-text">سبد خرید</span>
<?php if ( $cart_count > 0 ) : ?>
<span class="dk-cart-count"><?php echo $cart_count; ?></span>
<?php endif; ?>
</a>

<a href="#" class="dk-action-btn" title="لیست علاقه‌مندی">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
<span class="dk-action-text">علاقه‌مندی</span>
</a>
</div>
</div>
</header>

<nav class="dk-nav">
<div class="dk-container">
<?php
$cats = array(
    'دسته‌بندی‌ها' => $shop_url,
    'مردانه'       => add_query_arg( 'product_cat', 'men', $shop_url ),
    'زنانه'        => add_query_arg( 'product_cat', 'women', $shop_url ),
    'یونیسکس'      => add_query_arg( 'product_cat', 'unisex', $shop_url ),
    'ادو پرفیوم'   => add_query_arg( 'product_cat', 'eau-de-parfum', $shop_url ),
    'ادو تویلت'    => add_query_arg( 'product_cat', 'eau-de-toilette', $shop_url ),
    'عطر روغنی'    => add_query_arg( 'product_cat', 'perfume-oil', $shop_url ),
    'بادی اسپلش'   => add_query_arg( 'product_cat', 'body-spray', $shop_url ),
    'ست هدیه'      => add_query_arg( 'product_cat', 'gift-sets', $shop_url ),
    'سمپل'         => add_query_arg( 'product_cat', 'samples', $shop_url ),
);
$cat_items = array(
    'مردانه'       => add_query_arg( 'product_cat', 'men', $shop_url ),
    'زنانه'        => add_query_arg( 'product_cat', 'women', $shop_url ),
    'یونیسکس'      => add_query_arg( 'product_cat', 'unisex', $shop_url ),
    'ادو پرفیوم'   => add_query_arg( 'product_cat', 'eau-de-parfum', $shop_url ),
    'ادو تویلت'    => add_query_arg( 'product_cat', 'eau-de-toilette', $shop_url ),
    'عطر روغنی'    => add_query_arg( 'product_cat', 'perfume-oil', $shop_url ),
    'بادی اسپلش'   => add_query_arg( 'product_cat', 'body-spray', $shop_url ),
    'ست هدیه'      => add_query_arg( 'product_cat', 'gift-sets', $shop_url ),
    'سمپل'         => add_query_arg( 'product_cat', 'samples', $shop_url ),
);
$mega_menu = array(
    'بر اساس جنسیت' => array(
        'مردانه' => add_query_arg( 'product_cat', 'men', $shop_url ),
        'زنانه'  => add_query_arg( 'product_cat', 'women', $shop_url ),
        'یونیسکس' => add_query_arg( 'product_cat', 'unisex', $shop_url ),
    ),
    'بر اساس غلظت' => array(
        'ادو پرفیوم' => add_query_arg( 'product_cat', 'eau-de-parfum', $shop_url ),
        'ادو تویلت' => add_query_arg( 'product_cat', 'eau-de-toilette', $shop_url ),
        'عطر روغنی' => add_query_arg( 'product_cat', 'perfume-oil', $shop_url ),
    ),
    'بر اساس نوع' => array(
        'بادی اسپلش' => add_query_arg( 'product_cat', 'body-spray', $shop_url ),
        'ست هدیه'   => add_query_arg( 'product_cat', 'gift-sets', $shop_url ),
        'سمپل'      => add_query_arg( 'product_cat', 'samples', $shop_url ),
    ),
);
$first = true;
foreach ( $cats as $label => $url ) :
    if ( $first ) :
        $first = false;
        ?>
        <div class="dk-nav-item dk-mega-trigger">
            <span><?php echo esc_html( $label ); ?></span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            <div class="dk-mega-menu">
                <div class="dk-mega-menu-grid">
                    <?php foreach ( $mega_menu as $group_label => $items ) : ?>
                    <div class="dk-mega-column">
                        <h4><?php echo esc_html( $group_label ); ?></h4>
                        <ul>
                            <?php foreach ( $items as $item_label => $item_url ) : ?>
                            <li><a href="<?php echo esc_url( $item_url ); ?>"><?php echo esc_html( $item_label ); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    else :
        ?>
        <a href="<?php echo esc_url( $url ); ?>" class="dk-nav-item"><?php echo esc_html( $label ); ?></a>
        <?php
    endif;
endforeach;
?>
</div>
</nav>

<div class="dk-drawer-overlay" onclick="this.classList.remove('active');document.querySelector('.dk-drawer').classList.remove('active')"></div>
<div class="dk-drawer">
<div class="dk-drawer-header">
<span class="dk-logo">عطر<span>استور</span></span>
<button class="dk-drawer-close" onclick="this.closest('.dk-drawer').classList.remove('active');document.querySelector('.dk-drawer-overlay').classList.remove('active')">&times;</button>
</div>
<nav class="dk-drawer-nav">
<?php foreach ( $cats as $label => $url ) : ?>
<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
<?php endforeach; ?>
<?php if ( ! is_user_logged_in() ) : ?>
<a href="<?php echo esc_url( wp_login_url() ); ?>">ورود / ثبت‌نام</a>
<?php endif; ?>
</nav>
</div>

<div id="content">