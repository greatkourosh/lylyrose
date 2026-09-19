<?php
/**
 * Plugin Name: Lyly Rose Core
 * Plugin URI: http://localhost:8080
 * Description: Core functionality for Lyly Rose - Custom product attributes, taxonomies, and WooCommerce extensions
 * Author: Lyly Rose Team
 * Version: 2.3.0
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lylyrose_Core {

    const VERSION = '2.3.0';
    const SLUG    = 'lylyrose-core';

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
            self::$instance->setup_constants();
            self::$instance->includes();
            self::$instance->hooks();
        }
        return self::$instance;
    }

    protected function setup_constants() {
        define( 'LYLYROSE_CORE_VERSION', self::VERSION );
        define( 'LYLYROSE_CORE_PLUGIN_FILE', __FILE__ );
        define( 'LYLYROSE_CORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'LYLYROSE_CORE_PLUGIN_URL', plugins_url( '', __FILE__ ) );
    }

    protected function includes() {
        if ( is_admin() ) {
            $this->includes_admin();
        }
        $this->includes_frontend();
        if ( class_exists( 'WooCommerce' ) ) {
            $this->includes_woocommerce();
        }
    }

    protected function includes_admin() {
    }

    protected function includes_frontend() {
        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-otp.php';
        new ASC_OTP();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-cart-abandonment.php';
        new ASC_Cart_Abandonment();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-notifications.php';
        ASC_Notifications::instance();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-gift-wrap.php';
        new ASC_Gift_Wrap();
    }

    protected function includes_woocommerce() {
        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-national-id.php';
        new ASC_National_ID();
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_national_id' ), 10, 2 );

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-product-code.php';
        ASC_Product_Code::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-store-pages.php';
        ASC_Store_Pages::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-emails.php';
        ASC_Emails::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-images.php';
        ASC_Images::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-coupons.php';
        ASC_Coupons::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-frequently-bought.php';
        ASC_Frequently_Bought::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-instagram.php';
        ASC_Instagram::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-reports.php';
        ASC_Reports::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-reviews.php';
        ASC_Reviews::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-fragrance-notes.php';
        ASC_Fragrance_Notes::init();

        require_once LYLYROSE_CORE_PLUGIN_DIR . 'includes/class-stock-notifier.php';
        ASC_Stock_Notifier::init();
    }

    /**
     * Validate Iranian national ID checksum at checkout.
     */
    public function validate_national_id( $data, $errors ) {
        if ( ! empty( $data['billing_national_id'] ) && ! ASC_National_ID::is_valid( $data['billing_national_id'] ) ) {
            $errors->add( 'billing_national_id', __( 'کد ملی وارد شده معتبر نیست.', 'lylyrose-core' ) );
        }
    }

    protected function hooks() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        add_action( 'admin_init', array( $this, 'register_product_attributes' ) );
    }

    /**
     * Load the plugin text domain so UI strings are translatable.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'lylyrose-core', false, dirname( plugin_basename( LYLYROSE_CORE_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Register custom product taxonomies for perfume/fragrance.
     */
    public function register_taxonomies() {
        $taxonomies = array(
            'pa_brand'             => array( __( 'برند', 'lylyrose-core' ), __( 'برند', 'lylyrose-core' ), __( 'برندها', 'lylyrose-core' ), __( 'برند', 'lylyrose-core' ) ),
            'pa_gender'            => array( __( 'جنسیت', 'lylyrose-core' ), __( 'جنسیت', 'lylyrose-core' ), __( 'جنسیت‌ها', 'lylyrose-core' ), __( 'جنسیت', 'lylyrose-core' ) ),
            'pa_concentration'     => array( __( 'غلظت', 'lylyrose-core' ), __( 'غلظت', 'lylyrose-core' ), __( 'غلظت‌ها', 'lylyrose-core' ), __( 'غلظت', 'lylyrose-core' ) ),
            'pa_volume'            => array( __( 'حجم', 'lylyrose-core' ), __( 'حجم', 'lylyrose-core' ), __( 'حجم‌ها', 'lylyrose-core' ), __( 'حجم', 'lylyrose-core' ) ),
            'pa_fragrance_family'  => array( __( 'خانواده عطر', 'lylyrose-core' ), __( 'خانواده عطر', 'lylyrose-core' ), __( 'خانواده‌های عطر', 'lylyrose-core' ), __( 'خانواده عطر', 'lylyrose-core' ) ),
            'pa_season'            => array( __( 'فصل', 'lylyrose-core' ), __( 'فصل', 'lylyrose-core' ), __( 'فصل‌ها', 'lylyrose-core' ), __( 'فصل', 'lylyrose-core' ) ),
            'pa_occasion'          => array( __( 'مناسبت', 'lylyrose-core' ), __( 'مناسبت', 'lylyrose-core' ), __( 'مناسبت‌ها', 'lylyrose-core' ), __( 'مناسبت', 'lylyrose-core' ) ),
            'pa_longevity'         => array( __( 'ماندگاری', 'lylyrose-core' ), __( 'ماندگاری', 'lylyrose-core' ), __( 'ماندگاری‌ها', 'lylyrose-core' ), __( 'ماندگاری', 'lylyrose-core' ) ),
            'pa_sillage'           => array( __( 'پخش بو', 'lylyrose-core' ), __( 'پخش بو', 'lylyrose-core' ), __( 'پخش بو', 'lylyrose-core' ), __( 'پخش بو', 'lylyrose-core' ) ),
        );

        foreach ( $taxonomies as $tax => $labels ) {
            if ( taxonomy_exists( $tax ) ) {
                continue;
            }

            $args = array(
                'hierarchical'      => true,
                'labels'            => array(
                    'name'          => $labels[0],
                    'singular_name' => $labels[1],
                    'search_items'  => sprintf( __( 'جستجوی %s', 'lylyrose-core' ), $labels[2] ),
                    'all_items'     => sprintf( __( 'همه %s', 'lylyrose-core' ), $labels[2] ),
                    'parent_item'   => $labels[0],
                    'parent_item_colon' => $labels[0] . ':',
                    'edit_item'     => sprintf( __( 'ویرایش %s', 'lylyrose-core' ), $labels[0] ),
                    'update_item'   => sprintf( __( 'به‌روزرسانی %s', 'lylyrose-core' ), $labels[0] ),
                    'add_new_item'  => sprintf( __( 'افزودن %s', 'lylyrose-core' ), $labels[1] ),
                    'new_item_name' => sprintf( __( '%s جدید', 'lylyrose-core' ), $labels[1] ),
                    'menu_name'     => $labels[2],
                ),
                'show_ui'           => true,
                'show_admin_column' => true,
                'query_var'         => true,
                'rewrite'           => array( 'slug' => $tax ),
            );

            register_taxonomy( $tax, array( 'product' ), $args );
        }
    }

    /**
     * Register product attributes in WooCommerce.
     */
    public function register_product_attributes() {
        $attribute_labels = array(
            'brand'             => __( 'برند', 'lylyrose-core' ),
            'gender'            => __( 'جنسیت', 'lylyrose-core' ),
            'concentration'     => __( 'غلظت', 'lylyrose-core' ),
            'volume'            => __( 'حجم', 'lylyrose-core' ),
            'fragrance_family'  => __( 'خانواده عطر', 'lylyrose-core' ),
            'season'            => __( 'فصل', 'lylyrose-core' ),
            'occasion'          => __( 'مناسبت', 'lylyrose-core' ),
            'longevity'         => __( 'ماندگاری', 'lylyrose-core' ),
            'sillage'           => __( 'پخش بو', 'lylyrose-core' ),
        );

        global $wpdb;
        foreach ( $attribute_labels as $key => $label ) {
            $attribute_name = 'pa_' . $key;
            if ( taxonomy_exists( $attribute_name ) ) {
                continue;
            }
            $attribute_id = wc_create_attribute( array(
                'name'   => $label,
                'slug'   => $key,
                'type'   => 'select',
                'order_by' => 'menu_order',
            ) );

            if ( is_wp_error( $attribute_id ) ) {
                continue;
            }
        }
    }
}

