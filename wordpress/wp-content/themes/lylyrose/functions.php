<?php
/**
 * Lyly Rose Theme Functions
 *
 * @package Lyly_Rose
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme version, read from style.css so there is a single source of truth.
 * Used for asset cache-busting and the admin-only version badge.
 */
function lylyrose_version() {
    static $version = null;
    if ( null === $version ) {
        $version = wp_get_theme( 'lylyrose' )->get( 'Version' );
        if ( ! $version ) {
            $version = '0';
        }
    }
    return $version;
}

/**
 * The visitor-facing palette switcher, rendered in the topbar.
 *
 * Deliberately NOT in dk-header-actions next to the account and cart icons: that
 * row is width-constrained, and .dk-search-form once measured 0.2px wide at a
 * 375px viewport because the icon pair took every pixel. The topbar is a
 * text-link row with no such pressure, and it is on the homepage too.
 *
 * Renders nothing when the owner has switched the feature off, when the plugin is
 * absent, or when there is only one palette to choose between — a control with
 * one option is not a control.
 */
function lylyrose_palette_switcher() {
    if ( ! class_exists( 'ASC_Palette' ) ) {
        return;
    }
    $config = ASC_Palette::get_config();
    if ( empty( $config['enabled'] ) || count( $config['presets'] ) < 2 ) {
        return;
    }
    $active = ASC_Palette::current_slug();
    ?>
    <span class="dk-palette" role="group" aria-label="<?php esc_attr_e( 'انتخاب پالت رنگی', 'lylyrose' ); ?>">
        <?php foreach ( $config['presets'] as $slug => $cols ) : ?>
            <?php
            $label = isset( $cols['label'] ) && '' !== $cols['label'] ? $cols['label'] : $slug;
            $on    = ( $slug === $active );
            ?>
            <button type="button"
                class="dk-palette-btn<?php echo $on ? ' is-active' : ''; ?>"
                data-dk-palette="<?php echo esc_attr( $slug ); ?>"
                aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"
                title="<?php echo esc_attr( $label ); ?>">
                <span class="dk-palette-swatch" aria-hidden="true"
                    style="background:<?php echo esc_attr( $cols['bg'] ?? '#fff' ); ?>;border-color:<?php echo esc_attr( $cols['red'] ?? '#888' ); ?>;"></span>
                <span class="dk-palette-label"><?php echo esc_html( $label ); ?></span>
            </button>
        <?php endforeach; ?>
    </span>
    <?php
}

/**
 * Theme setup
 */
function lylyrose_setup() {
    load_theme_textdomain( 'lylyrose', get_template_directory() . '/languages' );

    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'custom-background' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'responsive-embeds' );

    // WooCommerce
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );

    register_nav_menus( array(
        'primary' => esc_html__( 'منوی اصلی', 'lylyrose' ),
        'footer'  => esc_html__( 'منوی فوتر', 'lylyrose' ),
    ) );

    // Avoid block library CSS conflicts
    add_filter( 'should_load_separate_core_block_assets', '__return_false' );
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_registered_block_scripts_and_styles' );

    // Disable emoji scripts
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
}
add_action( 'after_setup_theme', 'lylyrose_setup' );

/**
 * Scripts & styles
 */
function lylyrose_scripts() {
    // IRANYekan is Digikala's actual font; Vazirmatn is the free fallback.
    wp_enqueue_style( 'lylyrose-font-vazirmatn', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css', array(), '33.003' );
    wp_enqueue_style( 'lylyrose-style', get_stylesheet_uri(), array( 'lylyrose-font-vazirmatn' ), lylyrose_version() );

    // The base stylesheet is RTL-native (html{direction:rtl}); for LTR locales
    // (e.g. English) layer a small override on top.
    if ( ! is_rtl() ) {
        wp_enqueue_style( 'lylyrose-en-ltr', get_theme_file_uri( 'assets/css/en-ltr.css' ), array( 'lylyrose-style' ), lylyrose_version() );
    }

    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }

    if ( is_front_page() ) {
        wp_enqueue_script(
            'lylyrose-hero',
            get_theme_file_uri( 'assets/js/hero.js' ),
            array(),
            lylyrose_version(),
            true
        );
    }

    // The palette switcher is site-wide, not homepage-only: a visitor who picks
    // Night on the homepage should still have it on the shop and in the cart.
    // Depends on nothing, so it loads on every page like the header it lives in.
    wp_enqueue_script(
        'lylyrose-palette',
        get_theme_file_uri( 'assets/js/palette.js' ),
        array(),
        lylyrose_version(),
        true
    );

    // Mega menu: tap-to-open for touch, where the CSS :hover never fires.
    wp_enqueue_script(
        'lylyrose-mega',
        get_theme_file_uri( 'assets/js/mega.js' ),
        array(),
        lylyrose_version(),
        true
    );
}
add_action( 'wp_enqueue_scripts', 'lylyrose_scripts' );

