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
            <?php lylyrose_palette_switcher(); ?>
            <span class="dk-topbar-sep">|</span>
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
            <div class="dk-catnav-scroll">
            <ul class="dk-catnav-list">
                <?php
                /* Fallback labels for the mobile drawer, keyed by the slugs the
                   mega menu uses, and resolved to landing pages below. Only read
                   when the shop has no product categories at all. */
                $cat_menu = array(
                    'عطر و ادکلن'     => 'perfume',
                    'آرایش'          => 'makeup',
                    'مراقبت از پوست'  => 'skincare',
                    'مراقبت از مو'   => 'haircare',
                    'برندها'         => 'brands',
                    'تخفیف‌ها'        => 'sale',
                    'مجله زیبایی'    => 'magazine',
                );

                // The panel is the owner's agreed tree (lylyrose_mega_menu), not a
                // dump of whatever product_cat happens to hold. The per-category
                // fallback below only runs when that menu resolves to nothing —
                // a shop whose categories were renamed away — so the trigger is
                // never a dead end.
                $dk_mega = function_exists( 'lylyrose_mega_menu' ) ? lylyrose_mega_menu() : array();
                if ( empty( $dk_mega ) && function_exists( 'lylyrose_mega_cats_menu' ) ) {
                    $product_cats = function_exists( 'get_terms' ) ? get_terms( array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => true,
                        'parent'     => 0,
                        'exclude'    => array( get_option( 'default_product_cat' ) ),
                        'orderby'    => 'count',
                        'order'      => 'DESC',
                    ) ) : array();
                    if ( ! is_wp_error( $product_cats ) && ! empty( $product_cats ) ) {
                        $dk_mega = array_map( function ( $cell ) {
                            return array(
                                'label' => $cell['name'],
                                'url'   => $cell['url'],
                                'links' => array_map( function ( $l ) {
                                    return array( 'name' => $l['name'], 'url' => $l['url'], 'featured' => false );
                                }, $cell['links'] ),
                            );
                        }, lylyrose_mega_cats_menu( $product_cats ) );
                    }
                }

                /* Landing pages back the nav items outside the panel. Each falls back
                   to the closest real destination, so a page deleted by hand leaves
                   a working link rather than a 404. */
                $dk_landing = function ( $slug, $fallback ) {
                    $page = get_page_by_path( $slug );
                    return $page ? (string) get_permalink( $page ) : $fallback;
                };
                ?>
                <li class="dk-catnav-item"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'lylyrose' ); ?></a></li>
                <?php if ( ! empty( $dk_mega ) ) : ?>
                    <li class="dk-catnav-item dk-mega">
                        <a href="<?php echo esc_url( $shop_url ); ?>" aria-haspopup="true" aria-expanded="false">
                            <?php esc_html_e( 'دسته‌بندی کالا', 'lylyrose' ); ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                        </a>
                        <div class="dk-mega-panel">
                            <div class="dk-mega-inner">
                                <div class="dk-mega-cols">
                                    <?php foreach ( $dk_mega as $dk_section ) : ?>
                                        <div class="dk-mega-col">
                                            <h5><a href="<?php echo esc_url( $dk_section['url'] ); ?>"><?php echo esc_html( $dk_section['label'] ); ?></a></h5>
                                            <?php if ( ! empty( $dk_section['links'] ) ) : ?>
                                                <ul>
                                                    <?php foreach ( $dk_section['links'] as $dk_link ) : ?>
                                                        <li><a class="<?php echo ! empty( $dk_link['featured'] ) ? 'dk-mega-featured' : ''; ?>" href="<?php echo esc_url( $dk_link['url'] ); ?>"><?php echo esc_html( $dk_link['name'] ); ?></a></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>
                <?php if ( class_exists( 'ASC_Perfume_Finder' ) ) : ?>
                    <li class="dk-catnav-item"><a class="dk-mega-featured" href="<?php echo esc_url( get_permalink( get_page_by_path( ASC_Perfume_Finder::PAGE_SLUG ) ) ); ?>"><?php esc_html_e( 'عطرت رو پیدا کن', 'lylyrose' ); ?> ⭐</a></li>
                <?php endif; ?>
                <?php if ( class_exists( 'ASC_Needs_Finder' ) ) : ?>
                    <li class="dk-catnav-item"><a href="<?php echo esc_url( home_url( '/skin-needs/' ) ); ?>"><?php esc_html_e( 'پوستت چه نیازی داره', 'lylyrose' ); ?></a></li>
                    <li class="dk-catnav-item"><a href="<?php echo esc_url( home_url( '/hair-needs/' ) ); ?>"><?php esc_html_e( 'موهات چه نیازی داره', 'lylyrose' ); ?></a></li>
                <?php endif; ?>
                <li class="dk-catnav-item"><a href="<?php echo esc_url( $dk_landing( 'sale', home_url( '/incredible-offers/' ) ) ); ?>"><?php esc_html_e( 'تخفیف‌ها', 'lylyrose' ); ?> 🔥</a></li>
                <li class="dk-catnav-item"><a href="<?php echo esc_url( $dk_landing( 'magazine', home_url( '/about/' ) ) ); ?>"><?php esc_html_e( 'مجله زیبایی', 'lylyrose' ); ?></a></li>
            </ul>
            </div>
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
        <?php if ( class_exists( 'ASC_Perfume_Finder' ) ) : ?>
            <a href="<?php echo esc_url( get_permalink( get_page_by_path( ASC_Perfume_Finder::PAGE_SLUG ) ) ); ?>"><?php esc_html_e( 'عطرت رو پیدا کن', 'lylyrose' ); ?></a>
        <?php endif; ?>
        <?php if ( class_exists( 'ASC_Needs_Finder' ) ) : ?>
            <a href="<?php echo esc_url( home_url( '/skin-needs/' ) ); ?>"><?php esc_html_e( 'پوستت چه نیازی داره', 'lylyrose' ); ?></a>
            <a href="<?php echo esc_url( home_url( '/hair-needs/' ) ); ?>"><?php esc_html_e( 'موهات چه نیازی داره', 'lylyrose' ); ?></a>
        <?php endif; ?>
        <a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'فروشگاه', 'lylyrose' ); ?></a>
        <?php if ( is_user_logged_in() ) : ?>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>"><?php esc_html_e( 'حساب کاربری', 'lylyrose' ); ?></a>
        <?php else : ?>
            <a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url() ); ?>"><?php esc_html_e( 'ورود | ثبت‌نام', 'lylyrose' ); ?></a>
        <?php endif; ?>

        <?php
        /* Drawer rows mirror the desktop mega menu's grouping rather than the old
           gender/concentration split, so the mobile menu teaches the same
           structure the panel does. Each row is keyed by slug, so a category
           this shop has not filled drops out of its group instead of rendering
           a heading with nothing under it. A row with no 'group' key is a
           standalone link. */
        $dk_drawer = array(
            array( 'cats' => array( 'perfume' ) ),
            array( 'group' => __( 'بر اساس جنسیت', 'lylyrose' ), 'cats' => array( 'women', 'men', 'unisex' ) ),
            array( 'group' => __( 'بر اساس نوع', 'lylyrose' ),     'cats' => array( 'samples', 'body-spray' ) ),
            array( 'group' => __( 'بر اساس غلظت', 'lylyrose' ),   'cats' => array( 'eau-de-parfum', 'eau-de-toilette', 'perfume-oil' ) ),
            array( 'cats' => array( 'gift-sets' ) ),
            array( 'cats' => array( 'makeup' ) ),
            array( 'group' => __( 'آرایش', 'lylyrose' ),          'cats' => array( 'face', 'eyes', 'lips', 'brows', 'makeup-tools' ) ),
            array( 'cats' => array( 'skin-care' ) ),
            array( 'group' => __( 'پوست', 'lylyrose' ),           'cats' => array( 'facial-cleanser', 'serums', 'moisturizer', 'sunscreen', 'acne-care', 'dark-spot-care', 'anti-aging' ) ),
            array( 'cats' => array( 'hair-care' ) ),
            array( 'group' => __( 'مو', 'lylyrose' ),             'cats' => array( 'shampoo', 'hair-masks', 'hair-oil', 'anti-hair-loss', 'hair-repair', 'colored-hair' ) ),
            array( 'cats' => array( 'gift-cards' ) ),
        );

        // slug => array( name, url ), from live terms when the shop has them.
        // The drawer wants every category, not the top-level slice the catnav
        // takes: its rows are grouped by slug and its catch-all below renders
        // whatever the rows did not claim, so an unlisted category is the only
        // thing that silently disappears from it.
        $dk_all_cats = function_exists( 'get_terms' ) ? get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'exclude'    => array( get_option( 'default_product_cat' ) ),
            'orderby'    => 'count',
            'order'      => 'DESC',
        ) ) : array();

        $dk_cats = array();
        if ( ! is_wp_error( $dk_all_cats ) && ! empty( $dk_all_cats ) ) {
            foreach ( $dk_all_cats as $term ) {
                $url = get_term_link( $term );
                if ( ! is_wp_error( $url ) ) {
                    $dk_cats[ $term->slug ] = array( $term->name, $url );
                }
            }
        } else {
            foreach ( $cat_menu as $label => $slug ) {
                $page = get_page_by_path( $slug );
                $dk_cats[ $slug ] = array( $label, $page ? (string) get_permalink( $page ) : home_url( '/' ) );
            }
        }

        $dk_done = array();
        foreach ( $dk_drawer as $dk_row ) {
            $dk_items = array();
            foreach ( $dk_row['cats'] as $slug ) {
                $dk_done[] = $slug;
                if ( isset( $dk_cats[ $slug ] ) ) {
                    $dk_items[] = $dk_cats[ $slug ];
                }
            }
            if ( empty( $dk_items ) ) { continue; }

            if ( empty( $dk_row['group'] ) ) {
                foreach ( $dk_items as $dk_item ) {
                    echo '<a href="' . esc_url( $dk_item[1] ) . '">' . esc_html( $dk_item[0] ) . '</a>';
                }
                continue;
            }
            ?>
            <details class="dk-drawer-group">
                <summary><?php echo esc_html( $dk_row['group'] ); ?></summary>
                <?php foreach ( $dk_items as $dk_item ) : ?>
                    <a class="dk-drawer-sub" href="<?php echo esc_url( $dk_item[1] ); ?>"><?php echo esc_html( $dk_item[0] ); ?></a>
                <?php endforeach; ?>
            </details>
            <?php
        }

        // Anything the rows above did not claim (accessories, or a group slug the
        // fallback menu lacks) still belongs in the drawer.
        foreach ( $dk_cats as $dk_slug => $dk_item ) {
            if ( in_array( $dk_slug, $dk_done, true ) ) { continue; }
            echo '<a href="' . esc_url( $dk_item[1] ) . '">' . esc_html( $dk_item[0] ) . '</a>';
        }
        ?>
    </nav>
</aside>

<div id="content">
