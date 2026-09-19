<?php
/**
 * Digikala Theme Functions — v1.0.0 snapshot
 *
 * Frozen copy of the "digikala" theme at version 1.0.0, kept for rollback.
 * Do not develop against this folder; all changes go into themes/digikala.
 *
 * @package Digikala
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme setup
 */
function digikala_setup() {
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
        'primary' => esc_html__( 'منوی اصلی', 'digikala' ),
        'footer'  => esc_html__( 'منوی فوتر', 'digikala' ),
    ) );

    // Avoid block library CSS conflicts
    add_filter( 'should_load_separate_core_block_assets', '__return_false' );
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_registered_block_scripts_and_styles' );

    // Disable emoji scripts
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
}
add_action( 'after_setup_theme', 'digikala_setup' );

/**
 * Scripts & styles
 */
function digikala_scripts() {
    // IRANYekan is Digikala's actual font; Vazirmatn is the free fallback.
    wp_enqueue_style( 'digikala-font-vazirmatn', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css', array(), '33.003' );
    wp_enqueue_style( 'digikala-style', get_stylesheet_uri(), array( 'digikala-font-vazirmatn' ), '1.0.7' );

    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'digikala_scripts' );

/**
 * Widget areas
 */
function digikala_widgets_init() {
    register_sidebar( array(
        'name'          => esc_html__( 'ستون کناری', 'digikala' ),
        'id'            => 'sidebar-1',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'ویجت‌های فوتر', 'digikala' ),
        'id'            => 'footer-widgets',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'digikala_widgets_init' );

/**
 * WooCommerce tweaks
 */
add_filter( 'woocommerce_enqueue_styles', '__return_false' );

add_filter( 'loop_shop_columns', function() { return 3; }, 999 );

add_filter( 'woocommerce_output_related_products_args', function( $args ) {
    $args['posts_per_page'] = 4;
    $args['columns'] = 4;
    return $args;
} );

/**
 * Shop/category filters (URL-driven GET params, Digikala-style):
 * sort, price_min, price_max, in_stock, discount, brands[].
 */
function digikala_shop_filters( $q ) {
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
            // Percent is computed in SQL from price meta; see digikala_discount_sort_orderby().
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

    // Brand filter (pa_brand terms).
    $brands = isset( $_GET["dk_brands"] ) ? array_map( 'absint', (array) $_GET["dk_brands"] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $brands = array_filter( $brands );
    if ( $brands ) {
        $tax_query = (array) $q->get( 'tax_query' );
        $tax_query[] = array(
            'taxonomy' => 'pa_brand',
            'field'    => 'term_id',
            'terms'    => $brands,
        );
        $q->set( 'tax_query', $tax_query );
    }
}
add_action( 'pre_get_posts', 'digikala_shop_filters', 20 );

/**
 * ORDER BY discount percent for sort=discount: (regular-sale)/regular DESC.
 * Joins regular + sale price meta and sorts on the computed percent.
 */
function digikala_discount_sort_join( $join, $q ) {
    if ( ! $q->get( 'asc_discount_sort' ) ) {
        return $join;
    }
    global $wpdb;
    $join .= " LEFT JOIN {$wpdb->postmeta} AS dk_reg ON dk_reg.post_id = {$wpdb->posts}.ID AND dk_reg.meta_key = '_regular_price'
               LEFT JOIN {$wpdb->postmeta} AS dk_sale ON dk_sale.post_id = {$wpdb->posts}.ID AND dk_sale.meta_key = '_sale_price'";
    return $join;
}
add_filter( 'posts_join', 'digikala_discount_sort_join', 10, 2 );

function digikala_discount_sort_orderby( $orderby, $q ) {
    if ( ! $q->get( 'asc_discount_sort' ) ) {
        return $orderby;
    }
    // In-stock products first, then biggest percent first.
    return "(dk_sale.meta_value > 0) * ( ( CAST(dk_reg.meta_value AS UNSIGNED) - CAST(dk_sale.meta_value AS UNSIGNED) ) / GREATEST(CAST(dk_reg.meta_value AS UNSIGNED),1) ) DESC, post_date DESC";
}
add_filter( 'posts_orderby', 'digikala_discount_sort_orderby', 10, 2 );

// Persian digits for prices when a Persian-numbers helper exists.
if ( ! function_exists( 'digikala_fa_num' ) ) {
    function digikala_fa_num( $str ) {
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
function digikala_discount_percent( $product ) {
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
 */
function digikala_term_image( $term, $size = 'woocommerce_thumbnail' ) {
    $cache_key = 'digikala_term_img_' . $term->term_id . '_' . $size;
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

    set_transient( $cache_key, $url ? $url : '0', 12 * HOUR_IN_SECONDS );
    return $url;
}

/**
 * Convert Latin digits to Persian digits.
 */
function digikala_to_persian_digits( $text ) {
    return str_replace(
        array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
        array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
        (string) $text
    );
}

/**
 * Render WooCommerce prices with Persian digits.
 */
function digikala_persian_price( $html ) {
    return digikala_to_persian_digits( $html );
}
add_filter( 'wc_price', 'digikala_persian_price', 100 );

/**
 * Convert cart totals and order-review numbers to Persian digits.
 */
function digikala_persian_formatted_price( $formatted_price ) {
    return digikala_to_persian_digits( $formatted_price );
}
add_filter( 'formatted_woocommerce_price', 'digikala_persian_formatted_price', 100 );

/**
 * Persian digits for pagination and review counts.
 */
function digikala_persian_i18n_number( $formatted_number ) {
    return digikala_to_persian_digits( $formatted_number );
}
add_filter( 'number_format_i18n', 'digikala_persian_i18n_number', 1000 );

/**
 * Body classes
 */
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'digikala-theme';
    if ( is_rtl() ) {
        $classes[] = 'rtl';
    }
    return $classes;
} );

add_filter( 'excerpt_length', function() { return 25; }, 999 );
