<?php
/**
 * Gift wrap service (بسته‌بندی هدیه).
 *
 * Adds an optional gift-wrap fee to the cart/checkout.
 * State stored in WC session (guest) or cookie (logged-in).
 * Fee persists as order line item via WC CRUD (HPOS-safe).
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ASC_Gift_Wrap {

    const FEE_AMOUNT   = 50000; // 50,000 تومان
    private static function fee_label() {
        return __( 'بسته‌بندی هدیه', 'lylyrose-core' );
    }
    const SESSION_KEY  = 'asc_gift_wrap';
    const COOKIE_NAME  = 'asc_gift_wrap';

    public function __construct() {
        // Cart fee calculation
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_fee' ) );

        // Ensure the fee item remains correctly represented during checkout.
        add_action( 'woocommerce_checkout_create_order_fee_item', array( $this, 'persist_fee_item' ), 10, 4 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'ensure_order_fee' ), 20, 2 );

        // Cart page checkbox
        add_action( 'woocommerce_before_cart_table', array( $this, 'render_cart_checkbox' ) );

        // Checkout step 1 status + toggle
        add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_checkout_status' ) );

        // AJAX toggle
        add_action( 'wp_ajax_asc_gift_wrap_toggle', array( $this, 'ajax_toggle' ) );
        add_action( 'wp_ajax_nopriv_asc_gift_wrap_toggle', array( $this, 'ajax_toggle' ) );

        // Persist via checkout form POST (no-JS fallback)
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'maybe_update_from_checkout' ) );
    }

    /**
     * Add gift-wrap fee to cart.
     *
     * @param WC_Cart $cart
     */
    public function add_fee( $cart ) {
        if ( $this->is_enabled() ) {
            $cart->add_fee( self::fee_label(), self::FEE_AMOUNT, true, '' );
        }
    }

    /**
     * Persist gift-wrap as order fee line item (HPOS-safe via WC CRUD).
     *
     * @param WC_Order $order
     * @param array    $data
     */
    public function persist_fee_item( $item, $fee_key, $fee, $order ) {
        if ( self::fee_label() === $item->get_name() ) {
            $item->set_amount( self::FEE_AMOUNT );
            $item->set_total( self::FEE_AMOUNT );
        }
    }

    /**
     * Preserve the fee when a checkout session is rebuilt during submission.
     *
     * @param WC_Order $order Order being created.
     * @param array    $data Checkout data.
     */
    public function ensure_order_fee( $order, $data ) {
        if ( ! $this->is_enabled() ) {
            return;
        }

        foreach ( $order->get_items( 'fee' ) as $item ) {
            if ( self::fee_label() === $item->get_name() ) {
                return;
            }
        }

        $item = new WC_Order_Item_Fee();
        $item->set_name( self::fee_label() );
        $item->set_amount( self::FEE_AMOUNT );
        $item->set_tax_class( '' );
        $item->set_tax_status( 'none' );
        $item->set_total( self::FEE_AMOUNT );
        $order->add_item( $item );
    }

    /**
     * Render gift-wrap checkbox on cart page.
     * Injected at woocommerce_before_cart_table.
     */
    public function render_cart_checkbox() {
        $checked = $this->is_enabled() ? 'checked' : '';
        $nonce   = wp_create_nonce( 'asc_gift_wrap' );
        ?>
        <div class="dk-gift-wrap dk-cart-gift-wrap">
            <label class="dk-gift-wrap-label">
                <input type="checkbox" name="asc_gift_wrap" value="1" <?php echo $checked; ?>
                       data-nonce="<?php echo esc_attr( $nonce ); ?>" class="dk-gift-wrap-toggle" />
                <span class="dk-gift-wrap-text"><?php printf( esc_html__( 'افزودن بسته‌بندی هدیه (%s)', 'lylyrose-core' ), wp_kses_post( wc_price( self::FEE_AMOUNT ) ) ); ?></span>
            </label>
        </div>
        <?php
    }

    /**
     * Render gift-wrap status + toggle in checkout step 1.
     * Injected at woocommerce_review_order_before_payment.
     */
    public function render_checkout_status() {
        $enabled = $this->is_enabled();
        $nonce   = wp_create_nonce( 'asc_gift_wrap' );
        ?>
        <div class="dk-gift-wrap dk-checkout-gift-wrap">
            <label class="dk-gift-wrap-label">
                <input type="checkbox" name="asc_gift_wrap" value="1" <?php echo $enabled ? 'checked' : ''; ?>
                       data-nonce="<?php echo esc_attr( $nonce ); ?>" class="dk-gift-wrap-toggle" />
                <span class="dk-gift-wrap-text"><?php printf( esc_html__( 'بسته‌بندی هدیه (%s)', 'lylyrose-core' ), wp_kses_post( wc_price( self::FEE_AMOUNT ) ) ); ?></span>
            </label>
            <input type="hidden" name="asc_gift_wrap_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
        </div>
        <?php
    }

    /**
     * AJAX toggle handler (guest + logged-in).
     */
    public function ajax_toggle() {
        check_ajax_referer( 'asc_gift_wrap', 'nonce' );
        $enable = isset( $_POST['enable'] ) && $_POST['enable'] === 'true';
        WC()->session->set( self::SESSION_KEY, $enable );
        WC()->cart->calculate_totals();
        wp_send_json_success( array(
            'enabled'  => $enable,
            'fee_html' => wc_price( self::FEE_AMOUNT ),
        ) );
    }

    /**
     * Update session from checkout form POST (no-JS fallback).
     *
     * @param int $order_id
     */
    public function maybe_update_from_checkout( $order_id ) {
        if ( ! empty( $_POST['asc_gift_wrap'] ) && wp_verify_nonce( $_POST['asc_gift_wrap_nonce'] ?? '', 'asc_gift_wrap' ) ) {
            WC()->session->set( self::SESSION_KEY, true );
        }
    }

    /**
     * Check if gift wrap is enabled for current cart.
     *
     * Priority: WC session → cookie → checkout form POST.
     *
     * @return bool
     */
    private function is_enabled(): bool {
        // 1. WC session (set via AJAX or checkout POST)
        if ( WC()->session && WC()->session->get( self::SESSION_KEY ) ) {
            return true;
        }

        // 2. Cookie fallback (for logged-in users without session)
        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && $_COOKIE[ self::COOKIE_NAME ] === '1' ) {
            return true;
        }

        // 3. Checkout form POST (no-JS fallback)
        if ( ! empty( $_POST['asc_gift_wrap'] ) && wp_verify_nonce( $_POST['asc_gift_wrap_nonce'] ?? '', 'asc_gift_wrap' ) ) {
            return true;
        }

        return false;
    }
}