<?php
/**
 * Perfume landing page (/perfume/) — hero, quick-access cards, scent families,
 * filter rail + product grid, finder banner, best-seller rail.
 *
 * The page owns its own WP_Query instead of riding the main query: the shop
 * filters in lylyrose_shop_filters() only run on the main query of an archive,
 * and this is a static page. Building the query here keeps the facet code in
 * one place and leaves /shop/ and the category archives untouched.
 *
 * Every filter is a GET param on this URL, so a filtered view is shareable.
 *
 * @package lylyrose
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	get_header();
	echo '<main class="dk-container"><p>' . esc_html__( 'فروشگاه در دسترس نیست.', 'lylyrose' ) . '</p></main>';
	get_footer();
	return;
}

$pf_page_id  = get_queried_object_id();
$pf_base_url = get_permalink( $pf_page_id );
$pf_cat      = get_term_by( 'slug', 'perfume', 'product_cat' );

/* -------------------------------------------------------------------------
   Selected facets, read once. The shapes mirror lylyrose_facet_selection():
   an array of positive term ids.
   ------------------------------------------------------------------------- */
$pf_tax_facets = array(
	'pf_gender'    => 'pa_gender',
	'pf_family'    => 'pa_fragrance_family',
	'pf_occasion'  => 'pa_occasion',
	'pf_longevity' => 'pa_longevity',
	'pf_brand'     => 'pa_brand',
);

