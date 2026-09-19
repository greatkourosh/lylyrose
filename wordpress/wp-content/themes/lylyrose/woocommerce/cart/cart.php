<?php
/**
 * Cart — Digikala-style: stepper, product cards, payment summary sidebar.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
?>
<div class="dk-cart-page">
    <div class="dk-container">
        <div class="dk-cart-stepper" aria-hidden="true">
            <span class="dk-step is-active"><?php esc_html_e( '۱. سبد خرید', 'lylyrose' ); ?></span>
            <span class="dk-step-sep"></span>
            <span class="dk-step"><?php esc_html_e( '۲. تسویه حساب', 'lylyrose' ); ?></span>
            <span class="dk-step-sep"></span>
            <span class="dk-step"><?php esc_html_e( '۳. تکمیل سفارش', 'lylyrose' ); ?></span>
        </div>

        <h1 class="dk-cart-title"><?php esc_html_e( 'سبد خرید', 'lylyrose' ); ?></h1>

        <?php if ( WC()->cart->get_cart_contents_count() > 0 ) : ?>
        <div class="dk-cart-layout">
            <div class="dk-cart-main">
                <form class="cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
                    <?php do_action( 'woocommerce_before_cart_table' ); ?>

                    <div class="dk-cart-card">
                        <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                            if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
                                continue;
                            }
                            $dk_discount = function_exists( 'lylyrose_discount_percent' ) ? lylyrose_discount_percent( $_product ) : 0;
                            ?>
                            <div class="dk-cart-item">
                                <div class="dk-cart-item-thumb">
                                    <?php
                                    $thumb = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
                                    echo $_product->is_visible() ? '<a href="' . esc_url( $_product->get_permalink() ) . '">' . $thumb . '</a>' : $thumb;
                                    ?>
                                </div>
                                <div class="dk-cart-item-info">
                                    <div class="dk-cart-item-name">
                                        <?php
                                        $name = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
                                        echo $_product->is_visible() ? '<a href="' . esc_url( $_product->get_permalink() ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name );
                                        ?>
                                    </div>
                                    <?php if ( function_exists( 'ASC_Product_Code' ) && class_exists( 'ASC_Product_Code' ) ) : ?>
                                        <div class="dk-cart-item-code"><?php echo sprintf( esc_html__( 'کد کالا: %s', 'lylyrose' ), esc_html( ASC_Product_Code::get_code( $_product ) ) ); ?></div>
                                    <?php endif; ?>
                                    <div class="dk-cart-item-seller">
                                        <span class="dk-seller-dot"></span> <?php esc_html_e( 'فروشگاه اصلی', 'lylyrose' ); ?>
                                    </div>
                                    <?php if ( ! empty( $cart_item['data'] ) && $_product->is_in_stock() ) : ?>
                                        <div class="dk-cart-item-stock"><?php esc_html_e( 'موجود در انبار فروشنده', 'lylyrose' ); ?></div>
                                    <?php else : ?>
                                        <div class="dk-cart-item-stock is-out"><?php esc_html_e( 'ناموجود', 'lylyrose' ); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="dk-cart-item-side">
                                    <div class="dk-cart-item-remove">
                                        <?php
                                        echo apply_filters(
                                            'woocommerce_cart_item_remove_link',
                                            sprintf(
                                                '<a href="%s" class="remove" aria-label="%s" title="' . esc_attr__( 'حذف', 'lylyrose' ) . '">&times;</a>',
                                                esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                                esc_attr__( 'حذف این کالا از سبد خرید', 'lylyrose' )
                                            ),
                                            $cart_item_key
                                        );
                                        ?>
                                    </div>
                                    <div class="dk-cart-item-price">
                                        <?php if ( $dk_discount > 0 ) : ?>
                                            <span class="dk-badge-off"><?php echo esc_html( lylyrose_fa_num( $dk_discount ) ); ?>٪</span>
                                        <?php endif; ?>
                                        <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
                                    </div>
                                    <div class="dk-cart-item-qty">
                                        <?php
                                        if ( $_product->is_sold_individually() ) {
                                            echo '<span class="dk-qty-fixed">' . esc_html( lylyrose_fa_num( 1 ) ) . '</span>';
                                        } else {
                                            echo woocommerce_quantity_input(
                                                array(
                                                    'input_name'   => "cart[{$cart_item_key}][qty]",
                                                    'input_value'  => $cart_item['quantity'],
                                                    'max_value'    => $_product->get_max_purchase_quantity(),
                                                    'min_value'    => '0',
                                                    'product_name' => $_product->get_name(),
                                                ),
                                                $_product,
                                                false
                                            );
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="dk-cart-actions">
                            <a class="dk-cart-continue" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'ادامه خرید', 'lylyrose' ); ?></a>
                            <button type="submit" class="dk-btn dk-btn-secondary dk-cart-update" name="update_cart" value="1"><?php esc_html_e( 'به‌روزرسانی سبد', 'lylyrose' ); ?></button>
                        </div>
                        <?php do_action( 'woocommerce_after_cart_table' ); ?>
                    </div>
                </form>
            </div>

            <aside class="dk-cart-summary">
                <div class="dk-cart-summary-card">
                    <h2 class="dk-cart-summary-title"><?php esc_html_e( 'اطلاعات پرداخت', 'lylyrose' ); ?></h2>
                    <?php do_action( 'woocommerce_cart_collaterals' ); ?>
                    <?php if ( wc_coupons_enabled() && ! WC()->cart->get_coupons() ) : ?>
                        <form class="dk-coupon-form" method="post" action="<?php echo esc_url( wc_get_cart_url() ); ?>">
                            <label class="dk-coupon-label" for="dk_coupon_code"><?php esc_html_e( 'کد تخفیف دارید؟', 'lylyrose' ); ?></label>
                            <div class="dk-coupon-row">
                                <input type="text" id="dk_coupon_code" name="coupon_code" class="dk-coupon-input" placeholder="<?php echo esc_attr__( 'مثلاً WELCOME10', 'lylyrose' ); ?>" autocomplete="off" />
                                <button type="submit" class="dk-coupon-btn" name="apply_coupon" value="1"><?php esc_html_e( 'اعمال', 'lylyrose' ); ?></button>
                            </div>
                            <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                        </form>
                    <?php endif; ?>
                    <a class="dk-btn dk-btn-primary dk-cart-continue-btn" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'تایید و تکمیل سفارش', 'lylyrose' ); ?></a>
                </div>
            </aside>
        </div>
        <?php else : ?>
            <?php wc_get_template( 'cart/cart-empty.php' ); ?>
        <?php endif; ?>
    </div>
</div>
<?php do_action( 'woocommerce_after_cart' ); ?>
