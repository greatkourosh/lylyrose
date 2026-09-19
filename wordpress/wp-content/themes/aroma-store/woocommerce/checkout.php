<?php
/**
 * Checkout Template
 * @package Aroma_Store
 */

/**
 * Hook: woocommerce_before_checkout_form.
 */
do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
    echo '<div class="dk-container"><p>' . esc_html__( 'شما باید وارد حساب کاربری خود شوید تا سفارش دهید.', 'aroma-store' ) . '</p></div>';
    get_footer();
    return;
}
?>

<div class="dk-checkout-page">
    <div class="dk-container">
        <div class="dk-checkout-container">
            <h1 class="dk-section-title" style="margin-bottom:24px;"><?php esc_html_e( 'پرداخت و ثبت سفارش', 'aroma-store' ); ?></h1>

<?php if ( get_option( 'woocommerce_enable_guest_checkout' ) === 'yes' ) : ?>
    <form name="checkout" method="post" class="checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>">
<?php else : ?>
    <form name="checkout" method="post" class="checkout" enctype="multipart/form-data">
<?php endif;

/**
 * Hook: woocommerce_checkout_before_customer_details.
 */
do_action( 'woocommerce_checkout_before_customer_details' );
?>

            <div class="dk-checkout-grid">
                <div class="dk-checkout-form">
                    <div class="dk-checkout-customer-details">
                        <h3><?php esc_html_e( 'اطلاعات شخصی', 'aroma-store' ); ?></h3>
                        <?php if ( $checkout->get_checkout_fields( 'customer' ) ) : ?>
                            <div class="form-row">
                                <?php foreach ( $checkout->get_checkout_fields( 'customer' ) as $key => $field ) : ?>
                                    <div class="form-group <?php echo $key; ?>_field">
                                        <?php wc_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dk-checkout-order-details">
                    <?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
                        <div class="dk-order-review">
                            <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

                            <h3><?php esc_html_e( 'اطلاعات سفارش', 'aroma-store' ); ?></h3>
                            <div class="dk-order-review-content">
                                <?php do_action( woocommerce_order_review_html ); ?>
                            </div>

                            <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
                        </div>
                    <?php else : ?>
                        <div class="dk-order-review">
                            <h3><?php esc_html_e( 'خلاصه سفارش', 'aroma-store' ); ?></h3>
                            <div class="dk-order-review-content">
                                <?php do_action( woocommerce_order_review_html ); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

<?php
/**
 * Hook: woocommerce_checkout_after_customer_details.
 */
do_action( 'woocommerce_checkout_after_customer_details' );
?>

        </div>
    </div>
</div>

</div><!-- #content -->

</form>

<?php
