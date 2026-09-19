<?php
/**
 * Checkout — Digikala-style: stepper, form card, sticky order summary.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
?>
<div class="dk-cart-page">
    <div class="dk-container">
        <div class="dk-cart-stepper" aria-hidden="true">
            <span class="dk-step"><a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( '۱. سبد خرید', 'lylyrose' ); ?></a></span>
            <span class="dk-step-sep"></span>
            <span class="dk-step is-active"><?php esc_html_e( '۲. اطلاعات ارسال و پرداخت', 'lylyrose' ); ?></span>
            <span class="dk-step-sep"></span>
            <span class="dk-step"><?php esc_html_e( '۳. تکمیل سفارش', 'lylyrose' ); ?></span>
        </div>

        <h1 class="dk-cart-title"><?php esc_html_e( 'تسویه حساب', 'lylyrose' ); ?></h1>

        <?php if ( WC()->cart->is_empty() ) : ?>
            <div class="dk-cart-empty">
                <div class="dk-cart-empty-art" aria-hidden="true">
                    <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
                        <circle cx="60" cy="60" r="56" fill="#f0f9ff"/>
                        <path d="M38 45h44l-5 32a6 6 0 0 1-6 5H49a6 6 0 0 1-6-5l-5-32Z" stroke="#a0c8de" stroke-width="3" fill="#fff"/>
                        <path d="M50 45V39a10 10 0 0 1 20 0v6" stroke="#a0c8de" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="dk-cart-empty-text"><?php esc_html_e( 'سبد خرید شما خالی است!', 'lylyrose' ); ?></p>
                <a class="dk-btn dk-btn-primary" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'بازگشت به فروشگاه', 'lylyrose' ); ?></a>
            </div>
            <?php return; endif; ?>

        <form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
            <div class="dk-checkout-layout">
                <div class="dk-checkout-main">
                    <?php if ( WC()->checkout()->get_checkout_fields() ) : ?>
                        <div class="dk-cs2">
                            <div class="dk-checkout-step dk-checkout-step--1" data-step="1">
                                <div class="dk-checkout-card">
                                    <?php do_action( 'woocommerce_before_checkout_form', $checkout ); ?>
                                    <?php do_action( 'woocommerce_checkout_billing' ); ?>
                                    <?php do_action( 'woocommerce_checkout_shipping' ); ?>
                                    <div class="dk-step-next">
                                        <button type="button" class="dk-btn dk-btn-primary dk-step-next-btn"><?php esc_html_e( 'ادامه به پرداخت', 'lylyrose' ); ?></button>
                                    </div>
                                </div>
                            </div>
                            <div class="dk-checkout-step dk-checkout-step--2" data-step="2">
                                <div class="dk-checkout-card">
                                    <p class="dk-step-back"><a href="#" class="dk-step-back-link"><?php esc_html_e( 'ویرایش اطلاعات ارسال', 'lylyrose' ); ?></a></p>
                                    <?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
                                    <?php do_action( 'woocommerce_checkout_order_review' ); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="dk-checkout-summary">
                    <div class="dk-cart-summary-card dk-checkout-sticky">
                        <h2 class="dk-cart-summary-title"><?php esc_html_e( 'خلاصه سفارش', 'lylyrose' ); ?></h2>
                        <?php woocommerce_order_review(); ?>
                        <?php do_action( 'woocommerce_checkout_order_review' ); ?>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</div>
