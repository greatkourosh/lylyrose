<?php
/**
 * Front page — Digikala-style homepage.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;
get_header();

$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );

$has_woo = class_exists( 'WooCommerce' );

$latest = new WP_Query( array(
    'post_type'           => 'product',
    'post_status'         => 'publish',
    'posts_per_page'      => 10,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
) );

$sale = $has_woo ? new WP_Query( array(
    'post_type'           => 'product',
    'post_status'         => 'publish',
    'posts_per_page'      => 10,
    'meta_query'          => array(
        array(
            'key'     => '_sale_price',
            'value'   => '',
            'compare' => '!=',
        ),
    ),
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
) ) : false;

// Real product categories drive the story circles and the category grid.
$dk_cats = $has_woo ? get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'parent'     => 0,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 10,
    'exclude'    => array( get_option( 'default_product_cat' ) ),
) ) : array();
if ( is_wp_error( $dk_cats ) ) {
    $dk_cats = array();
}

// Popular brands (pa_brand attribute terms) -> shop filtered by ?dk_brands[]=<id>.
$dk_brands = $has_woo ? get_terms( array(
    'taxonomy'   => 'pa_brand',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 6,
) ) : array();
if ( is_wp_error( $dk_brands ) ) {
    $dk_brands = array();
}

$discount_url = add_query_arg( 'discount', '1', $shop_url );

$banners = array(
    array( 'tone' => 'rose',   'emoji' => '🏷️', 'title' => __( 'تخفیف‌های ویژه', 'lylyrose' ), 'sub' => __( 'کالاهای تخفیف‌دار', 'lylyrose' ), 'url' => $discount_url ),
    array( 'tone' => 'violet', 'emoji' => '✨', 'title' => __( 'جدیدترین‌ها', 'lylyrose' ), 'sub' => __( 'تازه‌های فروشگاه', 'lylyrose' ), 'url' => add_query_arg( 'sort', 'newest', $shop_url ) ),
    array( 'tone' => 'gold',   'emoji' => '🔥', 'title' => __( 'پرفروش‌ترین‌ها', 'lylyrose' ), 'sub' => __( 'انتخاب خریداران', 'lylyrose' ), 'url' => add_query_arg( 'sort', 'popular', $shop_url ) ),
    array( 'tone' => 'teal',   'emoji' => '🧴', 'title' => __( 'ارزان‌ترین‌ها', 'lylyrose' ), 'sub' => __( 'خرید به‌صرفه', 'lylyrose' ), 'url' => add_query_arg( 'sort', 'cheapest', $shop_url ) ),
);

// Magazine cards: real blog posts when the site has them, else buying-guide
// cards that link into the shop.
$editorial = array();
foreach ( get_posts( array( 'posts_per_page' => 3, 'no_found_rows' => true ) ) as $mag_post ) {
    $cats = get_the_category( $mag_post->ID );
    $editorial[] = array(
        'tag'   => $cats ? $cats[0]->name : __( 'مجله', 'lylyrose' ),
        'title' => get_the_title( $mag_post ),
        'text'  => wp_trim_words( wp_strip_all_tags( $mag_post->post_excerpt ? $mag_post->post_excerpt : $mag_post->post_content ), 18 ),
        'url'   => get_permalink( $mag_post ),
        'thumb' => (string) get_the_post_thumbnail_url( $mag_post, 'medium' ),
    );
}
if ( count( $editorial ) < 3 ) {
    $editorial = array(
        array( 'tag' => __( 'راهنما', 'lylyrose' ), 'title' => __( 'راهنمای خرید عطر مناسب برای هر فصل', 'lylyrose' ), 'text' => __( 'چطور رایحه‌ای متناسب با فصل و سلیقه‌ی خود انتخاب کنیم؟', 'lylyrose' ), 'url' => $shop_url, 'thumb' => '' ),
        array( 'tag' => __( 'ترند', 'lylyrose' ), 'title' => __( 'پرفروش‌ترین محصولات این هفته', 'lylyrose' ), 'text' => __( 'نگاهی به محبوب‌ترین کالاهای خریداران در هفته گذشته.', 'lylyrose' ), 'url' => add_query_arg( 'sort', 'popular', $shop_url ), 'thumb' => '' ),
        array( 'tag' => __( 'تخفیف', 'lylyrose' ), 'title' => __( 'چگونه از تخفیف‌های شگفت‌انگیز نهایت استفاده را ببریم؟', 'lylyrose' ), 'text' => __( 'نکاتی برای خرید هوشمندانه در کمپین‌های تخفیفی.', 'lylyrose' ), 'url' => $discount_url, 'thumb' => '' ),
    );
}
?>

<main class="dk-main">

    <?php if ( ! $has_woo ) : ?>
        <div class="dk-container">
            <div class="woocommerce-info"><?php esc_html_e( 'برای نمایش محصولات، افزونه WooCommerce را نصب و فعال کنید.', 'lylyrose' ); ?></div>
        </div>
    <?php endif; ?>

    <!-- Hero -->
    <div class="dk-container">
        <section class="dk-hero">
            <div class="dk-hero-slide">
                <div class="dk-hero-copy">
                    <span class="dk-kicker"><?php esc_html_e( 'پیشنهاد ویژه امروز', 'lylyrose' ); ?></span>
                    <h1><?php esc_html_e( 'تا ', 'lylyrose' ); ?><strong><?php esc_html_e( '۴۰٪ تخفیف', 'lylyrose' ); ?></strong><?php esc_html_e( ' روی منتخب کالاها', 'lylyrose' ); ?></h1>
                    <p><?php esc_html_e( 'فرصت محدود؛ همین حالا خرید کنید و از تخفیف‌های شگفت‌انگیز بهره‌مند شوید.', 'lylyrose' ); ?></p>
                    <a class="dk-btn dk-btn-primary" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'مشاهده پیشنهادها', 'lylyrose' ); ?></a>
                </div>
                <div class="dk-hero-art" aria-hidden="true">
                    <span style="font-size:120px;">🛒</span>
                </div>
            </div>
            <div class="dk-hero-dots"><i class="on"></i><i></i><i></i></div>
        </section>
    </div>

    <!-- Services -->
    <div class="dk-container">
        <section class="dk-service-row" aria-label="<?php echo esc_attr__( 'مزایای خرید', 'lylyrose' ); ?>">
            <div class="dk-service-item"><span class="dk-service-icon">🚚</span><div><strong><?php esc_html_e( 'ارسال سریع', 'lylyrose' ); ?></strong><small><?php esc_html_e( 'تحویل اکسپرس در تهران', 'lylyrose' ); ?></small></div></div>
            <div class="dk-service-item"><span class="dk-service-icon">🛡️</span><div><strong><?php esc_html_e( 'ضمانت اصالت', 'lylyrose' ); ?></strong><small><?php esc_html_e( '۱۰۰٪ اورجینال', 'lylyrose' ); ?></small></div></div>
            <div class="dk-service-item"><span class="dk-service-icon">↩️</span><div><strong><?php esc_html_e( '۷ روز بازگشت', 'lylyrose' ); ?></strong><small><?php esc_html_e( 'بدون قید و شرط', 'lylyrose' ); ?></small></div></div>
            <div class="dk-service-item"><span class="dk-service-icon">💳</span><div><strong><?php esc_html_e( 'پرداخت در محل', 'lylyrose' ); ?></strong><small><?php esc_html_e( 'در تمام شهرها', 'lylyrose' ); ?></small></div></div>
        </section>
    </div>

    <!-- Stories -->
    <div class="dk-container">
        <section class="dk-story-row" aria-label="<?php echo esc_attr__( 'دسته‌بندی سریع', 'lylyrose' ); ?>">
            <a class="dk-story" href="<?php echo esc_url( $discount_url ); ?>">
                <span class="dk-story-ring"><span class="dk-story-img">🎁</span></span>
                <span><?php esc_html_e( 'شگفت‌انگیز', 'lylyrose' ); ?></span>
            </a>
            <?php foreach ( $dk_cats as $story_term ) :
                $url = get_term_link( $story_term );
                if ( is_wp_error( $url ) ) { continue; }
                $img = lylyrose_term_image( $story_term, 'woocommerce_gallery_thumbnail' );
                ?>
                <a class="dk-story" href="<?php echo esc_url( $url ); ?>">
                    <span class="dk-story-ring"><span class="dk-story-img">
                        <?php if ( $img ) : ?>
                            <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $story_term->name ); ?>" loading="lazy" width="72" height="72">
                        <?php else : ?>
                            <?php echo esc_html( mb_substr( $story_term->name, 0, 1 ) ); ?>
                        <?php endif; ?>
                    </span></span>
                    <span><?php echo esc_html( $story_term->name ); ?></span>
                </a>
            <?php endforeach; ?>
        </section>
    </div>

    <!-- Incredible offers -->
    <?php if ( $sale && $sale->have_posts() ) : ?>
    <div class="dk-container">
        <section class="dk-offers" aria-label="<?php echo esc_attr__( 'پیشنهاد شگفت‌انگیز', 'lylyrose' ); ?>">
            <div class="dk-offers-side">
                <svg viewBox="0 0 24 24"><path d="M20 7h-3.1c-.4-1.2-1.5-2-2.9-2H6.9L4 3.6C3.4 3 2.4 3.1 2 3.9c-.3.5-.3 1.2.1 1.7L3.4 7H3c-1.1 0-2 .9-2 2v6c0 1.1.9 2 2 2h1v3c0 .6.4 1 1 1h1c.6 0 1-.4 1-1v-3h8v3c0 .6.4 1 1 1h1c.6 0 1-.4 1-1v-3h2c1.1 0 2-.9 2-2v-4c0-2.8-2.2-5-5-5z"/></svg>
                <span class="dk-offers-title"><?php esc_html_e( 'پیشنهاد', 'lylyrose' ); ?><br><?php esc_html_e( 'شگفت‌انگیز', 'lylyrose' ); ?></span>
                <div class="dk-countdown" data-dk-countdown="<?php echo esc_attr( ( 86400 - ( current_time( 'timestamp' ) % 86400 ) ) ); ?>">
                    <span class="dk-cd-box" data-cd="h">۰۰</span>
                    <span class="dk-cd-sep">:</span>
                    <span class="dk-cd-box" data-cd="m">۰۰</span>
                    <span class="dk-cd-sep">:</span>
                    <span class="dk-cd-box" data-cd="s">۰۰</span>
                </div>
                <a class="dk-offers-more" href="<?php echo esc_url( $discount_url ); ?>"><?php esc_html_e( 'مشاهده همه ‹', 'lylyrose' ); ?></a>
            </div>
            <div class="dk-offers-rail">
                <?php while ( $sale->have_posts() ) : $sale->the_post(); global $product; if ( ! $product ) { continue; }
                $percent = lylyrose_discount_percent( $product ); ?>
                <a class="dk-offer-card" href="<?php the_permalink(); ?>">
                    <div class="dk-offer-img"><?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?></div>
                    <?php if ( $percent ) : ?><span class="dk-offer-discount"><?php echo esc_html( lylyrose_fa_num( $percent ) ); ?>٪</span><?php endif; ?>
                    <div class="dk-offer-meta">
                        <span class="dk-offer-price-old"><?php echo $product->is_on_sale() ? wp_kses_post( wc_price( $product->get_regular_price() ) ) : ''; ?></span>
                        <span class="dk-offer-price"><b><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></b></span>
                        <span class="dk-progress"><i></i></span>
                    </div>
                </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
    </div>
    <?php endif; ?>

    <!-- Banners -->
    <div class="dk-container">
        <section class="dk-banner-grid" aria-label="<?php echo esc_attr__( 'پیشنهادهای ویژه', 'lylyrose' ); ?>">
            <?php foreach ( $banners as $banner ) : ?>
                <a class="dk-banner dk-banner-tone-<?php echo esc_attr( $banner['tone'] ); ?>" href="<?php echo esc_url( $banner['url'] ); ?>">
                    <span class="emoji"><?php echo esc_html( $banner['emoji'] ); ?></span>
                    <b><?php echo esc_html( __( $banner['title'], 'lylyrose' ) ); ?></b>
                    <small><?php echo esc_html( __( $banner['sub'], 'lylyrose' ) ); ?></small>
                </a>
            <?php endforeach; ?>
        </section>
    </div>

    <!-- Latest products -->
    <div class="dk-container">
        <section>
            <div class="dk-section-head">
                <h2><?php esc_html_e( 'پرفروش‌ترین کالاها', 'lylyrose' ); ?></h2>
                <a class="dk-section-more" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'مشاهده همه ‹', 'lylyrose' ); ?></a>
            </div>
            <div class="dk-rail">
                <div class="dk-rail-grid">
                    <?php
                    if ( $latest->have_posts() ) :
                        while ( $latest->have_posts() ) : $latest->the_post(); global $product; if ( ! $product ) { continue; }
                            wc_get_template_part( 'content', 'product' );
                        endwhile;
                        wp_reset_postdata();
                    else : ?>
                        <div style="padding:24px;grid-column:1/-1;"><?php esc_html_e( 'محصولی برای نمایش وجود ندارد.', 'lylyrose' ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <!-- Category circles -->
    <div class="dk-container">
        <section>
            <div class="dk-section-head">
                <h2><?php esc_html_e( 'خرید بر اساس دسته‌بندی', 'lylyrose' ); ?></h2>
                <a class="dk-section-more" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'همه دسته‌ها ‹', 'lylyrose' ); ?></a>
            </div>
            <div class="dk-catgrid">
                <?php foreach ( array_slice( $dk_cats, 0, 6 ) as $term ) :
                    $url = get_term_link( $term );
                    if ( is_wp_error( $url ) ) { continue; }
                    $img = lylyrose_term_image( $term );
                    ?>
                    <a class="dk-catcard" href="<?php echo esc_url( $url ); ?>">
                        <span class="dk-catcard-img">
                            <?php if ( $img ) : ?>
                                <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $term->name ); ?>" loading="lazy" width="110" height="110">
                            <?php else : ?>
                                <?php echo esc_html( mb_substr( $term->name, 0, 1 ) ); ?>
                            <?php endif; ?>
                        </span>
                        <strong><?php echo esc_html( $term->name ); ?></strong>
                        <span class="dk-catcard-count"><?php echo esc_html( lylyrose_fa_num( $term->count ) ); ?> <?php esc_html_e( 'کالا', 'lylyrose' ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <!-- Brand strip -->
    <?php if ( $dk_brands ) : ?>
    <div class="dk-container">
        <section>
            <div class="dk-section-head">
                <h2><?php esc_html_e( 'برندهای محبوب', 'lylyrose' ); ?></h2>
                <a class="dk-section-more" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'همه برندها ‹', 'lylyrose' ); ?></a>
            </div>
            <div class="dk-brand-strip">
                <?php foreach ( $dk_brands as $brand ) : ?>
                    <a class="dk-brandcell" href="<?php echo esc_url( add_query_arg( 'dk_brands[]', $brand->term_id, $shop_url ) ); ?>">
                        <span class="dk-brandcell-name"><?php echo esc_html( $brand->name ); ?></span>
                        <span class="dk-brandcell-count"><?php echo esc_html( lylyrose_fa_num( $brand->count ) ); ?> <?php esc_html_e( 'کالا', 'lylyrose' ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <?php endif; ?>

    <!-- Instagram strip (P2 #14) -->
    <?php if ( class_exists( 'ASC_Instagram' ) ) { ASC_Instagram::render(); } ?>

    <!-- Magazine -->
    <div class="dk-container">
        <section>
            <div class="dk-section-head">
                <h2><?php esc_html_e( 'مجله و راهنمای خرید', 'lylyrose' ); ?></h2>
            </div>
            <div class="dk-editorial">
                <?php foreach ( $editorial as $post_card ) : ?>
                    <div class="dk-editorial-card">
                        <?php if ( ! empty( $post_card['thumb'] ) ) : ?>
                            <a class="dk-editorial-thumb" href="<?php echo esc_url( $post_card['url'] ); ?>">
                                <img src="<?php echo esc_url( $post_card['thumb'] ); ?>" alt="<?php echo esc_attr( $post_card['title'] ); ?>" loading="lazy">
                            </a>
                        <?php endif; ?>
                        <span class="dk-editorial-tag"><?php echo esc_html( __( $post_card['tag'], 'lylyrose' ) ); ?></span>
                        <h3><?php echo esc_html( __( $post_card['title'], 'lylyrose' ) ); ?></h3>
                        <p><?php echo esc_html( __( $post_card['text'], 'lylyrose' ) ); ?></p>
                        <a class="dk-section-more" href="<?php echo esc_url( $post_card['url'] ); ?>"><?php esc_html_e( 'ادامه مطلب ‹', 'lylyrose' ); ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

</main>
<script>
(function () {
    var el = document.querySelector('[data-dk-countdown]');
    if (!el) { return; }
    var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    var boxes = {
        h: el.querySelector('[data-cd="h"]'),
        m: el.querySelector('[data-cd="m"]'),
        s: el.querySelector('[data-cd="s"]')
    };
    var left = parseInt(el.getAttribute('data-dk-countdown'), 10) || 0;
    function pad(n) {
        return String(n).padStart(2, '0').replace(/\d/g, function (d) { return fa[+d]; });
    }
    function tick() {
        if (left < 0) { left = 86400; }
        boxes.h.textContent = pad(Math.floor(left / 3600));
        boxes.m.textContent = pad(Math.floor((left % 3600) / 60));
        boxes.s.textContent = pad(left % 60);
        left--;
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
<?php get_footer(); ?>