$pf_selected = array();
foreach ( $pf_tax_facets as $pf_param => $pf_taxonomy ) {
	$pf_ids = isset( $_GET[ $pf_param ] ) ? array_map( 'absint', (array) wp_unslash( $_GET[ $pf_param ] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$pf_selected[ $pf_param ] = array_values( array_filter( $pf_ids ) );
}

$pf_sort   = isset( $_GET['pf_sort'] ) ? sanitize_key( wp_unslash( $_GET['pf_sort'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pf_stock  = ! empty( $_GET['pf_stock'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pf_sale   = ! empty( $_GET['pf_sale'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pf_ml     = isset( $_GET['pf_ml'] ) ? sanitize_key( wp_unslash( $_GET['pf_ml'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pf_ml     = in_array( $pf_ml, array( 'small', 'medium', 'large' ), true ) ? $pf_ml : '';

/**
 * URL for this page with one or more GET params added/replaced. An empty value
 * removes the param, which is what the filter toggles use to switch off.
 */
if ( ! function_exists( 'pf_url' ) ) {
	function pf_url( $base, $changes = array() ) {
		$params = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $params['paged'] );
		foreach ( $changes as $key => $value ) {
			if ( '' === $value || null === $value || array() === $value ) {
				unset( $params[ $key ] );
			} else {
				$params[ $key ] = $value;
			}
		}
		$query = http_build_query( $params );
		return $query ? $base . '?' . $query : $base;
	}
}

/**
 * Volume buckets, derived from the product titles.
 *
 * There is no pa_volume data in this catalogue — the size only ever appears in
 * the title ("... حجم ۱۰۰ میلی‌لیتر"). Parsing every perfume title once and
 * caching the id => ml map is both cheaper and safer than a MySQL REGEXP over
 * UTF-8 titles, and it keeps pagination correct because the bucket resolves to
 * a plain post__in list.
 *
 * A product whose title carries no size is in no bucket: it still shows in the
 * unfiltered grid, but a volume-filtered view cannot claim a size for it.
 */
function pf_volume_map( $cat_id ) {
	$cache_key = 'pf_volume_map_' . (int) $cat_id;
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array( array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => (int) $cat_id,
		) ),
	) );

	$map = array();
	foreach ( $ids as $id ) {
		$title = get_the_title( $id );
		// Persian/Arabic-Indic digits are what most of this catalogue uses.
		$latin = strtr( $title, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		) );
		if ( preg_match( '/(\d{1,4})\s*(?:میلی\s*لیتر|میل\s*لیتر|میلی‌لیتر|ml)/iu', $latin, $m ) ) {
			$ml = (int) $m[1];
			if ( $ml >= 5 && $ml <= 1000 ) {
				$map[ $id ] = $ml;
			}
		}
	}

	set_transient( $cache_key, $map, 12 * HOUR_IN_SECONDS );
	return $map;
}

$pf_volumes = $pf_cat ? pf_volume_map( $pf_cat->term_id ) : array();

$pf_ml_buckets = array(
	'small'  => array( 'label' => __( 'تا ۵۰ میلی‌لیتر', 'lylyrose' ), 'test' => function ( $ml ) { return $ml <= 50; } ),
	'medium' => array( 'label' => __( '۵۱ تا ۹۰ میلی‌لیتر', 'lylyrose' ), 'test' => function ( $ml ) { return $ml > 50 && $ml <= 90; } ),
	'large'  => array( 'label' => __( 'بیش از ۹۰ میلی‌لیتر', 'lylyrose' ), 'test' => function ( $ml ) { return $ml > 90; } ),
);

/* -------------------------------------------------------------------------
   The grid query.
   ------------------------------------------------------------------------- */
$pf_args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => 12,
	'paged'               => max( 1, (int) get_query_var( 'paged' ) ),
	'ignore_sticky_posts' => true,
);

if ( $pf_cat ) {
	$pf_args['tax_query'] = array( array(
		'taxonomy' => 'product_cat',
		'field'    => 'term_id',
		'terms'    => $pf_cat->term_id,
	) );
}

foreach ( $pf_tax_facets as $pf_param => $pf_taxonomy ) {
	if ( empty( $pf_selected[ $pf_param ] ) ) {
		continue;
	}
	$pf_args['tax_query'][] = array(
		'taxonomy' => $pf_taxonomy,
		'field'    => 'term_id',
		'terms'    => $pf_selected[ $pf_param ],
		'operator' => 'IN',
	);
}

if ( $pf_stock ) {
	$pf_args['meta_query'][] = array(
		'key'   => '_stock_status',
		'value' => 'instock',
	);
}
if ( $pf_sale ) {
	$pf_args['meta_query'][] = array(
		'key'     => '_sale_price',
		'value'   => 0,
		'compare' => '>',
		'type'    => 'NUMERIC',
	);
}
if ( $pf_ml && isset( $pf_ml_buckets[ $pf_ml ] ) ) {
	$pf_test = $pf_ml_buckets[ $pf_ml ]['test'];
	$pf_keep = array();
	foreach ( $pf_volumes as $pf_id => $pf_ml_value ) {
		if ( $pf_test( $pf_ml_value ) ) {
			$pf_keep[] = $pf_id;
		}
	}
	$pf_args['post__in'] = $pf_keep ? $pf_keep : array( 0 );
}

switch ( $pf_sort ) {
	case 'popular':
		$pf_args['meta_key'] = 'total_sales';
		$pf_args['orderby']  = 'meta_value_num';
		$pf_args['order']    = 'DESC';
		break;
	case 'newest':
		$pf_args['orderby'] = 'date';
		$pf_args['order']   = 'DESC';
		break;
	case 'cheapest':
		$pf_args['meta_key'] = '_price';
		$pf_args['orderby']  = 'meta_value_num';
		$pf_args['order']    = 'ASC';
		break;
	case 'expensive':
		$pf_args['meta_key'] = '_price';
		$pf_args['orderby']  = 'meta_value_num';
		$pf_args['order']    = 'DESC';
		break;
	case 'discount':
		$pf_args['orderby'] = 'date';
		$pf_args['order']   = 'DESC';
		break;
	default:
		$pf_args['orderby'] = 'date';
		$pf_args['order']   = 'DESC';
}

$pf_query = new WP_Query( $pf_args );

$pf_sort_options = array(
	'newest'    => __( 'جدیدترین', 'lylyrose' ),
	'popular'   => __( 'پرفروش‌ترین', 'lylyrose' ),
	'cheapest'  => __( 'ارزان‌ترین', 'lylyrose' ),
	'expensive' => __( 'گران‌ترین', 'lylyrose' ),
);

$pf_any_filter = $pf_stock || $pf_sale || $pf_sort || $pf_ml;
foreach ( $pf_selected as $pf_ids ) {
	if ( $pf_ids ) {
		$pf_any_filter = true;
	}
}

// Facet terms with counts, resolved once.
$pf_gender_terms    = get_terms( array( 'taxonomy' => 'pa_gender', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
$pf_family_terms    = get_terms( array( 'taxonomy' => 'pa_fragrance_family', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
$pf_occasion_terms  = get_terms( array( 'taxonomy' => 'pa_occasion', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
$pf_longevity_terms = get_terms( array( 'taxonomy' => 'pa_longevity', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
$pf_brand_terms     = get_terms( array( 'taxonomy' => 'pa_brand', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
foreach ( array( 'pf_gender_terms', 'pf_family_terms', 'pf_occasion_terms', 'pf_longevity_terms', 'pf_brand_terms' ) as $pf_var ) {
	if ( is_wp_error( $$pf_var ) ) {
		$$pf_var = array();
	}
}

// Best sellers, for the closing rail.
$pf_best = new WP_Query( array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 10,
	'meta_key'       => 'total_sales',
	'orderby'        => 'meta_value_num',
	'order'          => 'DESC',
	'no_found_rows'  => true,
	'tax_query'      => $pf_cat ? array( array(
		'taxonomy' => 'product_cat',
		'field'    => 'term_id',
		'terms'    => $pf_cat->term_id,
	) ) : array(),
) );

$pf_finder_url = get_permalink( get_page_by_path( 'perfume-finder' ) );
$pf_shop_url   = get_permalink( wc_get_page_id( 'shop' ) );

get_header();
?>

<main class="dk-main pf-main">

	<!-- ================= Hero ================= -->
	<div class="dk-container">
		<section class="pf-hero" aria-label="<?php esc_attr_e( 'دنیای عطرها', 'lylyrose' ); ?>">
			<?php
			// Art side: the newest perfume with a photo. A packshot floating over
			// the blush panel reads better than a stretched full-bleed crop, and it
			// is real catalogue content rather than a placeholder.
			$pf_hero_photo = lylyrose_promo_image( array(
				'orderby'  => 'date',
				'order'    => 'DESC',
				'tax_query' => $pf_cat ? array( array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $pf_cat->term_id,
				) ) : array(),
			), 'woocommerce_single' );
			?>
			<div class="pf-hero-copy">
				<h1><?php esc_html_e( 'دنیای عطرها، به سلیقه تو', 'lylyrose' ); ?></h1>
				<p><?php esc_html_e( 'از رایحه‌های خاص و روزانه تا عطرهای گرم و ماندگار، عطر دلخواهت رو پیدا کن.', 'lylyrose' ); ?></p>
				<a class="dk-btn pf-btn-pill" href="#pf-grid"><?php esc_html_e( 'مشاهده همه عطرها', 'lylyrose' ); ?> <span aria-hidden="true">←</span></a>
			</div>
			<div class="pf-hero-art" aria-hidden="true">
				<?php if ( $pf_hero_photo ) : ?>
					<img src="<?php echo esc_url( $pf_hero_photo ); ?>" alt="" loading="eager" width="300" height="300">
				<?php endif; ?>
			</div>
		</section>
	</div>

	<!-- ================= Quick access ================= -->
	<div class="dk-container">
		<section class="pf-quick" aria-label="<?php esc_attr_e( 'راه‌های پیدا کردن عطر', 'lylyrose' ); ?>">
			<a class="pf-quick-card" href="#pf-grid" data-pf-focus-search>
				<span class="pf-quick-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
				</span>
				<b><?php esc_html_e( 'عطرم رو می‌شناسم', 'lylyrose' ); ?></b>
				<small><?php esc_html_e( 'نام عطر یا برند مورد نظرت رو جستجو کن', 'lylyrose' ); ?></small>
				<span class="pf-quick-go" aria-hidden="true">‹</span>
			</a>

			<a class="pf-quick-card" href="#pf-filters">
				<span class="pf-quick-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
				</span>
				<b><?php esc_html_e( 'می‌خوام خودم بگردم', 'lylyrose' ); ?></b>
				<small><?php esc_html_e( 'با فیلترها و دسته‌بندی‌ها عطر مناسب خودت رو پیدا کن', 'lylyrose' ); ?></small>
				<span class="pf-quick-go" aria-hidden="true">‹</span>
			</a>

			<a class="pf-quick-card pf-quick-finder" href="<?php echo esc_url( $pf_finder_url ); ?>">
				<span class="pf-quick-badge" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M9 3h6M10 3v3.2L7.4 9.7A4 4 0 0 0 7 12.3V19a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-6.7a4 4 0 0 0-.4-2.6L14 6.2V3"/><path d="M7 12h10"/></svg>
				</span>
				<div class="pf-quick-finder-copy">
					<b><?php esc_html_e( 'نمی‌دونم چه عطری مناسبه!', 'lylyrose' ); ?></b>
					<small><?php esc_html_e( 'به چند سوال ساده جواب بده تا بهترین گزینه‌ها رو بهت پیشنهاد کنیم', 'lylyrose' ); ?></small>
				</div>
				<span class="pf-quick-cta"><?php esc_html_e( 'شروع تست انتخاب عطر', 'lylyrose' ); ?> <span aria-hidden="true">←</span></span>
			</a>
		</section>
	</div>

	<!-- ================= Scent families ================= -->
	<?php if ( $pf_family_terms ) : ?>
	<div class="dk-container">
		<section class="pf-families" aria-label="<?php esc_attr_e( 'انتخاب سریع بر اساس رایحه', 'lylyrose' ); ?>">
			<h2 class="pf-section-title"><?php esc_html_e( 'انتخاب سریع بر اساس رایحه', 'lylyrose' ); ?></h2>
			<div class="pf-family-row">
				<?php foreach ( $pf_family_terms as $pf_fam ) :
					$pf_active = in_array( (int) $pf_fam->term_id, $pf_selected['pf_family'], true );
					$pf_img    = lylyrose_term_image( $pf_fam, 'woocommerce_gallery_thumbnail' );
					?>
					<a class="pf-family<?php echo $pf_active ? ' is-active' : ''; ?>"
					   href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_family' => $pf_active ? array() : array( $pf_fam->term_id ) ) ) ); ?>#pf-grid">
						<span class="pf-family-img">
							<?php if ( $pf_img ) : ?>
								<img src="<?php echo esc_url( $pf_img ); ?>" alt="" loading="lazy" width="96" height="96">
							<?php else : ?>
								<?php echo esc_html( mb_substr( $pf_fam->name, 0, 1 ) ); ?>
							<?php endif; ?>
						</span>
						<span class="pf-family-name"><?php echo esc_html( $pf_fam->name ); ?> <span aria-hidden="true">‹</span></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	</div>
	<?php endif; ?>

	<!-- ================= Grid + filters ================= -->
	<div class="dk-container" id="pf-grid">
		<div class="pf-layout">

			<aside class="dk-filters pf-filters" id="pf-filters">
				<div class="dk-filters-head">
					<span class="dk-filters-title">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
						<?php esc_html_e( 'فیلترها', 'lylyrose' ); ?>
					</span>
				</div>

				<form method="get" action="<?php echo esc_url( $pf_base_url ); ?>" class="pf-filter-form" id="pf-filter-form">
					<?php if ( $pf_sort ) : ?><input type="hidden" name="pf_sort" value="<?php echo esc_attr( $pf_sort ); ?>"><?php endif; ?>

					<?php
					// One checkbox group per taxonomy facet. Rendered by a loop so a
					// facet with no data costs its own group and nothing else.
					$pf_groups = array(
						'pf_gender'    => array( 'label' => __( 'جنسیت', 'lylyrose' ), 'terms' => $pf_gender_terms ),
						'pf_family'    => array( 'label' => __( 'نوع رایحه', 'lylyrose' ), 'terms' => $pf_family_terms ),
						'pf_occasion'  => array( 'label' => __( 'موقعیت استفاده', 'lylyrose' ), 'terms' => $pf_occasion_terms ),
						'pf_longevity' => array( 'label' => __( 'ماندگاری', 'lylyrose' ), 'terms' => $pf_longevity_terms ),
					);
					foreach ( $pf_groups as $pf_param => $pf_group ) :
						if ( empty( $pf_group['terms'] ) ) { continue; } ?>
						<div class="dk-filter-group">
							<h4><?php echo esc_html( $pf_group['label'] ); ?></h4>
							<ul class="dk-attr-list">
								<?php foreach ( $pf_group['terms'] as $pf_term ) : ?>
									<li>
										<label class="dk-filter-toggle">
											<input type="checkbox" class="dk-brand-checkbox" name="<?php echo esc_attr( $pf_param ); ?>[]"
											       value="<?php echo esc_attr( $pf_term->term_id ); ?>"
											       <?php checked( in_array( (int) $pf_term->term_id, $pf_selected[ $pf_param ], true ) ); ?>>
											<span class="dk-check"></span>
											<span class="dk-brand-name"><?php echo esc_html( $pf_term->name ); ?></span>
											<span class="dk-brand-count"><?php echo esc_html( lylyrose_fa_num( $pf_term->count ) ); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>

					<?php if ( $pf_brand_terms ) : ?>
						<div class="dk-filter-group">
							<h4><?php esc_html_e( 'برند', 'lylyrose' ); ?></h4>
							<div class="pf-brand-search">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
								<input type="search" id="pf-brand-search" placeholder="<?php esc_attr_e( 'جستجوی برند…', 'lylyrose' ); ?>" aria-label="<?php esc_attr_e( 'جستجوی برند', 'lylyrose' ); ?>">
							</div>
							<ul class="dk-attr-list dk-brand-list" id="pf-brand-list">
								<?php foreach ( $pf_brand_terms as $pf_term ) : ?>
									<li data-pf-brand-name="<?php echo esc_attr( $pf_term->name ); ?>">
										<label class="dk-filter-toggle">
											<input type="checkbox" class="dk-brand-checkbox" name="pf_brand[]"
											       value="<?php echo esc_attr( $pf_term->term_id ); ?>"
											       <?php checked( in_array( (int) $pf_term->term_id, $pf_selected['pf_brand'], true ) ); ?>>
											<span class="dk-check"></span>
											<span class="dk-brand-name"><?php echo esc_html( $pf_term->name ); ?></span>
											<span class="dk-brand-count"><?php echo esc_html( lylyrose_fa_num( $pf_term->count ) ); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<?php
					// Volume buckets are derived from titles, so only buckets that the
					// catalogue actually fills are offered — an empty bucket is a filter
					// that can only return nothing.
					$pf_ml_available = array();
					foreach ( $pf_ml_buckets as $pf_key => $pf_bucket ) {
						foreach ( $pf_volumes as $pf_ml_value ) {
							if ( $pf_bucket['test']( $pf_ml_value ) ) {
								$pf_ml_available[ $pf_key ] = $pf_bucket;
								break;
							}
						}
					}
					?>
					<?php if ( $pf_ml_available ) : ?>
						<div class="dk-filter-group">
							<h4><?php esc_html_e( 'حجم', 'lylyrose' ); ?></h4>
							<ul class="dk-attr-list">
								<?php foreach ( $pf_ml_available as $pf_key => $pf_bucket ) : ?>
									<li>
										<label class="dk-filter-toggle">
											<input type="radio" class="pf-radio" name="pf_ml" value="<?php echo esc_attr( $pf_key ); ?>"
											       <?php checked( $pf_ml, $pf_key ); ?>>
											<span class="pf-radio-mark"></span>
											<span class="dk-brand-name"><?php echo esc_html( $pf_bucket['label'] ); ?></span>
										</label>
									</li>
								<?php endforeach; ?>
								<li>
									<label class="dk-filter-toggle">
										<input type="radio" class="pf-radio" name="pf_ml" value="" <?php checked( '', $pf_ml ); ?>>
										<span class="pf-radio-mark"></span>
										<span class="dk-brand-name"><?php esc_html_e( 'همه', 'lylyrose' ); ?></span>
									</label>
								</li>
							</ul>
						</div>
					<?php endif; ?>

					<div class="dk-filter-group">
						<a class="dk-filter-toggle <?php echo $pf_stock ? 'is-active' : ''; ?>"
						   href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_stock' => $pf_stock ? '' : '1' ) ) ); ?>#pf-grid">
							<span class="dk-check<?php echo $pf_stock ? ' is-checked' : ''; ?>"></span>
							<?php esc_html_e( 'فقط کالاهای موجود', 'lylyrose' ); ?>
						</a>
						<a class="dk-filter-toggle <?php echo $pf_sale ? 'is-active' : ''; ?>"
						   href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_sale' => $pf_sale ? '' : '1' ) ) ); ?>#pf-grid">
							<span class="dk-check<?php echo $pf_sale ? ' is-checked' : ''; ?>"></span>
							<?php esc_html_e( 'فقط کالاهای تخفیف‌دار', 'lylyrose' ); ?>
						</a>
					</div>

					<button type="submit" class="pf-apply"><?php esc_html_e( 'اعمال فیلترها', 'lylyrose' ); ?></button>
					<?php if ( $pf_any_filter ) : ?>
						<a class="pf-clear" href="<?php echo esc_url( $pf_base_url ); ?>"><?php esc_html_e( 'پاک کردن فیلترها', 'lylyrose' ); ?></a>
					<?php endif; ?>
				</form>
			</aside>

			<div class="pf-products">
				<div class="pf-toolbar">
					<h2 class="pf-products-title">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M9 3h6M10 3v3.2L7.4 9.7A4 4 0 0 0 7 12.3V19a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-6.7a4 4 0 0 0-.4-2.6L14 6.2V3"/><path d="M7 12h10"/></svg>
						<?php esc_html_e( 'جدیدترین عطرها', 'lylyrose' ); ?>
					</h2>
					<div class="pf-toolbar-sort">
						<span><?php esc_html_e( 'مرتب‌سازی بر اساس:', 'lylyrose' ); ?></span>
						<select id="pf-sort-select" aria-label="<?php esc_attr_e( 'مرتب‌سازی', 'lylyrose' ); ?>">
							<?php foreach ( $pf_sort_options as $pf_key => $pf_label ) : ?>
								<option value="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_sort' => $pf_key ) ) ); ?>#pf-grid"
									<?php selected( $pf_sort ? $pf_sort : 'newest', $pf_key ); ?>><?php echo esc_html( $pf_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<?php if ( $pf_query->have_posts() ) : ?>
					<ul class="pf-grid">
						<?php
						while ( $pf_query->have_posts() ) :
							$pf_query->the_post();
							global $product;
							if ( ! $product instanceof WC_Product ) {
								continue;
							}
							$pf_percent  = lylyrose_discount_percent( $product );
							$pf_families = wp_get_post_terms( $product->get_id(), 'pa_fragrance_family', array( 'fields' => 'names' ) );
							$pf_genders  = wp_get_post_terms( $product->get_id(), 'pa_gender', array( 'fields' => 'names' ) );
							$pf_rating   = (float) $product->get_average_rating();
							$pf_reviews  = (int) $product->get_review_count();
							?>
							<li class="pf-card">
								<div class="pf-card-media">
									<?php if ( $pf_percent ) : ?>
										<span class="pf-badge"><?php echo esc_html( lylyrose_fa_num( $pf_percent ) ); ?>٪</span>
									<?php endif; ?>
									<button type="button" class="pf-wish" aria-label="<?php esc_attr_e( 'افزودن به علاقه‌مندی‌ها', 'lylyrose' ); ?>">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
									</button>
									<a class="pf-card-photo" href="<?php echo esc_url( $product->get_permalink() ); ?>">
										<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
									</a>
								</div>

								<h3 class="pf-card-title">
									<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
								</h3>

								<?php if ( $pf_families || $pf_genders ) : ?>
									<p class="pf-card-tags">
										<?php echo esc_html( implode( ' | ', array_slice( $pf_families, 0, 2 ) ) ); ?>
										<?php if ( $pf_families && $pf_genders ) : ?><span class="pf-tag-sep">•</span><?php endif; ?>
										<?php echo esc_html( implode( ' | ', $pf_genders ) ); ?>
									</p>
								<?php endif; ?>

								<?php if ( $pf_reviews > 0 ) : ?>
									<div class="pf-card-rating">
										<span class="dk-stars" aria-hidden="true">★★★★★</span>
										<span class="pf-rating-num">(<?php echo esc_html( lylyrose_fa_num( number_format_i18n( $pf_rating, 1 ) ) ); ?>)</span>
										<span class="pf-rating-count"><?php echo esc_html( lylyrose_fa_num( $pf_reviews ) ); ?></span>
									</div>
								<?php endif; ?>

								<div class="pf-card-price">
									<?php if ( $product->is_on_sale() && $product->get_regular_price() ) : ?>
										<del><?php echo wp_kses_post( wc_price( $product->get_regular_price() ) ); ?></del>
									<?php endif; ?>
									<strong><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></strong>
								</div>

								<?php if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) : ?>
									<button type="button" class="pf-add" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-pf-add>
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2.5 3h2l2.6 12.5a2 2 0 0 0 2 1.5h8.7a2 2 0 0 0 2-1.6L21.5 7H6"/></svg>
										<?php esc_html_e( 'افزودن به سبد خرید', 'lylyrose' ); ?>
									</button>
								<?php else : ?>
									<a class="pf-add pf-add-link" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php esc_html_e( 'مشاهده محصول', 'lylyrose' ); ?></a>
								<?php endif; ?>
							</li>
						<?php endwhile; ?>
					</ul>

					<?php
					$pf_total_pages = (int) $pf_query->max_num_pages;
					if ( $pf_total_pages > 1 ) :
						$pf_current = max( 1, (int) get_query_var( 'paged' ) );
						?>
						<nav class="dk-pagination pf-pagination" aria-label="<?php esc_attr_e( 'صفحه‌بندی', 'lylyrose' ); ?>">
							<?php
							$pf_links = paginate_links( array(
								'base'      => trailingslashit( $pf_base_url ) . '%_%',
								'format'    => user_trailingslashit( 'page/%#%' ),
								'current'   => $pf_current,
								'total'     => $pf_total_pages,
								'type'      => 'array',
								'prev_text' => '‹',
								'next_text' => '›',
							) );
							if ( $pf_links ) {
								foreach ( $pf_links as $pf_link ) {
									echo wp_kses_post( $pf_link );
								}
							}
							?>
						</nav>
					<?php endif; ?>
				<?php else : ?>
					<div class="pf-empty">
						<h2><?php esc_html_e( 'عطری مطابق فیلترهای شما پیدا نشد', 'lylyrose' ); ?></h2>
						<p><?php esc_html_e( 'فیلترها را تغییر دهید یا همه عطرها را ببینید.', 'lylyrose' ); ?></p>
						<a class="dk-btn dk-btn-primary" href="<?php echo esc_url( $pf_base_url ); ?>"><?php esc_html_e( 'نمایش همه عطرها', 'lylyrose' ); ?></a>
					</div>
				<?php endif; ?>
				<?php wp_reset_postdata(); ?>
			</div>
		</div>
	</div>

	<!-- ================= Finder banner ================= -->
	<div class="dk-container">
		<section class="pf-guide" aria-label="<?php esc_attr_e( 'راهنمای انتخاب عطر', 'lylyrose' ); ?>">
			<div class="pf-guide-copy">
				<h2><?php esc_html_e( 'راهنمای انتخاب عطر', 'lylyrose' ); ?></h2>
				<p><?php esc_html_e( 'با چند سوال کوتاه، عطر مناسب شخصیت و سلیقه‌ات رو پیدا کن.', 'lylyrose' ); ?></p>
				<a class="pf-btn-pill pf-btn-dark" href="<?php echo esc_url( $pf_finder_url ); ?>"><?php esc_html_e( 'شروع تست انتخاب عطر', 'lylyrose' ); ?> <span aria-hidden="true">←</span></a>
			</div>
			<ul class="pf-guide-badges">
				<?php
				$pf_badges = array(
					array( 'label' => __( 'اصالت کالا', 'lylyrose' ), 'svg' => '<path d="M12 3l7 3v6c0 4.4-3 8-7 9-4-1-7-4.6-7-9V6z"/><path d="M9 12l2 2 4-4"/>' ),
					array( 'label' => __( 'ماندگاری و خط بو', 'lylyrose' ), 'svg' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>' ),
					array( 'label' => __( 'سلامت شیشه', 'lylyrose' ), 'svg' => '<path d="M9 3h6M10 3v3.2L7.4 9.7A4 4 0 0 0 7 12.3V19a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-6.7a4 4 0 0 0-.4-2.6L14 6.2V3"/>' ),
					array( 'label' => __( 'سرعت در ارسال', 'lylyrose' ), 'svg' => '<path d="M3 7h9v8H3z"/><path d="M12 10h4l3 3v2h-7z"/><circle cx="6.5" cy="17.5" r="1.6"/><circle cx="16.5" cy="17.5" r="1.6"/>' ),
				);
				foreach ( $pf_badges as $pf_badge ) : ?>
					<li>
						<span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><?php echo $pf_badge['svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- static inline markup ?></svg></span>
						<?php echo esc_html( $pf_badge['label'] ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>

	<!-- ================= Best sellers ================= -->
	<?php if ( $pf_best->have_posts() ) : ?>
	<div class="dk-container">
		<section class="pf-best" aria-label="<?php esc_attr_e( 'پرفروش‌ترین عطرها', 'lylyrose' ); ?>">
			<div class="dk-section-head">
				<h2><?php esc_html_e( 'پرفروش‌ترین عطرها', 'lylyrose' ); ?></h2>
				<a class="dk-section-more" href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_sort' => 'popular' ) ) ); ?>#pf-grid"><?php esc_html_e( 'مشاهده همه ‹', 'lylyrose' ); ?></a>
			</div>
			<div class="pf-best-rail">
				<button type="button" class="pf-rail-nav pf-rail-prev" aria-label="<?php esc_attr_e( 'قبلی', 'lylyrose' ); ?>"></button>
				<div class="pf-best-track" data-pf-track>
					<?php
					while ( $pf_best->have_posts() ) :
						$pf_best->the_post();
						global $product;
						if ( ! $product instanceof WC_Product ) {
							continue;
						}
						$pf_b_percent = lylyrose_discount_percent( $product );
						$pf_b_rating  = (float) $product->get_average_rating();
						?>
						<a class="pf-best-card" href="<?php echo esc_url( $product->get_permalink() ); ?>">
							<span class="pf-card-media">
								<?php if ( $pf_b_percent ) : ?>
									<span class="pf-badge"><?php echo esc_html( lylyrose_fa_num( $pf_b_percent ) ); ?>٪</span>
								<?php endif; ?>
								<span class="pf-card-photo"><?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?></span>
							</span>
							<span class="pf-best-name"><?php echo esc_html( $product->get_name() ); ?></span>
							<?php if ( $pf_b_rating > 0 ) : ?>
								<span class="pf-best-rating"><span class="dk-stars" aria-hidden="true">★★★★★</span> <?php echo esc_html( lylyrose_fa_num( number_format_i18n( $pf_b_rating, 1 ) ) ); ?></span>
							<?php endif; ?>
							<span class="pf-best-price"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span>
						</a>
					<?php endwhile; ?>
				</div>
				<button type="button" class="pf-rail-nav pf-rail-next" aria-label="<?php esc_attr_e( 'بعدی', 'lylyrose' ); ?>"></button>
			</div>
		</section>
	</div>
	<?php wp_reset_postdata(); endif; ?>

	<div class="pf-toast" id="pf-toast" role="status" aria-live="polite"></div>
</main>

<?php get_footer(); ?>