/**
 * OTP login script — only on the My Account page when not logged in.
 */
function lylyrose_otp_script() {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || is_user_logged_in() ) {
        return;
    }
    wp_enqueue_script(
        'lylyrose-otp-login',
        get_theme_file_uri( 'assets/js/otp-login.js' ),
        array(),
        lylyrose_version(),
        true
    );
    wp_localize_script( 'lylyrose-otp-login', 'dk_otp_i18n', array(
        'invalidMobile' => __( 'شماره موبایل معتبر نیست.', 'lylyrose' ),
        'sendFailed'    => __( 'خطا در ارسال کد.', 'lylyrose' ),
        'invalidCode'   => __( 'کد ۶ رقمی را وارد کنید.', 'lylyrose' ),
        'verifyFailed'  => __( 'کد تایید نشد.', 'lylyrose' ),
        'welcome'       => __( 'خوش آمدید! در حال انتقال...', 'lylyrose' ),
        'resend'        => __( 'ارسال مجدد کد', 'lylyrose' ),
        'networkError'  => __( 'خطای شبکه. دوباره تلاش کنید.', 'lylyrose' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'lylyrose_otp_script', 20 );

/**
 * 2-step checkout stepper — only on the checkout page.
 */
function lylyrose_checkout_stepper_script() {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        return;
    }
    wp_enqueue_script(
        'lylyrose-checkout-stepper',
        get_theme_file_uri( 'assets/js/checkout-stepper.js' ),
        array(),
        lylyrose_version(),
        true
    );
}
add_action( 'wp_enqueue_scripts', 'lylyrose_checkout_stepper_script', 20 );

/**
 * Notifications bell — loaded for every logged-in page.
 */
function lylyrose_notifications_script() {
    if ( ! is_user_logged_in() || ! class_exists( 'ASC_Notifications' ) ) {
        return;
    }
    wp_enqueue_script(
        'lylyrose-notifications',
        get_theme_file_uri( 'assets/js/notifications.js' ),
        array(),
        lylyrose_version(),
        true
    );
    wp_localize_script( 'lylyrose-notifications', 'asc_notifications', array(
        'nonce'   => wp_create_nonce( 'asc_notifications' ),
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'lylyrose_notifications_script', 20 );

/**
 * Gift wrap CSS — loaded on cart and checkout pages.
 */
function lylyrose_gift_wrap_style() {
    if ( is_cart() || is_checkout() ) {
        wp_enqueue_style(
            'lylyrose-gift-wrap',
            get_theme_file_uri( 'assets/css/gift-wrap.css' ),
            array(),
            lylyrose_version()
        );
    }
}
add_action( 'wp_enqueue_scripts', 'lylyrose_gift_wrap_style', 20 );

add_action( 'wp_enqueue_scripts', 'lylyrose_flash_sales_assets', 20 );

/**
 * Perfume finder assets — only on the finder page.
 */
function lylyrose_perfume_finder_assets() {
	if ( ! class_exists( 'ASC_Perfume_Finder' ) || ! ASC_Perfume_Finder::is_finder_page() ) {
		return;
	}
	wp_enqueue_style(
		'lylyrose-perfume-finder',
		get_theme_file_uri( 'assets/css/perfume-finder.css' ),
		array( 'lylyrose-style' ),
		lylyrose_version()
	);
}
add_action( 'wp_enqueue_scripts', 'lylyrose_perfume_finder_assets', 20 );
/**
 * Back-in-stock notifier script — only on out-of-stock single product pages.
 */
function lylyrose_stock_notifier_script() {
    if ( ! is_singular( 'product' ) || ! class_exists( 'ASC_Stock_Notifier' ) ) {
        return;
    }
    // The loop (and wc_setup_product_data) runs after get_header(), so the
    // $product global is not populated at wp_enqueue_scripts yet — resolve
    // through the queried post instead.
    $post = get_queried_object();
    $product = $post instanceof WP_Post ? wc_get_product( $post ) : null;
    if ( ! $product instanceof WC_Product || $product->is_in_stock() ) {
        return;
    }
    wp_enqueue_script(
        'lylyrose-stock-notifier',
        get_theme_file_uri( 'assets/js/stock-notifier.js' ),
        array(),
        lylyrose_version(),
        true
    );
    wp_localize_script( 'lylyrose-stock-notifier', 'dk_stock_i18n', array(
        'invalidMobile' => __( 'شماره موبایل معتبر نیست.', 'lylyrose' ),
        'subscribed'    => __( 'ثبت‌نام شد.', 'lylyrose' ),
        'subscribeFailed' => __( 'خطا در ثبت‌نام.', 'lylyrose' ),
        'networkError'  => __( 'خطای شبکه. دوباره تلاش کنید.', 'lylyrose' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'lylyrose_stock_notifier_script', 20 );

/**
 * Admin-bar badge showing the theme version (admins only, never on the front end).
 */
function lylyrose_admin_bar_version( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $wp_admin_bar->add_node( array(
        'id'     => 'dk-theme-version',
        'parent' => 'top-secondary',
        'title'  => sprintf( __( 'قالب لیلی رز %s', 'lylyrose' ), lylyrose_version() ),
        'meta'   => array( 'title' => __( 'نسخهٔ فعال قالب', 'lylyrose' ) ),
    ) );
}
add_action( 'admin_bar_menu', 'lylyrose_admin_bar_version', 100 );

/**
 * Detect the wp-login screen that leads to wp-admin (admin/backoffice entry).
 * The customer/vendor login lives at /my-account/ with the OTP tabs; wp-login.php
 * only ever serves admins, so give it its own dark "control room" look.
 */
function lylyrose_is_admin_login_screen() {
    if ( ! did_action( 'login_init' ) && ! defined( 'WP_LOGIN_PAGE' ) ) {
        return false;
    }
    $redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
    $is_admin = ( ! empty( $redirect ) && str_contains( $redirect, '/wp-admin' ) )
        || isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], array( 'confirm_admin_email', 'resetpass_for_admin' ), true );

    return $is_admin || empty( $redirect ) || true; // wp-login.php is never the customer path; always style it as admin.
}

/**
 * Distinct admin login screen: dark slate backdrop, monochrome card, panel label —
 * visually unrelated to the red/teal customer OTP login at /my-account/.
 */
function lylyrose_admin_login_styles() {
    ?>
    <style id="dk-admin-login">
        html, body.login { background: #1c2333 !important; }
        body.login { min-height: 100vh; }
        body.login::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                radial-gradient(1200px 500px at 85% -10%, rgba(239, 57, 78, .18), transparent 60%),
                radial-gradient(900px 500px at 10% 110%, rgba(25, 191, 211, .10), transparent 60%);
            pointer-events: none;
        }
        body.login #login {
            position: relative;
            padding-top: 12vh;
            width: 380px;
        }
        body.login #login h1 a {
            background-image: none !important;
            width: auto; height: auto;
            font-size: 28px; font-weight: 800;
            color: #fff; letter-spacing: .5px;
            text-indent: 0;
            margin-bottom: 2px;
        }
        body.login #login h1 a::after {
            content: "";
            display: block;
            width: 64px; height: 3px;
            margin: 10px auto 0;
            border-radius: 2px;
            background: linear-gradient(90deg, #ef394e, #19bfd3);
        }
        body.login .dk-login-panel-label {
            text-align: center;
            color: #8b93a7;
            font-size: 12.5px;
            margin: 0 0 14px;
            letter-spacing: .3px;
        }
        body.login .dk-login-panel-label strong { color: #f9a825; font-weight: 700; }
        body.login form,
        body.login #login_error,
        body.login .message,
        body.login .notice {
            background: #232c42 !important;
            border: 1px solid #313c58 !important;
            box-shadow: 0 12px 32px rgba(0, 0, 0, .35) !important;
            border-radius: 12px !important;
            color: #d7dce8 !important;
        }
        body.login form { padding: 26px 24px 30px; }
        body.login label {
            color: #aab2c5 !important;
            font-size: 13px;
        }
        body.login .input,
        body.login input[type="text"],
        body.login input[type="password"] {
            background: #1a2233 !important;
            border: 1px solid #3a465f !important;
            color: #eef1f7 !important;
            border-radius: 8px !important;
            padding: 8px 10px;
            font-size: 15px;
        }
        body.login .input:focus {
            border-color: #19bfd3 !important;
            box-shadow: 0 0 0 2px rgba(25, 191, 211, .25) !important;
        }
        body.login input[type="checkbox"],
        body.login input[type="checkbox"]:checked::before { accent-color: #ef394e; }
        body.login #wp-submit {
            background: linear-gradient(135deg, #ef394e, #d32f45) !important;
            border: none !important;
            border-radius: 8px !important;
            height: 40px;
            font-weight: 700;
            font-size: 14.5px;
            box-shadow: 0 6px 16px rgba(239, 57, 78, .35);
        }
        body.login #wp-submit:hover { filter: brightness(1.08); }
        body.login #nav,
        body.login #backtoblog {
            text-align: center;
            font-size: 12.5px;
        }
        body.login #nav a,
        body.login #backtoblog a { color: #7f889d !important; }
        body.login #nav a:hover,
        body.login #backtoblog a:hover { color: #19bfd3 !important; }
        body.login .language-switcher { margin-top: 18px; }
        body.login .language-switcher label,
        body.login .language-switcher button { color: #8b93a7 !important; }
        body.login .language-switcher button {
            background: #232c42 !important;
            border: 1px solid #313c58 !important;
        }
        body.login #login_error,
        body.login .message.notice-warning {
            border-right: 4px solid #f9a825 !important;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('#login form');
        if (form && !document.querySelector('.dk-login-panel-label')) {
            var label = document.createElement('p');
            label.className = 'dk-login-panel-label';
            label.innerHTML = 'پنل مدیریت لیلی رز — <strong>دسترسی ویژه</strong>';
            form.parentNode.insertBefore(label, form);
        }
    });
    </script>
    <?php
}
add_action( 'login_enqueue_scripts', 'lylyrose_admin_login_styles' );
add_action( 'login_head', 'lylyrose_admin_login_styles' );

/**
 * Brand the wp-login logo as لیلی رز پنل مدیریت (text, dark theme).
 */
function lylyrose_admin_login_headertext( $text ) {
    return 'لیلی رز';
}
add_filter( 'login_headertext', 'lylyrose_admin_login_headertext' );
function lylyrose_admin_login_headerurl( $url ) {
    return home_url( '/wp-admin/' );
}
add_filter( 'login_headerurl', 'lylyrose_admin_login_headerurl' );

/**
 * Widget areas
 */
function lylyrose_widgets_init() {
    register_sidebar( array(
        'name'          => esc_html__( 'ستون کناری', 'lylyrose' ),
        'id'            => 'sidebar-1',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'ویجت‌های فوتر', 'lylyrose' ),
        'id'            => 'footer-widgets',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'lylyrose_widgets_init' );

/**
 * WooCommerce tweaks
 */
add_filter( 'woocommerce_enqueue_styles', '__return_false' );
/**
 * Checkout: this store ships inside Iran, so the country is not a choice.
 * Pinning it to a single value also makes WooCommerce collapse the country
 * select, which removes the 250-entry dropdown from the top of the form.
 */
add_filter( 'woocommerce_countries_allowed_countries', function( $countries ) {
	return array( 'IR' => 'ایران' );
} );
add_filter( 'woocommerce_countries_shipping_countries', function( $countries ) {
	return array( 'IR' => 'ایران' );
} );
add_filter( 'woocommerce_checkout_posted_data', function( $data ) {
	$data['billing_country']  = 'IR';
	$data['shipping_country'] = 'IR';
	return $data;
} );

/**
 * Checkout field order: province and city first, then the rest of the address.
 * Runs at 30 so it wins over the national-id plugin (priority 20).
 */
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
	if ( ! isset( $fields['billing'] ) ) {
		return $fields;
	}
	$order = array(
		'billing_state'       => 25,
		'billing_city'        => 26,
		'billing_address_1'   => 50,
		'billing_address_2'   => 51,
		'billing_national_id' => 52,
		'billing_postcode'    => 90,
	);
	foreach ( $order as $key => $priority ) {
		if ( isset( $fields['billing'][ $key ] ) ) {
			$fields['billing'][ $key ]['priority'] = $priority;
		}
	}
	return $fields;
}, 30 );

/**
 * Put province and city back above the address after the Iran-cities script
 * rewrites the billing block. Also drops the country row: the country is
 * pinned to Iran, so the one-option select is pure noise.
 */
add_action( 'wp_footer', 'dk_checkout_reorder_fields', 5 );
function dk_checkout_reorder_fields() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	?>
	<script>
	jQuery( function ( $ ) {
		var armed = 0;
		function tidy() {
			// The Iran-cities script rewrites the billing block on every
			// updated_checkout, which wipes this class off the pickers. Re-apply
			// it once more per burst rather than on every event.
			clearTimeout( armed );
			armed = setTimeout( tidy, 100 );
			$( '.woocommerce-billing-fields__field-wrapper' ).each( function () {
				var $w = $( this );
				[ 'billing_city', 'billing_state' ].forEach( function ( id ) {
					$w.prepend( $w.find( '#' + id ).closest( '.form-row' ) );
				} );
				$w.find( '#billing_country_field' ).addClass( 'is-hidden' );
				// The Iran-cities script puts form-row-wide back on both pickers and
				// strips data-priority from the row it re-renders, so the city row is
				// the one without it. Read the classes off the live select instead.
				$w.find( '#billing_state, #billing_city' ).closest( '.form-row' )
					.removeClass( 'form-row-wide' )
					.addClass( 'dk-row-half' );
			} );
		}
		// The theme's update_order_review fragments hard-code the field set, so
		// data-priority goes stale and WooCommerce re-inserts the rows in server
		// order. Re-tidy once the fragments land.
		tidy();
		$( document.body ).on( 'updated_checkout change', tidy );
	} );
	</script>
	<?php
}


add_filter( 'loop_shop_columns', function() { return 3; }, 999 );

/**
 * Product thumbnails are portrait photos of bottles (up to 3.5:1 tall), but
 * WooCommerce's default is a hard 1:1 crop, which slices the top and bottom off
 * the product before it ever reaches CSS. Every consumer of this size already
 * renders with object-fit: contain, so an uncropped thumbnail is the shape they
 * expect. Width stays at the WooCommerce default so grid density is unchanged.
 *
 * The hook name looks like it targets the core 'thumbnail' size (150x150) and it
 * does not: WooCommerce strips the 'woocommerce_' prefix from the size name
 * before firing this filter, so 'woocommerce_thumbnail' fires
 * 'woocommerce_get_image_size_thumbnail'. The cache key below, built from the
 * FULL name, is the only place the prefix survives.
 */
add_filter( 'woocommerce_get_image_size_thumbnail', function( $size ) {
    $size['height'] = '';
    $size['crop']   = 0;
    return $size;
} );

/**
 * wc_get_image_size() memoises into the WooCommerce cache group and returns
 * before the filter above runs, so the stale cropped entry must be dropped.
 */
add_action( 'after_setup_theme', function() {
    wp_cache_delete( 'size-woocommerce_thumbnail', 'woocommerce' );
}, 5 );

add_filter( 'woocommerce_output_related_products_args', function( $args ) {
    $args['posts_per_page'] = 4;
    $args['columns'] = 4;
    return $args;
} );

/**
 * Attribute facets shown in the shop filter rail: GET param => taxonomy + label.
 */
function lylyrose_filter_facets() {
    return array(
        'dk_brands'        => array( 'taxonomy' => 'pa_brand', 'label' => __( 'برند', 'lylyrose' ), 'scroll' => true ),
        'dk_gender'        => array( 'taxonomy' => 'pa_gender', 'label' => __( 'جنسیت', 'lylyrose' ), 'scroll' => false ),
        'dk_concentration' => array( 'taxonomy' => 'pa_concentration', 'label' => __( 'غلظت', 'lylyrose' ), 'scroll' => false ),
        'dk_volume'        => array( 'taxonomy' => 'pa_volume', 'label' => __( 'حجم', 'lylyrose' ), 'scroll' => false ),
    );
}

/**
 * Selected term IDs for one facet param, from the query string.
 */
function lylyrose_facet_selection( $param ) {
    $ids = isset( $_GET[ $param ] ) ? array_map( 'absint', (array) wp_unslash( $_GET[ $param ] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    return array_values( array_filter( $ids ) );
}

/**
 * Shop/category filters (URL-driven GET params, Digikala-style):
 * sort, price_min, price_max, in_stock, discount, and one param per
 * attribute facet in lylyrose_filter_facets().
 */
function lylyrose_shop_filters( $q ) {
    if ( is_admin() || ! $q->is_main_query() ) {
        return;
    }
    if ( ! $q->is_post_type_archive( 'product' ) && ! $q->is_tax( 'product_cat' ) && ! $q->is_tax( 'product_tag' ) ) {
        return;
    }

    // Ordering.
    $sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    switch ( $sort ) {
        case 'popular':
            $q->set( 'meta_key', 'total_sales' );
            $q->set( 'orderby', 'meta_value_num' );
            $q->set( 'order', 'DESC' );
            break;
        case 'newest':
            $q->set( 'orderby', 'date' );
            $q->set( 'order', 'DESC' );
            break;
        case 'cheapest':
            $q->set( 'meta_key', '_price' );
            $q->set( 'orderby', 'meta_value_num' );
            $q->set( 'order', 'ASC' );
            break;
        case 'expensive':
            $q->set( 'meta_key', '_price' );
            $q->set( 'orderby', 'meta_value_num' );
            $q->set( 'order', 'DESC' );
            break;
        case 'rating':
            $q->set( 'meta_key', '_wc_average_rating' );
            $q->set( 'orderby', 'meta_value_num' );
            $q->set( 'order', 'DESC' );
            break;
        case 'discount':
            // Percent is computed in SQL from price meta; see lylyrose_discount_sort_orderby().
            $q->set( 'asc_discount_sort', 1 );
            break;
    }

    // Price range.
    $price_min = isset( $_GET['price_min'] ) ? (int) $_GET['price_min'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $price_max = isset( $_GET['price_max'] ) ? (int) $_GET['price_max'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( $price_min > 0 || $price_max > 0 ) {
        $meta_query = (array) $q->get( 'meta_query' );
        $range      = array( 'key' => '_price', 'type' => 'NUMERIC', 'compare' => 'BETWEEN' );
        $range['value'] = array( $price_min > 0 ? $price_min : 0, $price_max > 0 ? $price_max : PHP_INT_MAX );
        $meta_query[] = $range;
        $q->set( 'meta_query', $meta_query );
    }

    // Availability / discount toggles via tax_query + meta_query combos.
    $in_stock     = ! empty( $_GET['in_stock'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $has_discount = ! empty( $_GET['discount'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( $in_stock || $has_discount ) {
        $meta_query = (array) $q->get( 'meta_query' );
        if ( $in_stock ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array( 'key' => '_stock_status', 'value' => 'instock' ),
                array( 'key' => '_stock_status', 'value' => 'onbackorder' ),
            );
        }
        if ( $has_discount ) {
            $meta_query[] = array(
                'key'     => '_sale_price',
                'compare' => '>',
                'value'   => 0,
                'type'    => 'NUMERIC',
            );
        }
        $q->set( 'meta_query', $meta_query );
    }

    // Attribute facet filters (brand/gender/concentration/volume terms).
    foreach ( lylyrose_filter_facets() as $param => $facet ) {
        $terms = lylyrose_facet_selection( $param );
        if ( $terms ) {
            $tax_query = (array) $q->get( 'tax_query' );
            $tax_query[] = array(
                'taxonomy' => $facet['taxonomy'],
                'field'    => 'term_id',
                'terms'    => $terms,
            );
            $q->set( 'tax_query', $tax_query );
        }
    }
}
add_action( 'pre_get_posts', 'lylyrose_shop_filters', 20 );

/**
 * Filtered shop/category URLs (any GET filter active) get noindex,follow so
 * facet permutations don't create duplicate-content pages for search engines.
 */
function lylyrose_filtered_shop_robots( $robots ) {
    if ( is_admin() || is_feed() ) {
        return $robots;
    }
    $q = $GLOBALS['wp_query'];
    if ( ! $q || ( ! $q->is_post_type_archive( 'product' ) && ! $q->is_tax( 'product_cat' ) && ! $q->is_tax( 'product_tag' ) ) ) {
        return $robots;
    }
    $filtered_params = array_merge( array( 'sort', 'price_min', 'price_max', 'in_stock', 'discount' ), array_keys( lylyrose_filter_facets() ) );
    foreach ( $filtered_params as $param ) {
        if ( isset( $_GET[ $param ] ) && '' !== $_GET[ $param ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $robots['noindex'] = true;
            $robots['follow']  = true;
            break;
        }
    }
    return $robots;
}
add_filter( 'wp_robots', 'lylyrose_filtered_shop_robots' );

/**
 * ORDER BY discount percent for sort=discount: (regular-sale)/regular DESC.
 * Joins regular + sale price meta and sorts on the computed percent.
 */
function lylyrose_discount_sort_join( $join, $q ) {
    if ( ! $q->get( 'asc_discount_sort' ) ) {
        return $join;
    }
    global $wpdb;
    $join .= " LEFT JOIN {$wpdb->postmeta} AS dk_reg ON dk_reg.post_id = {$wpdb->posts}.ID AND dk_reg.meta_key = '_regular_price'
               LEFT JOIN {$wpdb->postmeta} AS dk_sale ON dk_sale.post_id = {$wpdb->posts}.ID AND dk_sale.meta_key = '_sale_price'";
    return $join;
}
add_filter( 'posts_join', 'lylyrose_discount_sort_join', 10, 2 );

function lylyrose_discount_sort_orderby( $orderby, $q ) {
    if ( ! $q->get( 'asc_discount_sort' ) ) {
        return $orderby;
    }
    // In-stock products first, then biggest percent first.
    return "(dk_sale.meta_value > 0) * ( ( CAST(dk_reg.meta_value AS UNSIGNED) - CAST(dk_sale.meta_value AS UNSIGNED) ) / GREATEST(CAST(dk_reg.meta_value AS UNSIGNED),1) ) DESC, post_date DESC";
}
add_filter( 'posts_orderby', 'lylyrose_discount_sort_orderby', 10, 2 );

// Persian digits for prices when a Persian-numbers helper exists.
if ( ! function_exists( 'lylyrose_fa_num' ) ) {
    function lylyrose_fa_num( $str ) {
        return str_replace(
            array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
            array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
            (string) $str
        );
    }
}

/**
 * Discount percent badge helper.
 */
function lylyrose_discount_percent( $product ) {
    if ( ! $product || ! $product->is_on_sale() ) {
        return 0;
    }
    $regular = (float) $product->get_regular_price();
    $sale    = (float) $product->get_sale_price();
    if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
        return 0;
    }
    return (int) round( ( $regular - $sale ) / $regular * 100 );
}

/**
 * Representative image for a product category: the term thumbnail when set,
 * otherwise the newest in-category product's image.
 *
 * Cached as a root-relative path rather than an absolute URL. The site answers
 * on more than one host (the published LAN port and the local HTTPS proxy), so
 * an absolute URL cached from whichever request reached the cache first pins
 * every later visitor's images to that host for the whole transient TTL.
 */
function lylyrose_term_image( $term, $size = 'woocommerce_thumbnail' ) {
    $cache_key = 'lylyrose_term_img_' . $term->term_id . '_' . $size;
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached ? $cached : '';
    }

    $url    = '';
    $thumb  = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
    if ( $thumb ) {
        $url = (string) wp_get_attachment_image_url( $thumb, $size );
    }

    if ( ! $url ) {
        $ids = get_posts( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
            'tax_query'      => array( array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ) ),
        ) );
        if ( $ids ) {
            $url = (string) get_the_post_thumbnail_url( $ids[0], $size );
        }
    }

    $path = $url ? wp_parse_url( $url, PHP_URL_PATH ) : '';
    if ( ! $path ) {
        $path = '0';
    }

    set_transient( $cache_key, $path, 12 * HOUR_IN_SECONDS );
    return $path;
}

/**
 * A photo that matches what a promotional cell is selling.
 *
 * A banner captioned "پرفروش‌ترین‌ها" over a picture of something else reads as a
 * bug, so the caller picks the product with the same query its own link already
 * sorts by. $args goes straight to get_posts().
 *
 * Returns a root-relative path, never an absolute URL: the site answers on more
 * than one host, and a URL cached from whichever request won the race would pin
 * every later visitor's photos to that host. Same reasoning as
 * lylyrose_term_image().
 */
function lylyrose_promo_image( $args = array(), $size = 'medium' ) {
    $cache_key = 'lylyrose_promo_img_' . md5( $size . wp_json_encode( $args ) );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached ? $cached : '';
    }

    $ids = get_posts( array_merge( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
    ), $args ) );

    $path = '0';
    if ( $ids ) {
        $url = get_the_post_thumbnail_url( $ids[0], $size );
        if ( $url ) {
            $found = wp_parse_url( $url, PHP_URL_PATH );
            if ( $found ) {
                $path = $found;
            }
        }
    }

    set_transient( $cache_key, $path, 12 * HOUR_IN_SECONDS );
    return $path;
}

/**
 * The photo an owner picked for a hero slide, as a root-relative path.
 *
 * Nothing is derived from the catalogue: a product packshot stretched to full
 * bleed looks worse than no photo at all, so an unset slide deliberately renders
 * nothing and keeps the hero's own gradient. What is wanted here is a scene
 * shot, and only the owner knows which one.
 *
 * Root-relative for the reason lylyrose_term_image() gives — the site answers on
 * more than one host, and an absolute URL would pin the photo to whichever host
 * the theme mod happened to be saved from.
 */
function lylyrose_hero_photo( $key ) {
	$id = (int) get_theme_mod( 'lylyrose_hero_photo_' . $key );
	if ( ! $id ) {
		return '';
	}
	$url = wp_get_attachment_image_url( $id, 'full' );
	if ( ! $url ) {
		return '';
	}
	return wp_parse_url( $url, PHP_URL_PATH ) ?: '';
}

/**
 * A "تصویر اسلاید" control per hero slide, under Appearance → Customize.
 *
 * The five keys are the slide identifiers from front-page.php, not array
 * indices: the perfume-finder slide is conditional, so an indexed key would
 * silently repoint every photo the moment that plugin is switched off.
 */
function lylyrose_customize_register( $wp_customize ) {
	$slides = array(
		'sale'    => __( 'اسلاید ۱ — پیشنهاد ویژه امروز', 'lylyrose' ),
		'newest'  => __( 'اسلاید ۲ — جدیدترین محصولات', 'lylyrose' ),
		'popular' => __( 'اسلاید ۳ — پرفروش‌ترین‌ها', 'lylyrose' ),
		'finder'  => __( 'اسلاید ۴ — عطرت رو پیدا کن', 'lylyrose' ),
		'offers'  => __( 'اسلاید ۵ — شگفت‌انگیزها', 'lylyrose' ),
	);

	$wp_customize->add_section( 'lylyrose_hero', array(
		'title'       => __( 'تصویر اسلایدهای صفحه اصلی', 'lylyrose' ),
		'priority'    => 30,
		'description' => __( 'تصویری که پشت هر اسلاید بنشیند. خالی بگذارید تا همان پس‌زمینهٔ گرادیانی بماند؛ عکس محصول برای این کار مناسب نیست.', 'lylyrose' ),
	) );

	foreach ( $slides as $key => $label ) {
		$wp_customize->add_setting( 'lylyrose_hero_photo_' . $key, array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'transport'         => 'refresh',
		) );

		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'lylyrose_hero_photo_' . $key, array(
			'label'       => $label,
			'section'     => 'lylyrose_hero',
			'settings'    => 'lylyrose_hero_photo_' . $key,
			'description' => __( 'تصویر عریض (حداقل ۱۶۰۰ پیکسل عرض) مناسب است.', 'lylyrose' ),
		) ) );
	}
}
add_action( 'customize_register', 'lylyrose_customize_register' );
/**
 * Mega-menu cells for the "دسته‌بندی کالا" panel: one cell per top-level
 * category, each carrying its own subcategories when it has any.
 *
 * The tree is mostly flat — only مراقبت از پوست and مراقبت از مو have children —
 * so a cell is usually just its category name. Brands are not repeated per
 * category here: the category archive a visitor lands on already carries the
 * brand chips, so a second copy in the nav would be 13 near-identical columns.
 *
 * Returns a list of array( 'name', 'url', 'links' ), where 'links' is a list
 * of array( 'name', 'url' ).
 */
