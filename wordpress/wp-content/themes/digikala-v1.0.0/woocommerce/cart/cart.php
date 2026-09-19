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
            <span class="dk-step is-active">۱. سبد خرید</span>
            <span class="dk-step-sep"></span>
            <span class="dk-step">۲. تسویه حساب</span>
            <span class="dk-step-sep"></span>
            <span class="dk-step">۳. تکمیل سفارش</span>
        </div>

        <h1 class="dk-cart-title">سبد خرید</h1>

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
                            $dk_discount = function_exists( 'digikala_discount_percent' ) ? digikala_discount_percent( $_product ) : 0;
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
                                        <div class="dk-cart-item-code">کد کالا: <?php echo esc_html( ASC_Product_Code::get_code( $_product ) ); ?></div>
                                    <?php endif; ?>
                                    <div class="dk-cart-item-seller">
                                        <span class="dk-seller-dot"></span> فروشگاه اصلی
                                    </div>
                                    <?php if ( ! empty( $cart_item['data'] ) && $_product->is_in_stock() ) : ?>
                                        <div class="dk-cart-item-stock">موجود در انبار فروشنده</div>
                                    <?php else : ?>
                                        <div class="dk-cart-item-stock is-out">ناموجود</div>
                                    <?php endif; ?>
                                </div>
                                <div class="dk-cart-item-side">
                                    <div class="dk-cart-item-remove">
                                        <?php
                                        echo apply_filters(
                                            'woocommerce_cart_item_remove_link',
                                            sprintf(
                                                '<a href="%s" class="remove" aria-label="%s" title="حذف">&times;</a>',
                                                esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                                esc_attr__( 'حذف این کالا از سبد خرید', 'digikala' )
                                            ),
                                            $cart_item_key
                                        );
                                        ?>
                                    </div>
                                    <div class="dk-cart-item-price">
                                        <?php if ( $dk_discount > 0 ) : ?>
                                            <span class="dk-badge-off"><?php echo esc_html( digikala_fa_num( $dk_discount ) ); ?>٪</span>
                                        <?php endif; ?>
                                        <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
                                    </div>
                                    <div class="dk-cart-item-qty">
                                        <?php
                                        if ( $_product->is_sold_individually() ) {
                                            echo '<span class="dk-qty-fixed">' . esc_html( digikala_fa_num( 1 ) ) . '</span>';
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
                            <a class="dk-cart-continue" href="<?php echo esc_url( $shop_url ); ?>">ادامه خرید</a>
                            <button type="submit" class="dk-btn dk-btn-secondary dk-cart-update" name="update_cart" value="1">به‌روزرسانی سبد</button>
                        </div>
                        <?php do_action( 'woocommerce_after_cart_table' ); ?>
                    </div>
                </form>
            </div>

            <aside class="dk-cart-summary">
                <div class="dk-cart-summary-card">
                    <h2 class="dk-cart-summary-title">اطلاعات پرداخت</h2>
                    <?php do_action( 'woocommerce_cart_collaterals' ); ?>
                    <a class="dk-btn dk-btn-primary dk-cart-continue-btn" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">تایید و تکمیل سفارش</a>
                </div>
            </aside>
        </div>
        <?php else : ?>
            <?php wc_get_template( 'cart/cart-empty.php' ); ?>
        <?php endif; ?>
    </div>
</div>
<?php do_action( 'woocommerce_after_cart' ); ?>
