<?php
defined( 'ABSPATH' ) || exit;
get_header();
$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
?>

<section class="dk-hero">
<div class="dk-container">
<a href="<?php echo esc_url( $shop_url ); ?>" class="dk-hero-banner">
<h2>جشنواره تخفیف‌های ویژه عطر استور</h2>
<p>بهترین عطرهای اورجینال با قیمت استثنایی | ارسال رایگان بالای ۵۰۰ هزار تومان</p>
</a>
</div>
</section>

<div class="dk-container">

<section class="dk-section">
<div class="dk-section-header">
<h2 class="dk-section-title">دسته‌بندی‌های محبوب</h2>
<a href="<?php echo esc_url( $shop_url ); ?>" class="dk-section-link">مشاهده همه &larr;</a>
</div>
<div class="dk-cat-grid">
<?php
$cats = array(
    'men'            => array( 'مردانه', '&#x1F454;' ),
    'women'          => array( 'زنانه', '&#x1F338;' ),
    'unisex'         => array( 'یونیسکس', '&#x1F4AB;' ),
    'eau-de-parfum'  => array( 'ادو پرفیوم', '&#x2728;' ),
    'eau-de-toilette'=> array( 'ادو تویلت', '&#x1F33F;' ),
    'perfume-oil'    => array( 'عطر روغنی', '&#x1FAE7;' ),
    'body-spray'     => array( 'بادی اسپلش', '&#x1F4A8;' ),
    'gift-sets'      => array( 'ست هدیه', '&#x1F381;' ),
);
foreach ( $cats as $slug => $data ) : ?>
<a href="<?php echo esc_url( add_query_arg( 'product_cat', $slug, $shop_url ) ); ?>" class="dk-cat-item">
<span class="dk-cat-icon"><?php echo $data[1]; ?></span>
<span><?php echo esc_html( $data[0] ); ?></span>
</a>
<?php endforeach; ?>
</div>
</section>

<?php if ( class_exists( 'WooCommerce' ) ) : ?>
<section class="dk-section">
<div class="dk-section-header">
<h2 class="dk-section-title">پرفروش‌ترین عطرها</h2>
<a href="<?php echo esc_url( $shop_url ); ?>" class="dk-section-link">مشاهده همه &larr;</a>
</div>
<div class="dk-product-grid">
<?php
$args = array(
    'post_type'      => 'product',
    'posts_per_page' => 10,
    'meta_key'       => 'total_sales',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
    'tax_query'      => array( array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'exclude-from-catalog', 'operator' => 'NOT IN' ) ),
);
$query = new WP_Query( $args );
if ( $query->have_posts() ) :
    while ( $query->have_posts() ) : $query->the_post();
        global $product;
        if ( ! $product ) continue;
        ?>
        <div class="dk-product-card" onclick="window.location='<?php the_permalink(); ?>'">
            <?php if ( $product->is_on_sale() ) : ?>
                <span class="dk-product-badge">تخفیف</span>
            <?php endif; ?>
            <div class="dk-product-img">
                <?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
            </div>
            <h3 class="dk-product-title"><?php the_title(); ?></h3>
            <?php
            $rating = $product->get_average_rating();
            $count  = $product->get_review_count();
            if ( $count > 0 ) : ?>
                <div class="dk-product-rating">
                    <span class="dk-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
                    <span>(<?php echo $count; ?>)</span>
                </div>
            <?php endif; ?>
            <div class="dk-product-price">
                <?php if ( $product->is_on_sale() ) : ?>
                    <span class="dk-price-old"><?php echo wp_kses_post( wc_price( $product->get_regular_price() ) ); ?></span>
                <?php endif; ?>
                <span class="dk-price-current"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span>
            </div>
        </div>
        <?php
    endwhile;
    wp_reset_postdata();
endif;
?>
</div>
</section>

<section class="dk-section">
<div class="dk-section-header">
<h2 class="dk-section-title">برندهای محبوب</h2>
</div>
<div class="dk-brand-strip">
<?php
$brands = array( 'Chanel', 'Dior', 'Gucci', 'Armani', 'Versace', 'Hermes', 'Lancome', 'YSL', 'Tom Ford', 'Bulgari', 'Calvin Klein', 'Davidoff' );
foreach ( $brands as $brand ) : ?>
<a href="<?php echo esc_url( add_query_arg( 'pa_brand', sanitize_title( $brand ), $shop_url ) ); ?>" class="dk-brand-item"><?php echo esc_html( $brand ); ?></a>
<?php endforeach; ?>
</div>
</section>

<section class="dk-section">
<div class="dk-section-header">
<h2 class="dk-section-title">جدیدترین عطرها</h2>
<a href="<?php echo esc_url( $shop_url ); ?>" class="dk-section-link">مشاهده همه &larr;</a>
</div>
<div class="dk-product-grid">
<?php
$args2 = array(
    'post_type'      => 'product',
    'posts_per_page' => 5,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'tax_query'      => array( array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'exclude-from-catalog', 'operator' => 'NOT IN' ) ),
);
$query2 = new WP_Query( $args2 );
if ( $query2->have_posts() ) :
    while ( $query2->have_posts() ) : $query2->the_post();
        global $product;
        if ( ! $product ) continue;
        ?>
        <div class="dk-product-card" onclick="window.location='<?php the_permalink(); ?>'">
            <?php if ( $product->is_on_sale() ) : ?>
                <span class="dk-product-badge">تخفیف</span>
            <?php endif; ?>
            <div class="dk-product-img">
                <?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
            </div>
            <h3 class="dk-product-title"><?php the_title(); ?></h3>
            <div class="dk-product-price">
                <span class="dk-price-current"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span>
            </div>
        </div>
        <?php
    endwhile;
    wp_reset_postdata();
endif;
?>
</div>
</section>
<?php endif; ?>

</div><!-- .dk-container -->

</div><!-- #content -->

<?php get_footer(); ?>