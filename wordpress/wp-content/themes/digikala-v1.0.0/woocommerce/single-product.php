<?php
/**
 * Single product — Digikala-style: gallery, info column, sticky buy box,
 * specs strip, reviews histogram, Q&A card, mobile purchase bar.
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;

get_header();

$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/shop/' );

while ( have_posts() ) : the_post(); global $product;

$product_code = ( $product && class_exists( 'ASC_Product_Code' ) ) ? ASC_Product_Code::get_code( $product ) : ( $product ? $product->get_sku() : '' );
$discount     = $product ? digikala_discount_percent( $product ) : 0;
$is_variable  = $product && $product->is_type( 'variable' );

// Latin subtitle: leading Latin words from the short excerpt, if any.
$subtitle = '';
if ( $post->post_excerpt && preg_match( '/^\s*([A-Za-z0-9][A-Za-z0-9 \-\.\&\/]{3,})/u', $post->post_excerpt, $m_sub ) ) {
	$subtitle = trim( $m_sub[1] );
}

// Review histogram data.
$hist = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
$review_total = 0;
if ( $product && $product->get_review_count() > 0 ) {
	foreach ( get_comments( array( 'post_id' => $product->get_id(), 'status' => 'approve', 'type' => 'review' ) ) as $c_rev ) {
		$r = (int) get_comment_meta( $c_rev->comment_ID, 'rating', true );
		if ( isset( $hist[ $r ] ) ) {
			$hist[ $r ]++;
			$review_total++;
		}
	}
}

$stock_qty   = $product && $product->managing_stock() ? $product->get_stock_quantity() : null;
$stock_label = $product ? ( $product->is_in_stock() ? 'موجود در انبار فروشنده' : 'ناموجود' ) : '';

// Brand (pa_brand) — Digikala shows برند under the title.
$brand_name = '';
if ( $product ) {
	$brand_terms = get_the_terms( $product->get_id(), 'pa_brand' );
	if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
		$brand_name = $brand_terms[0]->name;
	}
}

// Satisfaction percent, Digikala-style: (Σrating / 5×count) × 100.
$rate_pct = 0;
if ( $review_total > 0 && $product ) {
	$rate_pct = round( ( ( 5 * $hist[5] + 4 * $hist[4] + 3 * $hist[3] + 2 * $hist[2] + 1 * $hist[1] ) / ( 5 * $review_total ) ) * 100 );
}

// Digiclub points mirror Digikala's scale (~150 pts for a 10M-toman item).
$price_toman     = $product ? (float) $product->get_price() : 0;
$digiclub_points = $price_toman > 0 ? (int) floor( $price_toman / 66600 ) : 0;

// Full reviews (body, date, buyer) — Digikala lists them under the histogram.
$review_comments = $product && $product->get_review_count() > 0
	? get_comments( array( 'post_id' => $product->get_id(), 'status' => 'approve', 'type' => 'review', 'number' => 10 ) )
	: array();
?>
<div class="dk-container">
    <?php if ( function_exists( 'woocommerce_breadcrumb' ) ) : ?>
        <nav class="dk-breadcrumb"><?php woocommerce_breadcrumb(); ?></nav>
    <?php endif; ?>

    <div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'dk-single', $product ); ?>>
        <div class="dk-single-layout">

            <!-- Gallery -->
            <div class="dk-gallery">
                <?php
                if ( function_exists( 'woocommerce_show_product_images' ) ) {
                    do_action( 'woocommerce_before_single_product_summary' );
                }
                ?>
            </div>

            <!-- Info -->
            <div class="dk-single-info">
                <h1 class="product_title entry-title"><?php the_title(); ?></h1>

                <?php if ( $subtitle ) : ?>
                    <div class="dk-subtitle" dir="ltr"><?php echo esc_html( $subtitle ); ?></div>
                <?php endif; ?>

                <?php if ( $brand_name ) : ?>
                    <div class="dk-brand-row">
                        <span class="dk-brand-label">برند:</span>
                        <span class="dk-brand-value"><?php echo esc_html( $brand_name ); ?></span>
                    </div>
                <?php endif; ?>

                <div class="dk-single-rating">
                    <?php if ( $product && $product->get_review_count() > 0 ) : ?>
                        <span class="dk-stars">&#9733;</span>
                        <span><?php echo esc_html( digikala_fa_num( number_format_i18n( $product->get_average_rating(), 1 ) ) ); ?></span>
                        <span>(<?php echo esc_html( digikala_fa_num( $product->get_review_count() ) ); ?> نظر)</span>
                    <?php else : ?>
                        <span>هنوز نظری ثبت نشده است</span>
                    <?php endif; ?>
                    <span>|</span>
                    <span>کد کالا: <span class="dk-code"><?php echo esc_html( $product_code ); ?></span></span>
                </div>

                <?php if ( $discount > 0 ) : ?>
                    <div class="dk-off-row">
                        <span class="dk-badge-off"><?php echo esc_html( digikala_fa_num( $discount ) ); ?>٪</span>
                        <span class="dk-off-label">تخفیف ویژه</span>
                    </div>
                <?php endif; ?>

                <div class="dk-single-desc">
                    <?php echo wp_kses_post( apply_filters( 'woocommerce_short_description', $post->post_excerpt ) ); ?>
                </div>

                <a class="dk-section-more" href="#description">مشاهده توضیحات کامل ‹</a>
            </div>

            <!-- Buy box -->
            <aside class="dk-buybox" id="buybox">
                <?php if ( $product ) : ?>
                    <div class="dk-seller">
                        <span class="dk-seller-dot"></span>
                        <span>فروشنده</span>
                        <a class="dk-seller-name" href="#">فروشگاه اصلی</a>
                        <?php $seller_pct = max( 0, min( 100, $rate_pct ? $rate_pct : 82 ) ); ?>
                        <span class="dk-seller-grade" style="background:<?php echo esc_attr( $seller_pct >= 80 ? '#00a049' : '#f9a825' ); ?>">
                            عملکرد: <?php echo esc_html( digikala_fa_num( $seller_pct ) ); ?>٪
                        </span>
                    </div>

                    <div class="dk-price-row">
                        <?php wc_get_template( 'single-product/price.php' ); ?>
                        <?php if ( $discount > 0 ) : ?>
                            <span class="dk-badge-off"><?php echo esc_html( digikala_fa_num( $discount ) ); ?>٪</span>
                        <?php endif; ?>
                    </div>

                    <div class="dk-stock <?php echo $product->is_in_stock() ? 'in' : 'out'; ?>">
                        <?php
                        if ( null !== $stock_qty && $product->is_in_stock() ) {
                            echo esc_html( digikala_fa_num( $stock_qty ) . ' عدد در انبار' );
                        } else {
                            echo esc_html( $stock_label );
                        }
                        ?>
                    </div>

                    <?php if ( $digiclub_points > 0 ) : ?>
                        <div class="dk-digiclub">
                            <span class="dk-digiclub-ic">◆</span>
                            با خرید این کالا <?php echo esc_html( digikala_fa_num( $digiclub_points ) ); ?> امتیاز باشگاه مشتریان دریافت می‌کنید
                        </div>
                    <?php endif; ?>

                    <?php woocommerce_template_single_add_to_cart(); ?>

                    <div class="dk-buybox-trust">
                        <div>🛡️ ضمانت اصل بودن کالا</div>
                        <div>🚚 ارسال سریع به سراسر ایران</div>
                        <div>↩️ ۷ روز ضمانت بازگشت</div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <!-- Quick specs strip -->
    <div class="dk-spec-card">
        <div><span class="dk-spec-ic">🛡️</span><div><small>گارانتی</small><span>ضمانت اصالت و سلامت فیزیکی کالا</span></div></div>
        <div><span class="dk-spec-ic">🚚</span><div><small>ارسال از</small><span>یک روز کاری آینده</span></div></div>
        <div><span class="dk-spec-ic">📦</span><div><small>موجودی</small><span><?php echo esc_html( $stock_label ); ?></span></div></div>
        <?php if ( $rate_pct > 0 ) : ?>
            <div><span class="dk-spec-ic">😊</span><div><small>رضایت کاربران</small><span><?php echo esc_html( digikala_fa_num( $rate_pct ) ); ?>٪ از خریداران راضی بوده‌اند</span></div></div>
        <?php endif; ?>
    </div>

    <!-- Tabs (description / reviews / attributes) -->
    <div class="dk-tabs" id="description">
        <?php if ( function_exists( 'woocommerce_output_product_data_tabs' ) ) {
            woocommerce_output_product_data_tabs();
        } ?>
    </div>

    <!-- Reviews + Q&A -->
    <div class="dk-extras">
        <div class="dk-review-card">
            <h3>امتیاز و نظرات کاربران</h3>
            <?php if ( $review_total > 0 && $product ) : ?>
                <div class="dk-hist-summary">
                    <span class="dk-hist-avg"><?php echo esc_html( digikala_fa_num( number_format_i18n( $product->get_average_rating(), 1 ) ) ); ?></span>
                    <span class="dk-stars">&#9733;</span>
                    <span class="dk-muted">(<?php echo esc_html( digikala_fa_num( $review_total ) ); ?> نظر)</span>
                    <?php if ( $rate_pct > 0 ) : ?>
                        <span class="dk-rate-badge"><?php echo esc_html( digikala_fa_num( $rate_pct ) ); ?>٪ رضایت</span>
                    <?php endif; ?>
                </div>
                <?php foreach ( array( 5, 4, 3, 2, 1 ) as $stars ) :
                    $pct = $review_total > 0 ? round( $hist[ $stars ] / $review_total * 100 ) : 0; ?>
                    <div class="dk-hist-row">
                        <span class="dk-hist-label"><?php echo esc_html( digikala_fa_num( $stars ) ); ?> ستاره</span>
                        <div class="dk-hist-track"><span class="dk-hist-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></span></div>
                        <span class="dk-hist-num"><?php echo esc_html( digikala_fa_num( $hist[ $stars ] ) ); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if ( $review_comments ) : ?>
                    <div class="dk-comments-list">
                        <?php foreach ( $review_comments as $rc ) : ?>
                            <?php
                            $rc_rating = (int) get_comment_meta( $rc->comment_ID, 'rating', true );
                            $rc_author = $rc->comment_author ? $rc->comment_author : 'کاربر';
                            ?>
                            <div class="dk-comment">
                                <div class="dk-comment-head">
                                    <span class="dk-comment-buyer"><span class="dk-check is-checked"></span> خریدار</span>
                                    <span class="dk-comment-author"><?php echo esc_html( $rc_author ); ?></span>
                                    <span class="dk-comment-date"><?php echo esc_html( digikala_fa_num( get_comment_date( 'Y/m/d', $rc ) ) ); ?></span>
                                </div>
                                <?php if ( $rc_rating > 0 ) : ?>
                                    <div class="dk-comment-stars" aria-label="<?php echo esc_attr( $rc_rating ); ?>">
                                        <?php echo esc_html( str_repeat( '★', $rc_rating ) . str_repeat( '☆', 5 - $rc_rating ) ); ?>
                                    </div>
                                <?php endif; ?>
                                <p class="dk-comment-body"><?php echo esc_html( $rc->comment_content ); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <p class="dk-extras-empty">هنوز نظری ثبت نشده است؛ اولین نفر باشید.</p>
            <?php endif; ?>
        </div>

        <div class="dk-qa-card">
            <h3>پرسش و پاسخ</h3>
            <p class="dk-extras-empty">هنوز پرسشی ثبت نشده است.</p>
            <a class="dk-section-more" href="#description">پرسش خود را بپرسید ‹</a>
        </div>
    </div>

    <!-- Related products -->
    <?php
    if ( function_exists( 'woocommerce_output_related_products' ) ) {
        echo '<div class="dk-related">';
        woocommerce_output_related_products();
        echo '</div>';
    }
    ?>

    <!-- Mobile purchase bar -->
    <?php if ( $product && $product->is_in_stock() ) : ?>
    <div class="dk-mobile-bar">
        <div class="dk-mobile-bar-info">
            <?php if ( $discount > 0 ) : ?>
                <span class="dk-badge-off dk-badge-off-sm"><?php echo esc_html( digikala_fa_num( $discount ) ); ?>٪</span>
            <?php endif; ?>
            <?php echo wp_kses_post( $product->get_price_html() ); ?>
        </div>
        <?php if ( ! $is_variable ) : ?>
            <form method="post" action="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dk-mobile-bar-form">
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="dk-mobile-bar-btn">افزودن به سبد</button>
            </form>
        <?php else : ?>
            <a class="dk-mobile-bar-btn" href="#buybox">مشاهده و خرید</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php endwhile; // end single product loop — bar markup above stays inside. ?>
</div>

<?php get_footer(); ?>
