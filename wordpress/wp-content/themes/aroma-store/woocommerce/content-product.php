<?php
defined( 'ABSPATH' ) || exit;
global $product;
if ( empty( $product ) || ! $product->is_visible() ) return;
?>
<li <?php wc_product_class( 'dk-product-card', $product ); ?>>
<div class="dk-product-card-inner">
<?php if ( $product->is_on_sale() ) : ?>
<span class="dk-product-badge dk-product-badge-sale">-<?php echo esc_html( $product->get_sale_price() ); ?>%</span>
<?php endif; ?>
<span class="dk-product-wishlist" title="افزودن به علاقه‌مندی">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
</span>
<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="dk-product-link">
<div class="dk-product-img">
<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
</div>
<h3 class="dk-product-title"><?php echo esc_html( $product->get_name() ); ?></h3>
<?php
$rating = $product->get_average_rating();
$count  = $product->get_review_count();
if ( $count > 0 ) : ?>
<div class="dk-product-rating">
<span class="dk-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
<span>(<?php echo esc_html( $count ); ?>)</span>
</div>
<?php endif; ?>
<div class="dk-product-price">
<?php if ( $product->is_on_sale() ) : ?>
<span class="dk-price-old"><?php echo wp_kses_post( wc_price( $product->get_regular_price() ) ); ?></span>
<?php endif; ?>
<span class="dk-price-current"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span>
</div>
</a>
</div>
</li>