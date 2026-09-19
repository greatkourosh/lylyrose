<?php
/**
 * Empty cart — Digikala-style empty state.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
?>
<div class="dk-cart-page">
    <div class="dk-container">
        <h1 class="dk-cart-title">سبد خرید</h1>
        <div class="dk-cart-empty">
            <div class="dk-cart-empty-art" aria-hidden="true">
                <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
                    <circle cx="60" cy="60" r="56" fill="#f0f9ff"/>
                    <path d="M38 45h44l-5 32a6 6 0 0 1-6 5H49a6 6 0 0 1-6-5l-5-32Z" stroke="#a0c8de" stroke-width="3" fill="#fff"/>
                    <path d="M50 45V39a10 10 0 0 1 20 0v6" stroke="#a0c8de" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>
            <p class="dk-cart-empty-text">سبد خرید شما خالی است!</p>
            <a class="dk-btn dk-btn-primary" href="<?php echo esc_url( $shop_url ); ?>">بازگشت به فروشگاه</a>
        </div>
    </div>
</div>
