<?php
/**
 * Shop / category archive — Digikala-style: filter rail (price, availability,
 * discount, brand), sort bar with result count, category chips.
 *
 * All filters are URL-driven GET params (shareable/bookmarkable, like
 * digikala.com): sort, price_min, price_max, in_stock, discount, brands[].
 *
 * @package Digikala
 */

defined( 'ABSPATH' ) || exit;
get_header();

/**
 * Build a URL that keeps current filters and adds/replaces one param.
 * Params starting with '_' are internal.
 */
if ( ! function_exists( 'dk_filter_url' ) ) {
	function dk_filter_url( $changes = array() ) {
		$params = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $params['submit'], $params['_wpnonce'] );
		foreach ( $changes as $key => $value ) {
			if ( '' === $value || null === $value ) {
				unset( $params[ $key ] );
			} else {
				$params[ $key ] = $value;
			}
		}
		$base = is_product_taxonomy() ? get_term_link( get_queried_object() ) : get_permalink( wc_get_page_id( 'shop' ) );
		if ( is_wp_error( $base ) ) {
			$base = home_url( '/shop/' );
		}
		$query = http_build_query( $params );
		return $base . ( $query ? '?' . $query : '' );
	}
}

$dk_current_sort   = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$dk_price_min      = isset( $_GET['price_min'] ) ? max( 0, (int) $_GET['price_min'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$dk_price_max      = isset( $_GET['price_max'] ) ? max( 0, (int) $_GET['price_max'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$dk_in_stock       = ! empty( $_GET['in_stock'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$dk_has_discount   = ! empty( $_GET['discount'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$dk_brands         = isset( $_GET["dk_brands"] ) ? array_map( 'absint', (array) $_GET["dk_brands"] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$dk_sort_options = array(
	''        => 'پیش‌فرض',
	'popular' => 'پرفروش‌ترین',
	'newest'  => 'جدیدترین',
	'cheapest'=> 'ارزان‌ترین',
	'expensive'=> 'گران‌ترین',
	'discount'=> 'بیشترین تخفیف',
	'rating'  => 'پربازدید',
);

// Category chips (sibling categories of current term, or top-level).
$dk_cat_chips = array();
if ( is_product_taxonomy() ) {
	$term = get_queried_object();
	$siblings = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'parent'     => $term->parent ? $term->parent : 0,
		'exclude'    => array( get_option( 'default_product_cat' ) ),
	) );
	if ( ! is_wp_error( $siblings ) ) {
		$dk_cat_chips = $siblings;
	}
} else {
	$dk_cat_chips = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'parent'     => 0,
		'exclude'    => array( get_option( 'default_product_cat' ) ),
	) );
}

// Brand facets with counts (products in current category).
$dk_brand_terms = get_terms( array(
	'taxonomy'   => 'pa_brand',
	'hide_empty' => true,
	'orderby'    => 'count',
	'order'      => 'DESC',
) );

// Price bounds for slider labels (single MIN/MAX query, not full product load).
$dk_price_bounds = array( 0, 0 );
global $wpdb;
$dk_price_row = $wpdb->get_row(
	"SELECT MIN( CAST( pm.meta_value AS UNSIGNED ) ) AS lo, MAX( CAST( pm.meta_value AS UNSIGNED ) ) AS hi
	 FROM {$wpdb->postmeta} AS pm
	 INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
	 WHERE pm.meta_key = '_price' AND p.post_type = 'product' AND p.post_status = 'publish'"
);
if ( $dk_price_row && $dk_price_row->hi > 0 ) {
	$dk_price_bounds = array( (int) floor( $dk_price_row->lo / 10000 ) * 10000, (int) ceil( $dk_price_row->hi / 100000 ) * 100000 );
}
?>
<div class="dk-container">
    <?php if ( function_exists( 'woocommerce_breadcrumb' ) ) : ?>
        <nav class="dk-breadcrumb"><?php woocommerce_breadcrumb(); ?></nav>
    <?php endif; ?>

    <?php if ( is_product_taxonomy() ) :
        $term = get_queried_object();
        if ( $term && ! is_wp_error( $term ) ) :
            $term_desc = term_description( $term->term_id, $term->taxonomy );
            ?>
            <div class="dk-category-header">
                <h1><?php single_term_title(); ?></h1>
                <?php if ( $term_desc ) : ?>
                    <div class="dk-category-desc"><?php echo wp_kses_post( $term_desc ); ?></div>
                <?php endif; ?>
            </div>
        <?php endif;
    endif; ?>

    <?php if ( $dk_cat_chips && ! is_wp_error( $dk_cat_chips ) ) : ?>
        <div class="dk-cat-chips">
            <span class="dk-cat-chips-label">دسته‌بندی‌ها:</span>
            <?php foreach ( $dk_cat_chips as $chip ) : ?>
                <?php
                $chip_url = get_term_link( $chip );
                if ( is_wp_error( $chip_url ) ) { continue; }
                $chip_active = ( is_product_taxonomy() && get_queried_object_id() === $chip->term_id );
                ?>
                <a class="dk-cat-chip<?php echo $chip_active ? ' is-active' : ''; ?>"
                   href="<?php echo esc_url( $chip_url ); ?>"><?php echo esc_html( $chip->name ); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="dk-shop-layout">
        <aside class="dk-filters" id="dk-filters">
            <div class="dk-filters-head">
                <span class="dk-filters-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
                    فیلترها
                </span>
                <?php if ( $dk_current_sort || $dk_price_min || $dk_price_max || $dk_in_stock || $dk_has_discount || $dk_brands ) : ?>
                    <a class="dk-filters-clear" href="<?php echo esc_url( is_product_taxonomy() ? get_term_link( get_queried_object() ) : get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">حذف همه</a>
                <?php endif; ?>
            </div>

            <!-- Availability toggle -->
            <div class="dk-filter-group">
                <a class="dk-filter-toggle <?php echo $dk_in_stock ? 'is-active' : ''; ?>"
                   href="<?php echo esc_url( dk_filter_url( array( 'in_stock' => $dk_in_stock ? '' : '1' ) ) ); ?>">
                    <span class="dk-check<?php echo $dk_in_stock ? ' is-checked' : ''; ?>"></span>
                    فقط کالاهای موجود
                </a>
                <a class="dk-filter-toggle <?php echo $dk_has_discount ? 'is-active' : ''; ?>"
                   href="<?php echo esc_url( dk_filter_url( array( 'discount' => $dk_has_discount ? '' : '1' ) ) ); ?>">
                    <span class="dk-check<?php echo $dk_has_discount ? ' is-checked' : ''; ?>"></span>
                    فقط کالاهای تخفیف‌دار
                </a>
            </div>

            <!-- Brand filter -->
            <?php if ( $dk_brand_terms && ! is_wp_error( $dk_brand_terms ) ) : ?>
                <div class="dk-filter-group">
                    <h4>برند</h4>
                    <form method="get" class="dk-brand-form" id="dk-brand-form">
                        <?php
                        // Preserve other active filters in hidden fields.
                        if ( $dk_current_sort ) : ?><input type="hidden" name="sort" value="<?php echo esc_attr( $dk_current_sort ); ?>"><?php endif;
                        if ( $dk_price_min ) : ?><input type="hidden" name="price_min" value="<?php echo esc_attr( $dk_price_min ); ?>"><?php endif;
                        if ( $dk_price_max ) : ?><input type="hidden" name="price_max" value="<?php echo esc_attr( $dk_price_max ); ?>"><?php endif;
                        if ( $dk_in_stock ) : ?><input type="hidden" name="in_stock" value="1"><?php endif;
                        if ( $dk_has_discount ) : ?><input type="hidden" name="discount" value="1"><?php endif;
                        ?>
                        <ul class="dk-brand-list">
                            <?php foreach ( $dk_brand_terms as $brand_term ) : ?>
                                <li>
                                    <label class="dk-filter-toggle">
                                        <input type="checkbox" class="dk-brand-checkbox" name="dk_brands[]"
                                               value="<?php echo esc_attr( $brand_term->term_id ); ?>"
                                               <?php checked( in_array( (int) $brand_term->term_id, $dk_brands, true ) ); ?>
                                               data-count="<?php echo esc_attr( $brand_term->count ); ?>">
                                        <span class="dk-check"></span>
                                        <span class="dk-brand-name"><?php echo esc_html( $brand_term->name ); ?></span>
                                        <span class="dk-brand-count"><?php echo esc_html( digikala_fa_num( $brand_term->count ) ); ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Price range -->
            <?php if ( $dk_price_bounds[1] > 0 ) : ?>
                <div class="dk-filter-group">
                    <h4>محدوده قیمت (تومان)</h4>
                    <form method="get" class="dk-price-form">
                        <?php
                        if ( $dk_current_sort ) : ?><input type="hidden" name="sort" value="<?php echo esc_attr( $dk_current_sort ); ?>"><?php endif;
                        if ( $dk_in_stock ) : ?><input type="hidden" name="in_stock" value="1"><?php endif;
                        if ( $dk_has_discount ) : ?><input type="hidden" name="discount" value="1"><?php endif;
                        foreach ( $dk_brands as $b ) : ?>
                            <input type="hidden" name="dk_brands[]" value="<?php echo esc_attr( $b ); ?>">
                        <?php endforeach; ?>
                        <div class="dk-price-inputs">
                            <label>از
                                <input type="number" name="price_min" min="0"
                                       value="<?php echo esc_attr( $dk_price_min ? $dk_price_min : '' ); ?>"
                                       placeholder="<?php echo esc_attr( digikala_fa_num( $dk_price_bounds[0] ) ); ?>">
                            </label>
                            <label>تا
                                <input type="number" name="price_max" min="0"
                                       value="<?php echo esc_attr( $dk_price_max ? $dk_price_max : '' ); ?>"
                                       placeholder="<?php echo esc_attr( digikala_fa_num( $dk_price_bounds[1] ) ); ?>">
                            </label>
                        </div>
                        <button type="submit" class="dk-price-submit">اعمال</button>
                    </form>
                </div>
            <?php endif; ?>
        </aside>

        <div class="dk-products-grid">
            <div class="dk-toolbar">
                <span class="dk-toolbar-count">
                    <?php echo esc_html( digikala_fa_num( wc_get_loop_prop( 'total' ) ? wc_get_loop_prop( 'total' ) : 0 ) ); ?> کالا
                </span>
                <div class="dk-toolbar-order">
                    <span class="dk-sort-label">مرتب‌سازی:</span>
                    <select id="dk-sort-select">
                        <?php foreach ( $dk_sort_options as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( dk_filter_url( array( 'sort' => $key ?: '' ) ) ); ?>"
                                <?php selected( $dk_current_sort, $key ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ( woocommerce_product_loop() ) : ?>
                <?php woocommerce_product_loop_start(); ?>
                <?php if ( wc_get_loop_prop( 'total' ) ) : ?>
                    <?php while ( have_posts() ) : the_post(); ?>
                        <?php wc_get_template_part( 'content', 'product' ); ?>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php woocommerce_product_loop_end(); ?>
                <nav class="dk-pagination"><?php woocommerce_pagination(); ?></nav>
            <?php else : ?>
                <div class="dk-no-products">
                    <h2>کالایی مطابق فیلترهای شما یافت نشد</h2>
                    <p>می‌توانید فیلترها را تغییر دهید یا همه محصولات را ببینید.</p>
                    <a class="dk-btn dk-btn-primary" href="<?php echo esc_url( is_product_taxonomy() ? get_term_link( get_queried_object() ) : get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">حذف فیلترها</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var sortSelect = document.getElementById('dk-sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            if (this.value) { window.location.href = this.value; }
        });
    }
    // Auto-submit brand form on change (Digikala behavior).
    document.querySelectorAll('.dk-brand-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            document.getElementById('dk-brand-form').submit();
        });
    });
})();
</script>

<?php get_footer(); ?>