function lylyrose_mega_cats_menu( $terms ) {
	$cells = array();

	foreach ( (array) $terms as $term ) {
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}

		$links = array();
		$children = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => $term->term_id,
			'orderby'    => 'count',
			'order'      => 'DESC',
		) );
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child ) {
				$child_url = get_term_link( $child );
				if ( is_wp_error( $child_url ) ) {
					continue;
				}
				$links[] = array( 'name' => $child->name, 'url' => $child_url );
			}
		}

		$cells[] = array(
			'name'  => $term->name,
			'url'   => $url,
			'links' => $links,
		);
	}

	return $cells;
}

/**
 * Convert Latin digits to Persian digits.
 */
function lylyrose_to_persian_digits( $text ) {
    return str_replace(
        array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
        array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
        (string) $text
    );
}

/**
 * Prices are whole تومان, so no decimal part is ever meaningful. WooCommerce
 * defaults to 2 and renders "۹,۲۰۰,۰۰۰.۰۰".
 *
 * Scoped to wc_price's display args on purpose. The store option stays at 2
 * because wc_get_price_decimals() also feeds tax, shipping and refund
 * arithmetic, where the precision is real and rounding it away would change
 * the totals WooCommerce computes.
 *
 * Also not 'woocommerce_price_trim_zeros': that only strips trailing zeros, so
 * a price that genuinely lands on .5 would still show one digit.
 */
