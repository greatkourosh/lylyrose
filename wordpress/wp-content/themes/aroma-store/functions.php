<?php
/**
 * Aroma Store Theme Functions
 *
 * @package Aroma_Store
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme setup
 */
function aroma_store_setup() {
    // Add theme support
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'custom-background' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'responsive-embeds' );

    // WooCommerce support
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );

    // RTL support
    add_theme_support( 'rtl' );

    // Register navigation menus
    register_nav_menus( array(
        'primary' => esc_html__( 'Primary Menu', 'aroma-store' ),
        'footer'  => esc_html__( 'Footer Menu', 'aroma-store' ),
    ) );

    // Add editor styles
    add_editor_style( 'assets/css/editor-style.css' );

    // Remove WP block library styles that conflict
    add_filter( 'should_load_separate_core_block_assets', '__return_false' );
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_registered_block_scripts_and_styles' );

    // Disable emoji styles
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
}

add_action( 'after_setup_theme', 'aroma_store_setup' );

/**
 * Enqueue scripts and styles
 */
function aroma_store_scripts() {
    // Theme stylesheet - use get_template_directory_uri() for child theme compatibility
    wp_enqueue_style( 'aroma-store-style', get_template_directory_uri() . '/style.css', array(), '2.0.0' );

    // RTL styles
    if ( is_rtl() ) {
        wp_enqueue_style( 'aroma-store-rtl', get_template_directory_uri() . '/style.css', array( 'aroma-store-style' ), '2.0.0' );
    }

    // Enqueue the optional frontend script only when the file exists. This
    // prevents missing assets from falling through to WordPress routing.
    $script_path = get_template_directory() . '/assets/js/main.js';
    if ( file_exists( $script_path ) ) {
        wp_enqueue_script( 'aroma-store-script', get_template_directory_uri() . '/assets/js/main.js', array( 'jquery' ), '1.0.0', true );

        wp_localize_script( 'aroma-store-script', 'aromaStore', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'aroma-store-nonce' ),
            'cart_url' => wc_get_cart_url(),
            'checkout_url' => wc_get_checkout_url(),
        ) );
    }

    // Comment reply
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}

add_action( 'wp_enqueue_scripts', 'aroma_store_scripts' );

/**
 * Register widget areas
 */
