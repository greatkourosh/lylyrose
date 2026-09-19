<?php
/**
 * Product card — Digikala-style.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

global $product;
if ( empty( $product ) || ! $product->is_visible() ) {
    return;
}
$percent = digikala_discount_percent( $product );
?>
<li <?php wc_product_class( 'dk-product-card', $product ); ?>>
    <div class="dk-product-media">
        <?php if ( $percent ) : ?>
            <span class="dk-product-badge-sale"><?php echo esc_html( digikala_fa_num( $percent ) ); ?>٪</span>
        <?php endif; ?>
        <a class="dk-wish" href="#" title="افزودن به علاقه‌مندی‌ها" onclick="return false;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </a>
        <?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
    </div>
    <h3 class="dk-product-title"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
    <?php
    $count = $product->get_review_count();
    if ( $count > 0 ) : ?>
        <div class="dk-product-rating">
            <span class="dk-stars">&#9733;</span>
            <span><?php echo esc_html( digikala_fa_num( number_format_i18n( $product->get_average_rating(), 1 ) ) ); ?></span>
            <span>(<?php echo esc_html( digikala_fa_num( $count ) ); ?>)</span>
        </div>
    <?php endif; ?>
    <div class="dk-product-price dk-price-row">
        <?php if ( $percent ) : ?>
            <span class="dk-discount-tag"><?php echo esc_html( digikala_fa_num( $percent ) ); ?>٪</span>
        <?php endif; ?>
        <span class="dk-price-current">
            <?php if ( $product->is_on_sale() ) : ?>
                <del class="dk-price-old" style="display:block;"><?php echo wp_kses_post( wc_price( $product->get_regular_price() ) ); ?></del>
            <?php endif; ?>
            <?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?>
        </span>
    </div>
</li>
