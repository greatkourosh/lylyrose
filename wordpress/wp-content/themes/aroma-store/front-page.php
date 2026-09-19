<?php
defined( 'ABSPATH' ) || exit;
get_header();
$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );
$categories = array(
    array( 'title' => 'عطر مردانه', 'slug' => 'men', 'icon' => '♂', 'tone' => 'blue' ),
    array( 'title' => 'عطر زنانه', 'slug' => 'women', 'icon' => '✿', 'tone' => 'rose' ),
    array( 'title' => 'عطر یونیسکس', 'slug' => 'unisex', 'icon' => '✦', 'tone' => 'violet' ),
    array( 'title' => 'ادو پرفیوم', 'slug' => 'eau-de-parfum', 'icon' => '◈', 'tone' => 'gold' ),
    array( 'title' => 'عطر روغنی', 'slug' => 'perfume-oil', 'icon' => '◌', 'tone' => 'green' ),
    array( 'title' => 'بادی اسپلش', 'slug' => 'body-spray', 'icon' => '≈', 'tone' => 'cyan' ),
    array( 'title' => 'ست هدیه', 'slug' => 'gift-sets', 'icon' => '◇', 'tone' => 'orange' ),
    array( 'title' => 'سمپل عطر', 'slug' => 'samples', 'icon' => '◎', 'tone' => 'purple' ),
);
$products = new WP_Query( array(
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
) );
$offer_products = new WP_Query( array(
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => 4,
    'orderby' => 'date',
    'order' => 'DESC',
    'no_found_rows' => true,
    'ignore_sticky_posts' => true,
) );
$hero_product = $offer_products->have_posts() ? wc_get_product( $offer_products->posts[0] ) : false;
?>
<main class="aroma-home">
    <section class="aroma-hero">
        <div class="aroma-container aroma-hero-grid">
            <div class="aroma-hero-copy">
                <span class="aroma-eyebrow">پیشنهاد ویژه عطر استور</span>
                <h1>تا <strong>۳۰٪ تخفیف</strong><br>برای رایحه‌های محبوب</h1>
                <p>عطر مورد علاقه‌ات را با ضمانت اصالت و ارسال سریع از مجموعه منتخب عطر استور پیدا کن.</p>
                <div class="aroma-hero-actions">
                    <a class="aroma-button aroma-button-light" href="<?php echo esc_url( $shop_url ); ?>">مشاهده پیشنهادها <span>←</span></a>
                    <a class="aroma-text-link" href="#aroma-categories">همه دسته‌ها <span>↓</span></a>
                </div>
            </div>
            <div class="aroma-hero-art" aria-hidden="true">
                <div class="aroma-hero-panel"><span>AROMA<br><b>PERFUME</b></span><?php if ( $hero_product ) : ?><?php echo $hero_product->get_image( 'woocommerce_single', array( 'alt' => esc_attr( $hero_product->get_name() ) ) ); ?><small><?php echo esc_html( wp_trim_words( $hero_product->get_name(), 7 ) ); ?></small><?php endif; ?></div>
                <div class="aroma-hero-dots"><i></i><i></i><i></i><i></i></div>
            </div>
        </div>
    </section>

    <section class="aroma-container aroma-service-row" aria-label="مزایای خرید">
        <div><span class="aroma-service-icon">✧</span><div><strong>تضمین اصالت کالا</strong><small>خریدی مطمئن و خیال آسوده</small></div></div>
        <div><span class="aroma-service-icon">↗</span><div><strong>ارسال سریع و رایگان</strong><small>برای سفارش‌های بالای ۵۰۰ هزار تومان</small></div></div>
        <div><span class="aroma-service-icon">↺</span><div><strong>۷ روز ضمانت بازگشت</strong><small>رضایت شما اولویت ماست</small></div></div>
        <div><span class="aroma-service-icon">♧</span><div><strong>مشاوره تخصصی رایحه</strong><small>انتخابی دقیق برای سلیقه شما</small></div></div>
    </section>

    <section class="aroma-container aroma-home-section" id="aroma-categories">
        <div class="aroma-section-heading"><div><span class="aroma-kicker">EXPLORE YOUR SCENT</span><h2>دسته‌بندی‌های محبوب</h2></div><a href="<?php echo esc_url( $shop_url ); ?>">مشاهده همه <span>←</span></a></div>
        <div class="aroma-category-grid">
            <?php foreach ( $categories as $category ) : ?><a class="aroma-category-card aroma-tone-<?php echo esc_attr( $category['tone'] ); ?>" href="<?php echo esc_url( add_query_arg( 'product_cat', $category['slug'], $shop_url ) ); ?>"><span class="aroma-category-icon"><?php echo esc_html( $category['icon'] ); ?></span><strong><?php echo esc_html( $category['title'] ); ?></strong><small>مشاهده مجموعه <span>←</span></small></a><?php endforeach; ?>
        </div>
    </section>

    <section class="aroma-deals">
        <div class="aroma-container aroma-deals-inner"><div class="aroma-deals-copy"><span class="aroma-kicker">پیشنهادهای شگفت‌انگیز</span><h2>تخفیف‌های<br><strong>امروز</strong></h2><p>فرصت محدود برای خرید عطرهای منتخب با قیمت ویژه.</p><a class="aroma-button aroma-button-dark" href="<?php echo esc_url( $shop_url ); ?>">مشاهده همه <span>←</span></a></div><div class="aroma-offer-rail"><?php if ( $offer_products->have_posts() ) : while ( $offer_products->have_posts() ) : $offer_products->the_post(); global $product; if ( ! $product ) { continue; } ?><a class="aroma-offer-card" href="<?php the_permalink(); ?>"><?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?><span class="aroma-offer-discount">تخفیف</span><strong><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></strong></a><?php endwhile; wp_reset_postdata(); endif; ?></div></div>
    </section>

    <section class="aroma-container aroma-home-section aroma-products-section">
        <div class="aroma-section-heading"><div><span class="aroma-kicker">CURATED FOR YOU</span><h2>جدیدترین انتخاب‌ها</h2></div><a href="<?php echo esc_url( $shop_url ); ?>">مشاهده همه <span>←</span></a></div>
        <div class="aroma-product-rail">
        <?php if ( $products->have_posts() ) : while ( $products->have_posts() ) : $products->the_post(); global $product; if ( ! $product ) { continue; } ?><article class="aroma-product-card"><a href="<?php the_permalink(); ?>" class="aroma-product-image"><?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'alt' => esc_attr( $product->get_name() ) ) ); ?><?php if ( $product->is_on_sale() ) : ?><span class="aroma-product-sale">پیشنهاد ویژه</span><?php endif; ?><span class="aroma-product-heart">♡</span></a><div class="aroma-product-info"><small><?php echo esc_html( $product->get_attribute( 'pa_brand' ) ?: 'Aroma Collection' ); ?></small><h3><a href="<?php the_permalink(); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3><div class="aroma-product-bottom"><strong><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></strong><span class="aroma-product-arrow">←</span></div></div></article><?php endwhile; wp_reset_postdata(); else : ?><p class="aroma-empty">محصولی برای نمایش وجود ندارد.</p><?php endif; ?>
        </div>
    </section>

    <section class="aroma-container aroma-editorial"><div><span class="aroma-kicker">THE AROMA JOURNAL</span><h2>رایحه خود را<br><em>بهتر بشناسید</em></h2><p>راهنمای انتخاب عطر بر اساس شخصیت، فصل و حال‌وهوای شما.</p><a href="<?php echo esc_url( $shop_url ); ?>" class="aroma-outline-button">مطالعه راهنما <span>←</span></a></div><div class="aroma-editorial-visual"><span>SCENT<br>IS A<br>MEMORY</span></div></section>
</main>
<?php get_footer(); ?>
