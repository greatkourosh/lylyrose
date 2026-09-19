<?php
/**
 * Product Archive Template
 */
get_header();
?>

<div class="dk-container">
    <div class="dk-shop-wrapper">
        <?php if ( function_exists('woocommerce_breadcrumb') ) : ?>
        <nav class="dk-breadcrumb">
            <?php woocommerce_breadcrumb(); ?>
        </nav>
        <?php endif; ?>

        <?php
        if ( woocommerce_product_loop() ) :

            // Product filters header
            if ( is_product_taxonomy() ) :
                $term = get_queried_object();
                if ( $term && ! is_wp_error( $term ) ) :
                    $term_desc = term_description( $term->term_id, $term->taxonomy );
                    if ( $term_desc ) :
            ?>
            <div class="dk-category-header">
                <h1 class="dk-section-title dk-category-title"><?php single_term_title(); ?></h1>
                <div class="dk-category-desc"><?php echo wp_kses_post( $term_desc ); ?></div>
            </div>
            <?php endif; endif; endif; ?>

            <?php do_action( 'woocommerce_before_shop_loop' ); ?>

            <?php woocommerce_product_loop_start(); ?>

            <?php if ( wc_get_loop_prop( 'total' ) ) : ?>
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php wc_get_template_part( 'content', 'product' ); ?>
                <?php endwhile; ?>
            <?php endif; ?>

            <?php woocommerce_product_loop_end(); ?>

            <?php do_action( 'woocommerce_after_shop_loop' ); ?>

        <?php else : ?>
            <div class="dk-no-products">
                <h2><?php esc_html_e( 'هیچ محصولی یافت نشد', 'aroma-store' ); ?></h2>
                <p><?php esc_html_e( 'محصولی در این دسته‌بندی موجود نیست.', 'aroma-store' ); ?></p>
                <a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="dk-btn dk-btn-primary">
                    <?php esc_html_e( 'مشاهده همه محصولات', 'aroma-store' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- #content -->

<?php
get_footer();