function lylyrose_core_init() {
    return Lylyrose_Core::instance();
}
add_action( 'plugins_loaded', 'lylyrose_core_init', 20 );

/**
 * Keep checkout reachable with an empty cart (WooCommerce otherwise 302s to /cart/).
 * Runs late so it wins over any plugin re-enabling the redirect.
 */
function lylyrose_core_allow_empty_cart_checkout() {
    add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false', 9999 );
}
add_action( 'plugins_loaded', 'lylyrose_core_allow_empty_cart_checkout', 99 );

/**
 * Dokan vendor-own-product restriction neutralizer for a single-seller store.
 *
 * Products were imported with post_author = 0 (no real WP user). Dokan's
 * `dokan_vendor_own_product_purchase_restriction` then compares that author to
 * `dokan_get_current_user_id()` — with author 0 this matches the guest (0) and
 * force-marks every product non-purchasable, breaking add-to-cart. Filtering
 * `dokan_is_product_author` keeps the store's own products purchasable while
 * leaving real per-vendor rules intact.
 */
function lylyrose_core_dokan_fix() {
    if ( ! function_exists( 'dokan_is_product_author' ) ) {
        return;
    }
    add_filter( 'dokan_is_product_author', function( $user_id, $product_id ) {
        if ( (int) get_post_field( 'post_author', $product_id ) === 0 ) {
            return -1;
        }
        return $user_id;
    }, 10, 2 );
}
add_action( 'plugins_loaded', 'lylyrose_core_dokan_fix', 25 );
