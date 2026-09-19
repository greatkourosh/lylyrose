<?php
/**
 * Single Product Gallery Template Part
 * @package Aroma_Store
 */
global $product;
?>

<div class="dk-product-gallery-main">
    <?php echo $product->get_image( 'woocommerce_single', array( 'style' => 'border-radius: 8px; box-shadow: var(--dk-shadow);' ) ); ?>
</div>

<div class="dk-product-gallery-thumbs">
    <?php
    $attachment_ids = $product->get_gallery_image_ids();
    if ( $attachment_ids && count( $attachment_ids ) > 0 ) {
        echo '<div class="dk-thumb-item">' . wp_get_attachment_image( $attachment_ids[0], 'thumbnail' ) . '</div>';
    }
    ?>
</div>
