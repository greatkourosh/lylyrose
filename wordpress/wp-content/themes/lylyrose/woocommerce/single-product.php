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
$discount     = $product ? lylyrose_discount_percent( $product ) : 0;
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
$stock_label = $product ? ( $product->is_in_stock() ? __( 'موجود در انبار فروشنده', 'lylyrose' ) : __( 'ناموجود', 'lylyrose' ) ) : '';

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
                        <span class="dk-brand-label"><?php esc_html_e( 'برند:', 'lylyrose' ); ?></span>
                        <span class="dk-brand-value"><?php echo esc_html( $brand_name ); ?></span>
                    </div>
                <?php endif; ?>

                <div class="dk-single-rating">
                    <?php if ( $product && $product->get_review_count() > 0 ) : ?>
                        <span class="dk-stars">&#9733;</span>
                        <span><?php echo esc_html( lylyrose_fa_num( number_format_i18n( $product->get_average_rating(), 1 ) ) ); ?></span>
                        <span><?php echo sprintf( esc_html__( '(%s نظر)', 'lylyrose' ), esc_html( lylyrose_fa_num( $product->get_review_count() ) ) ); ?></span>
                    <?php else : ?>
                        <span><?php esc_html_e( 'هنوز نظری ثبت نشده است', 'lylyrose' ); ?></span>
                    <?php endif; ?>
                    <span>|</span>
                    <span><?php esc_html_e( 'کد کالا:', 'lylyrose' ); ?> <span class="dk-code"><?php echo esc_html( $product_code ); ?></span></span>
                </div>

                <?php if ( $discount > 0 ) : ?>
                    <div class="dk-off-row">
                        <span class="dk-badge-off"><?php echo esc_html( lylyrose_fa_num( $discount ) ); ?>٪</span>
                        <span class="dk-off-label"><?php esc_html_e( 'تخفیف ویژه', 'lylyrose' ); ?></span>
                    </div>
                <?php endif; ?>

                <div class="dk-single-desc">
                    <?php echo wp_kses_post( apply_filters( 'woocommerce_short_description', $post->post_excerpt ) ); ?>
                </div>

                <?php
                if ( class_exists( 'ASC_Fragrance_Notes' ) ) {
                    ASC_Fragrance_Notes::render_pyramid();
                }
                ?>

                <a class="dk-section-more" href="#description"><?php esc_html_e( 'مشاهده توضیحات کامل ‹', 'lylyrose' ); ?></a>
            </div>

            <!-- Buy box -->
            <aside class="dk-buybox" id="buybox">
                <?php if ( $product ) : ?>
                    <div class="dk-seller">
                        <span class="dk-seller-dot"></span>
                        <span><?php esc_html_e( 'فروشنده', 'lylyrose' ); ?></span>
                        <a class="dk-seller-name" href="#"><?php esc_html_e( 'فروشگاه اصلی', 'lylyrose' ); ?></a>
                        <?php $seller_pct = max( 0, min( 100, $rate_pct ? $rate_pct : 82 ) ); ?>
                        <span class="dk-seller-grade" style="background:<?php echo esc_attr( $seller_pct >= 80 ? '#00a049' : '#f9a825' ); ?>">
                            <?php echo sprintf( esc_html__( 'عملکرد: %s٪', 'lylyrose' ), esc_html( lylyrose_fa_num( $seller_pct ) ) ); ?>
                        </span>
                    </div>

                    <div class="dk-price-row">
                        <?php wc_get_template( 'single-product/price.php' ); ?>
                        <?php if ( $discount > 0 ) : ?>
                            <span class="dk-badge-off"><?php echo esc_html( lylyrose_fa_num( $discount ) ); ?>٪</span>
                        <?php endif; ?>
                    </div>

                    <div class="dk-stock <?php echo $product->is_in_stock() ? 'in' : 'out'; ?>">
                        <?php
                        if ( null !== $stock_qty && $product->is_in_stock() ) {
                            echo sprintf( esc_html__( '%s عدد در انبار', 'lylyrose' ), esc_html( lylyrose_fa_num( $stock_qty ) ) );
                        } else {
                            echo esc_html( $stock_label );
                        }
                        ?>
                    </div>

                    <?php if ( $product && ! $product->is_in_stock() && class_exists( 'ASC_Stock_Notifier' ) ) :
                        $sn_user_id    = get_current_user_id();
                        $sn_mobile     = $sn_user_id ? ASC_OTP::normalize_mobile( get_user_meta( $sn_user_id, 'billing_phone', true ) ) : '';
                        $sn_subscribed = $sn_mobile ? (bool) ASC_Stock_Notifier::is_subscribed( $sn_mobile, $product->get_id() ) : false;
                    ?>
                        <div class="dk-stock-notify" id="dk-stock-notify"
                            data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'asc_stock_subscribe' ) ); ?>">
                            <?php if ( $sn_subscribed ) : ?>
                                <div class="dk-stock-notify-done"><?php esc_html_e( 'شما ثبت‌نام کرده‌اید. به محض موجود شدن کالا، اطلاع‌رسانی می‌شود.', 'lylyrose' ); ?></div>
                            <?php else : ?>
                                <div class="dk-stock-notify-head"><?php esc_html_e( 'موجود شد به من خبر بده', 'lylyrose' ); ?></div>
                                <form class="dk-stock-notify-form" method="post">
                                    <?php if ( ! $sn_mobile ) : /* guests + logged-in users without billing_phone */ ?>
                                        <input type="tel" class="dk-stock-notify-input" name="phone"
                                            placeholder="<?php echo esc_attr__( 'شماره موبایل', 'lylyrose' ); ?>" maxlength="11" autocomplete="tel" dir="ltr" />
                                    <?php endif; ?>
                                    <button type="submit" class="dk-btn dk-btn-primary dk-stock-notify-btn"><?php esc_html_e( 'ثبت‌نام', 'lylyrose' ); ?></button>
                                </form>
                                <div class="dk-stock-notify-msg" hidden></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $digiclub_points > 0 ) : ?>
                        <div class="dk-digiclub">
                            <span class="dk-digiclub-ic">◆</span>
                            <?php echo sprintf( esc_html__( 'با خرید این کالا %s امتیاز باشگاه مشتریان دریافت می‌کنید', 'lylyrose' ), esc_html( lylyrose_fa_num( $digiclub_points ) ) ); ?>
                        </div>
                    <?php endif; ?>

                    <?php woocommerce_template_single_add_to_cart(); ?>

                    <div class="dk-buybox-trust">
                        <div>🛡️ <?php esc_html_e( 'ضمانت اصل بودن کالا', 'lylyrose' ); ?></div>
                        <div>🚚 <?php esc_html_e( 'ارسال سریع به سراسر ایران', 'lylyrose' ); ?></div>
                        <div>↩️ <?php esc_html_e( '۷ روز ضمانت بازگشت', 'lylyrose' ); ?></div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <!-- Quick specs strip -->
    <div class="dk-spec-card">
        <div><span class="dk-spec-ic">🛡️</span><div><small><?php esc_html_e( 'گارانتی', 'lylyrose' ); ?></small><span><?php esc_html_e( 'ضمانت اصالت و سلامت فیزیکی کالا', 'lylyrose' ); ?></span></div></div>
        <div><span class="dk-spec-ic">🚚</span><div><small><?php esc_html_e( 'ارسال از', 'lylyrose' ); ?></small><span><?php esc_html_e( 'یک روز کاری آینده', 'lylyrose' ); ?></span></div></div>
        <div><span class="dk-spec-ic">📦</span><div><small><?php esc_html_e( 'موجودی', 'lylyrose' ); ?></small><span><?php echo esc_html( $stock_label ); ?></span></div></div>
        <?php if ( $rate_pct > 0 ) : ?>
            <div><span class="dk-spec-ic">😊</span><div><small><?php esc_html_e( 'رضایت کاربران', 'lylyrose' ); ?></small><span><?php echo sprintf( esc_html__( '%s٪ از خریداران راضی بوده‌اند', 'lylyrose' ), esc_html( lylyrose_fa_num( $rate_pct ) ) ); ?></span></div></div>
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
            <h3><?php esc_html_e( 'امتیاز و نظرات کاربران', 'lylyrose' ); ?></h3>
            <?php if ( $review_total > 0 && $product ) : ?>
                <div class="dk-hist-summary">
                    <span class="dk-hist-avg"><?php echo esc_html( lylyrose_fa_num( number_format_i18n( $product->get_average_rating(), 1 ) ) ); ?></span>
                    <span class="dk-stars">&#9733;</span>
                    <span class="dk-muted">(<?php echo sprintf( esc_html__( '(%s نظر)', 'lylyrose' ), esc_html( lylyrose_fa_num( $review_total ) ) ); ?></span>
                    <?php if ( $rate_pct > 0 ) : ?>
                        <span class="dk-rate-badge"><?php echo sprintf( esc_html__( '%s٪ رضایت', 'lylyrose' ), esc_html( lylyrose_fa_num( $rate_pct ) ) ); ?></span>
                    <?php endif; ?>
                </div>
                <?php foreach ( array( 5, 4, 3, 2, 1 ) as $stars ) :
                    $pct = $review_total > 0 ? round( $hist[ $stars ] / $review_total * 100 ) : 0; ?>
                    <div class="dk-hist-row">
                        <span class="dk-hist-label"><?php echo sprintf( esc_html__( '%s ستاره', 'lylyrose' ), esc_html( lylyrose_fa_num( $stars ) ) ); ?></span>
                        <div class="dk-hist-track"><span class="dk-hist-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></span></div>
                        <span class="dk-hist-num"><?php echo esc_html( lylyrose_fa_num( $hist[ $stars ] ) ); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if ( $review_comments ) : ?>
                    <div class="dk-comments-list">
                        <?php foreach ( $review_comments as $rc ) : ?>
                            <?php
                            $rc_rating = (int) get_comment_meta( $rc->comment_ID, 'rating', true );
                            $rc_author = $rc->comment_author ? $rc->comment_author : __( 'کاربر', 'lylyrose' );
                            ?>
                            <div class="dk-comment">
                                <div class="dk-comment-head">
                                    <span class="dk-comment-buyer"><span class="dk-check is-checked"></span> <?php esc_html_e( 'خریدار', 'lylyrose' ); ?></span>
                                    <span class="dk-comment-author"><?php echo esc_html( $rc_author ); ?></span>
                                    <span class="dk-comment-date"><?php echo esc_html( lylyrose_fa_num( get_comment_date( 'Y/m/d', $rc ) ) ); ?></span>
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
                <p class="dk-extras-empty"><?php esc_html_e( 'هنوز نظری ثبت نشده است؛ اولین نفر باشید.', 'lylyrose' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="dk-qa-card">
            <h3><?php esc_html_e( 'پرسش و پاسخ', 'lylyrose' ); ?></h3>
            <p class="dk-extras-empty"><?php esc_html_e( 'هنوز پرسشی ثبت نشده است.', 'lylyrose' ); ?></p>
            <a class="dk-section-more" href="#description"><?php esc_html_e( 'پرسش خود را بپرسید ‹', 'lylyrose' ); ?></a>
        </div>
    </div>

    <!-- Frequently bought together -->
    <?php
    if ( $product && class_exists( 'ASC_Frequently_Bought' ) ) {
        $fbt_ids = ASC_Frequently_Bought::get_partners( $product->get_id() );
        if ( $fbt_ids ) {
            $fbt_partner = wc_get_product( $fbt_ids[0] );
            ?>
            <div class="dk-fbt">
                <h3 class="dk-fbt-title"><?php esc_html_e( 'اکثراً با هم خریداری شده‌اند', 'lylyrose' ); ?></h3>
                <div class="dk-fbt-row">
                    <div class="dk-fbt-item">
                        <a href="<?php echo esc_url( $product->get_permalink() ); ?>">
                            <?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
                        </a>
                    </div>
                    <span class="dk-fbt-plus">+</span>
                    <div class="dk-fbt-item">
                        <a href="<?php echo esc_url( $fbt_partner ? $fbt_partner->get_permalink() : '#' ); ?>">
                            <?php echo wp_kses_post( $fbt_partner ? $fbt_partner->get_image( 'woocommerce_thumbnail' ) : '' ); ?>
                        </a>
                    </div>
                    <div class="dk-fbt-buy">
                        <?php if ( $fbt_partner ) : ?>
                            <div class="dk-fbt-price">
                                <?php echo wp_kses_post( $product->get_price_html() ); ?> <span class="dk-fbt-plus-inline">+</span> <?php echo wp_kses_post( $fbt_partner->get_price_html() ); ?>
                            </div>
                            <form method="post" action="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dk-fbt-form">
                                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="dk-fbt-btn"><?php esc_html_e( 'خرید هر دو', 'lylyrose' ); ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( count( $fbt_ids ) > 1 ) : ?>
                    <div class="dk-fbt-more">
                        <?php foreach ( array_slice( $fbt_ids, 1 ) as $fbt_pid ) :
                            $fbt_p = wc_get_product( $fbt_pid );
                            if ( ! $fbt_p || ! $fbt_p->is_visible() ) { continue; } ?>
                            <div class="dk-fbt-card">
                                <a href="<?php echo esc_url( $fbt_p->get_permalink() ); ?>">
                                    <?php echo wp_kses_post( $fbt_p->get_image( 'woocommerce_thumbnail' ) ); ?>
                                    <span class="dk-fbt-card-name"><?php echo esc_html( $fbt_p->get_name() ); ?></span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    ?>

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
                <span class="dk-badge-off dk-badge-off-sm"><?php echo esc_html( lylyrose_fa_num( $discount ) ); ?>٪</span>
            <?php endif; ?>
            <?php echo wp_kses_post( $product->get_price_html() ); ?>
        </div>
        <?php if ( ! $is_variable ) : ?>
            <form method="post" action="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dk-mobile-bar-form">
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="dk-mobile-bar-btn"><?php esc_html_e( 'افزودن به سبد', 'lylyrose' ); ?></button>
            </form>
        <?php else : ?>
            <a class="dk-mobile-bar-btn" href="#buybox"><?php esc_html_e( 'مشاهده و خرید', 'lylyrose' ); ?></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php endwhile; // end single product loop — bar markup above stays inside. ?>
</div>

<?php get_footer(); ?>