function aroma_store_widgets_init() {
    register_sidebar( array(
        'name'          => esc_html__( 'Sidebar', 'aroma-store' ),
        'id'            => 'sidebar-1',
        'description'   => esc_html__( 'Add widgets here.', 'aroma-store' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'Footer Widget Area', 'aroma-store' ),
        'id'            => 'footer-widgets',
        'description'   => esc_html__( 'Add widgets here.', 'aroma-store' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}

add_action( 'widgets_init', 'aroma_store_widgets_init' );

/**
 * WooCommerce customizations
 */

// Remove default WooCommerce styles
add_filter( 'woocommerce_enqueue_styles', '__return_false' );

// Adjust number of products per row
add_filter( 'loop_shop_columns', function() { return 4; }, 999 );

// Adjust related products args
add_filter( 'woocommerce_output_related_products_args', function( $args ) {
    $args['posts_per_page'] = 4;
    $args['columns'] = 4;
    return $args;
} );

// Custom product badge
add_action( 'woocommerce_before_single_product_summary', 'aroma_store_product_badge', 5 );
function aroma_store_product_badge() {
    global $product;
    if ( $product->is_on_sale() ) {
        echo '<span class="aroma-badge aroma-badge-sale">' . esc_html__( 'تخفیف', 'aroma-store' ) . '</span>';
    }
    if ( $product->is_in_stock() && $product->get_stock_quantity() <= 5 ) {
        echo '<span class="aroma-badge aroma-badge-low-stock">' . esc_html__( 'محدود', 'aroma-store' ) . '</span>';
    }
}

/**
 * Currency formatting for Iranian Toman
 */
add_filter( 'woocommerce_currency_symbol', function( $currency_symbol, $currency ) {
    if ( $currency === 'IRR' ) {
        return 'تومان';
    }
    return $currency_symbol;
}, 10, 2 );

add_filter( 'woocommerce_price_trim_zeros', '__return_true' );

/**
 * Add Persian/Arabic number formatting
 */
add_filter( 'woocommerce_price_format', function( $format ) {
    return '<span class="woocommerce-Price-amount amount">%1$s&nbsp;<span class="woocommerce-Price-currencySymbol">%2$s</span></span>';
} );

/**
 * Add custom product tabs
 */
add_filter( 'woocommerce_product_tabs', 'aroma_store_product_tabs' );
function aroma_store_product_tabs( $tabs ) {
    // Add Fragrance Notes tab
    $tabs['fragrance_notes'] = array(
        'title'    => __( 'یادداشت‌های عطر', 'aroma-store' ),
        'priority' => 20,
        'callback' => 'aroma_store_fragrance_notes_tab',
    );

    // Add Specifications tab
    $tabs['specifications'] = array(
        'title'    => __( 'مشخصات', 'aroma-store' ),
        'priority' => 30,
        'callback' => 'aroma_store_specifications_tab',
    );

    return $tabs;
}

function aroma_store_fragrance_notes_tab() {
    global $product;
    $top_notes    = get_post_meta( $product->get_id(), '_top_notes', true );
    $heart_notes  = get_post_meta( $product->get_id(), '_heart_notes', true );
    $base_notes   = get_post_meta( $product->get_id(), '_base_notes', true );

    if ( ! $top_notes && ! $heart_notes && ! $base_notes ) {
        return;
    }

    echo '<div class="fragrance-pyramid">';

    if ( $top_notes ) {
        echo '<div class="notes-group"><h4>' . esc_html__( 'یادداشت‌های بالا', 'aroma-store' ) . '</h4>';
        echo '<ul class="notes-list">' . wp_kses_post( $top_notes ) . '</ul></div>';
    }

    if ( $heart_notes ) {
        echo '<div class="notes-group"><h4>' . esc_html__( 'یادداشت‌های میانی', 'aroma-store' ) . '</h4>';
        echo '<ul class="notes-list">' . wp_kses_post( $heart_notes ) . '</ul></div>';
    }

    if ( $base_notes ) {
        echo '<div class="notes-group"><h4>' . esc_html__( 'یادداشت‌های پایه', 'aroma-store' ) . '</h4>';
        echo '<ul class="notes-list">' . wp_kses_post( $base_notes ) . '</ul></div>';
    }

    echo '</div>';
}

function aroma_store_specifications_tab() {
    global $product;

    $attributes = array(
        'pa_brand'         => __( 'برند', 'aroma-store' ),
        'pa_gender'        => __( 'جنسیت', 'aroma-store' ),
        'pa_concentration' => __( 'غلظت', 'aroma-store' ),
        'pa_volume'        => __( 'حجم', 'aroma-store' ),
        'pa_fragrance_family' => __( 'خانواده عطر', 'aroma-store' ),
        'pa_season'        => __( 'فصل', 'aroma-store' ),
        'pa_occasion'      => __( 'مناسبت', 'aroma-store' ),
        'pa_longevity'     => __( 'ماندگاری', 'aroma-store' ),
        'pa_sillage'       => __( 'استقرار', 'aroma-store' ),
    );

    $has_attrs = false;
    foreach ( $attributes as $tax => $label ) {
        $terms = wc_get_product_terms( $product->get_id(), $tax, array( 'fields' => 'names' ) );
        if ( $terms ) {
            $has_attrs = true;
            break;
        }
    }

    if ( ! $has_attrs ) {
        return;
    }

    echo '<table class="specifications-table">';
    echo '<thead><tr><th>' . esc_html__( 'مشخصه', 'aroma-store' ) . '</th><th>' . esc_html__( 'مقدار', 'aroma-store' ) . '</th></tr></thead>';
    echo '<tbody>';

    foreach ( $attributes as $tax => $label ) {
        $terms = wc_get_product_terms( $product->get_id(), $tax, array( 'fields' => 'names' ) );
        if ( $terms ) {
            echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( implode( ', ', $terms ) ) . '</td></tr>';
        }
    }

    echo '</tbody></table>';
}

/**
 * Add custom product meta fields
 */
add_action( 'woocommerce_product_options_general_product_data', 'aroma_store_product_custom_fields' );
function aroma_store_product_custom_fields() {
    global $post;

    echo '<div class="options_group">';

    woocommerce_wp_textarea_input( array(
        'id'          => '_top_notes',
        'label'       => __( 'یادداشت‌های بالا (Top Notes)', 'aroma-store' ),
        'placeholder' => 'برگاموت، لیمو، سیب',
        'desc_tip'    => 'true',
        'description' => __( 'یادداشت‌های اولیه عطر', 'aroma-store' ),
    ) );

    woocommerce_wp_textarea_input( array(
        'id'          => '_heart_notes',
        'label'       => __( 'یادداشت‌های میانی (Heart Notes)', 'aroma-store' ),
        'placeholder' => 'گل رز، یاسمن، لوند',
        'desc_tip'    => 'true',
        'description' => __( 'یادداشت‌های قلب عطر', 'aroma-store' ),
    ) );

    woocommerce_wp_textarea_input( array(
        'id'          => '_base_notes',
        'label'       => __( 'یادداشت‌های پایه (Base Notes)', 'aroma-store' ),
        'placeholder' => 'عنبر، مشک، وانیل، سيدر',
        'desc_tip'    => 'true',
        'description' => __( 'یادداشت‌های پایانی عطر', 'aroma-store' ),
    ) );

    woocommerce_wp_text_input( array(
        'id'          => '_longevity',
        'label'       => __( 'ماندگاری', 'aroma-store' ),
        'placeholder' => 'طولانی / متوسط / کوتاه',
        'desc_tip'    => 'true',
        'description' => __( 'مدت زمان ماندن عطر روی پوست', 'aroma-store' ),
    ) );

    woocommerce_wp_text_input( array(
        'id'          => '_sillage',
        'label'       => __( 'استقرار (Sillage)', 'aroma-store' ) . ' - ' . __( 'شدت انتشار عطر', 'aroma-store' ),
        'placeholder' => 'قوی / متوسط / ضعیف',
        'desc_tip'    => 'true',
        'description' => __( 'شدت انتشار عطر در فضای اطراف', 'aroma-store' ),
    ) );

    echo '</div>';
}

/**
 * Save custom product meta fields
 */
add_action( 'woocommerce_process_product_meta', 'aroma_store_save_product_custom_fields' );
function aroma_store_save_product_custom_fields( $post_id ) {
    $fields = array( '_top_notes', '_heart_notes', '_base_notes', '_longevity', '_sillage' );

    foreach ( $fields as $field ) {
        $value = isset( $_POST[ $field ] ) ? sanitize_textarea_field( $_POST[ $field ] ) : '';
        update_post_meta( $post_id, $field, $value );
    }
}

/**
 * RTL text direction
 */
add_filter( 'locale_stylesheet_dir', function( $dir ) {
    if ( is_rtl() ) {
        return get_template_directory() . '/rtl.css';
    }
    return $dir;
} );

/**
 * Login page logo
 */
add_action( 'login_enqueue_scripts', function() {
    echo '<style type="text/css">
        #login h1 a { background-image: url(' . get_template_directory_uri() . '/assets/images/logo.png); background-size: contain; width: 100%; height: 100px; }
        body.login { direction: rtl; text-align: right; }
    </style>';
} );

/**
 * Add body classes for Persian context
 */
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'aroma-store';
    $classes[] = 'persian';
    if ( is_rtl() ) {
        $classes[] = 'rtl';
    }
    return $classes;
} );

/**
 * Custom excerpt length
 */
add_filter( 'excerpt_length', function() {
    return 25;
}, 999 );

/**
 * Add Persian date support
 */
add_filter( 'date_i18n', function( $date, $format, $timestamp, $gmt ) {
    if ( ! $gmt && function_exists( 'jdate' ) ) {
        return jdate( $format, $timestamp );
    }
    return $date;
}, 10, 4 );

/**
 * Entry footer
 */
if ( ! function_exists( 'aroma_store_entry_footer' ) ) {
    function aroma_store_entry_footer() {
        // translators: used between list items, there is a space after the comma.
        $tag_list = get_the_tag_list( '', esc_html__( ', ', 'aroma-store' ) );
        if ( $tag_list ) {
            printf( '<span class="tags-links">%s</span>', $tag_list ); // WPCS: XSS OK.
        }
    }
}
/**
 * Check if user can manage the shop
 */
if ( ! function_exists( 'owns_shop' ) ) {
    function owns_shop() {
        if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }
        return false;
    }
}