function lylyrose_price_args( $args ) {
    $args['decimals'] = 0;
    return $args;
}
add_filter( 'wc_price_args', 'lylyrose_price_args', 100 );

/**
 * Persian digits for prices — fa (RTL) only, so EN keeps Latin numerals.
 */
function lylyrose_persian_price( $html ) {
    return is_rtl() ? lylyrose_to_persian_digits( $html ) : $html;
}
add_filter( 'wc_price', 'lylyrose_persian_price', 100 );

/**
 * Convert cart totals and order-review numbers to Persian digits.
 */
function lylyrose_persian_formatted_price( $formatted_price ) {
    return is_rtl() ? lylyrose_to_persian_digits( $formatted_price ) : $formatted_price;
}
add_filter( 'formatted_woocommerce_price', 'lylyrose_persian_formatted_price', 100 );

/**
 * Persian digits for pagination and review counts.
 */
function lylyrose_persian_i18n_number( $formatted_number ) {
    return is_rtl() ? lylyrose_to_persian_digits( $formatted_number ) : $formatted_number;
}
add_filter( 'number_format_i18n', 'lylyrose_persian_i18n_number', 1000 );

/**
 * Body classes
 */
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'lylyrose-theme';
    if ( is_rtl() ) {
        $classes[] = 'rtl';
    }
    return $classes;
} );

add_filter( 'excerpt_length', function() { return 25; }, 999 );

/**
 * Flash sales (incredible offers) assets — only on the offers page.
 * Served from the theme so they version-bump with theme updates.
 */
function lylyrose_flash_sales_assets() {
    if ( ! function_exists( 'is_incredible_offers' ) || ! is_incredible_offers() ) {
        return;
    }
    wp_enqueue_script(
        'lylyrose-flash-sales',
        get_theme_file_uri( 'assets/js/flash-sales.js' ),
        array(),
        lylyrose_version(),
        true
    );
    wp_localize_script( 'lylyrose-flash-sales', 'dk_flash_i18n', array(
        'noStock'   => __( 'اتمام موجودی', 'lylyrose' ),
        'addedToCart' => __( 'افزوده شد', 'lylyrose' ),
    ) );
    wp_enqueue_style(
        'lylyrose-flash-sales',
        get_theme_file_uri( 'assets/css/flash-sales.css' ),
        array(),
        lylyrose_version()
    );
}
add_action( 'wp_enqueue_scripts', 'lylyrose_flash_sales_assets', 20 );
