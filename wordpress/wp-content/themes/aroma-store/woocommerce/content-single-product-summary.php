<?php
/**
 * Single Product Summary Template Part
 * @package Aroma_Store
 */
global $product;
?>

<h1 class="dk-product-title-large"><?php the_title(); ?></h1>

<div class="dk-product-meta">
    <?php if ( $product->is_on_sale() ) : ?>
        <span class="dk-product-badge">تخفیف</span>
    <?php endif; ?>
    
    <div class="dk-product-rating">
        <?php echo wc_get_rating_html( $product->get_average_rating() ); ?>
        <span class="dk-rating-count">(<?php echo $product->get_review_count(); ?>)</span>
    </div>
</div>

<div class="dk-product-price">
    <?php if ( $product->is_on_sale() ) : ?>
        <span class="dk-price-old"><?php echo wp_kses_post( wc_price( $product->get_regular_price() ) ); ?></span>
    <?php endif; ?>
    <span class="dk-price-current"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span>
</div>

<div class="dk-product-description">
    <?php the_content(); ?>
</div>

<div class="dk-product-variations">
    <?php
    if ( $product->has_attributes() ) {
        foreach ( $product->get_attributes() as $attribute ) {
            $name  = $attribute->get_name();
            $value = wc_get_product_terms_names( $product->get_id(), $name );
            ?>
            <div class="dk-attribute">
                <span class="dk-attribute-label"><?php echo esc_html( wc_attribute_label( $name ) ); ?>:</span>
                <span class="dk-attribute-value"><?php echo implode( ', ', $value ); ?></span>
            </div>
            <?php
        }
    }
    ?>
</div>

<div class="dk-product-actions">
    <?php
    if ( $product->is_in_stock() ) {
        woocommerce_template_single_add_to_cart();
    } else {
        echo '<p class="dk-out-of-stock">' . esc_html__( 'ناموجود', 'aroma-store' ) . '</p>';
    }
    ?>
</div>

<div class="dk-product-share">
    <span class="dk-share-label">اشتراک‌گذاری:</span>
    <?php
    // Add social sharing links here if needed
    ?>
</div>
