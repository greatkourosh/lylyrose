<?php
/**
 * Cart Template
 * @package Aroma_Store
 */

/**
 * Hook: woocommerce_before_cart.
 */
do_action( 'woocommerce_before_cart' );
?>

<div class="dk-cart-page">
    <div class="dk-container">
        <div class="dk-cart-container">
            <h1 class="dk-section-title" style="margin-bottom:24px;"><?php echo esc_html( get_cart_page_title() ); ?></h1>

        <div class="cart-layout">
            <?php
            /**
             * Hook: woocommerce_before_cart_table.
             */
            do_action( 'woocommerce_before_cart_table' );
            ?>

            <form class="cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
                <div class="cart-table-wrapper">
                    <table class="cart-table" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="product-thumbnail">&nbsp;</th>
                                <th class="product-name">
                                    <?php _e( 'محصول', 'aroma-store' ); ?>
                                </th>
                                <th class="product-price">
                                    <?php _e( 'قیمت', 'aroma-store' ); ?>
                                </th>
                                <th class="product-quantity">
                                    <?php _e( 'تعداد', 'aroma-store' ); ?>
                                </th>
                                <th class="product-subtotal">
                                    <?php _e( 'جمع کل', 'aroma-store' ); ?>
                                </th>
                                <th class="product-remove">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody class="cart-empty">
                            <?php if ( ! WC()->cart->get_cart_contents_count() ) : ?>
                                <tr class="cart-empty">
                                    <td colspan="6" class="cart-empty-message">
                                        <?php _e( 'سبد خرید شما خالی است.', 'aroma-store' ); ?>
                                        <a href="<?php echo esc_url( wc_get_shop_url() ); ?>" class="button">
                                            <?php _e( 'خرید کنید', 'aroma-store' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php do_action( 'woocommerce_cart_table' ); ?>
                        <?php do_action( 'woocommerce_cart_table_before_cart_contents' ); ?>
                        <?php
                        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                            $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                            ?>
                            <tr class="cart-item">
                                <td class="product-thumbnail">
                                    <?php
                                    $thumbnail = apply_filters(
                                        'woocommerce_cart_item_thumbnail',
                                        $_product->get_image(),
                                        $cart_item,
                                        $cart_item_key
                                    );

                                    if ( ! $_product->is_visible() ) {
                                        echo $thumbnail;
                                    } else {
                                        echo '<a href="' . esc_url( $_product->get_permalink() ) . '">' . $thumbnail . '</a>';
                                    }
                                    ?>
                                </td>

                                <td class="product-name" data-title="<?php esc_attr_e( 'محصول', 'aroma-store' ); ?>">
                                    <?php if ( ! $_product->is_visible() ) : ?>
                                        <?php echo apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;'; ?>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $_product->get_permalink() ); ?>">
                                            <?php echo apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ); ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php do_action( 'woocommerce_cart_item_before_contents', $cart_item, $cart_item_key ); ?>

                                    <?php if ( ! $cart_item['quantity'] || $cart_item['quantity'] <= 0 ) : ?>
                                        <?php echo sprintf( '%s %s', __('خرید شده', 'aroma-store'), wc_get_cart_remove_url( $cart_item_key ) ); ?>
                                    <?php else : ?>
                                        <?php echo wc_get_quantity_html(
                                            '<input type="number"',
                                            $cart_item['quantity']
                                        ); ?>
                                    <?php endif; ?>
                                </td>

                                <td class="product-price" data-title="<?php esc_attr_e( 'قیمت', 'aroma-store' ); ?>">
                                    <?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?>
                                </td>

                                <td class="product-subtotal" data-title="<?php esc_attr_e( 'جمع', 'aroma-store' ); ?>">
                                    <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
                                </td>

                                <td class="product-remove" data-title=" ">
                                    <?php
                                    echo apply_filters(
                                        'woocommerce_cart_item_remove_link',
                                        sprintf(
                                            '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                                            esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                            esc_html__( 'پاک کردن این مورد از سبد خرید', 'aroma-store' ),
                                            esc_attr( $cart_item['product_id'] ),
                                            esc_attr( $_product->get_sku() )
                                        ),
                                        $cart_item_key
                                    );
                                    ?>
                                </td>
                            </tr>
                            <?php
                        endforeach; // end of the loop
                        ?>
                        <?php do_action( 'woocommerce_cart_table_after_cart_contents' ); ?>
                    </table>
                </div>

                <?php do_action( 'woocommerce_cart_table' ); ?>

                <?php do_action( 'woocommerce_after_cart_table' ); ?>
            </form>
        </div>

        <?php if ( WC()->cart->get_cart_contents_count() > 0 ) : ?>
            <div class="cart-collaterals">
                <?php
                /**
                 * Hook: woocommerce_cart_collaterals.
                 */
                do_action( 'woocommerce_cart_collaterals' );
                ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- #content -->

<?php
/**
 * Hook: woocommerce_after_cart.
 */
do_action( 'woocommerce_after_cart' );