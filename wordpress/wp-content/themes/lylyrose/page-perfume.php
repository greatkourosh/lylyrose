<?php
/**
 * Perfume landing page (/perfume/) — full-bleed hero, quick-entry cards,
 * seven scent-family tiles, filter rail + product grid, guide banner and
 * best-seller rail.
 *
 * The page owns its own WP_Query instead of riding the main query: the shop
 * filters in lylyrose_shop_filters() only run on the main query of an archive,
 * and this is a static page. Building the query here keeps the facet code in
 * one place and leaves /shop/ and the category archives untouched.
 *
 * Every filter is a GET param on this URL, so a filtered view is shareable.
 * Pagination is the `pf_page` GET param rather than /page/N/ — a static page
 * does not populate the `paged` query var, and `page/N/` is WordPress's
 * multipage-content route.
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

$pf_sort = isset( $_GET['pf_sort'] ) ? sanitize_key( wp_unslash( $_GET['pf_sort'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pf_ml   = isset( $_GET['pf_ml'] ) ? sanitize_key( wp_unslash( $_GET['pf_ml'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pf_ml   = in_array( $pf_ml, array( 'v30', 'v50', 'v100', 'other' ), true ) ? $pf_ml : '';
$pf_page = isset( $_GET['pf_page'] ) ? max( 1, absint( wp_unslash( $_GET['pf_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pf_page = min( $pf_page, 999 );

/**
 * URL for this page with one or more GET params added/replaced. An empty value
 * removes the param, which is what the filter toggles use to switch off.
 * Page number is dropped on every change unless the caller sets `pf_page`
 * explicitly (pagination), so a filter never lands on page 7 of a shorter list.
 */
if ( ! function_exists( 'pf_url' ) ) {
	function pf_url( $base, $changes = array() ) {
		$params = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $params['paged'], $params['pf_page'] );
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

/* The comp's 20/50/100 ml are near-enough buckets for this catalogue: the
   titles carry 25–125 ml, with 30/50/100 dominant. "other" keeps the rest
   reachable instead of silently dropping them from a filtered view. */
$pf_ml_buckets = array(
	'v30'   => array( 'label' => __( '۳۰ میلی‌لیتر', 'lylyrose' ), 'test' => function ( $ml ) { return $ml >= 25 && $ml < 40; } ),
	'v50'   => array( 'label' => __( '۵۰ میلی‌لیتر', 'lylyrose' ), 'test' => function ( $ml ) { return $ml >= 40 && $ml < 70; } ),
	'v100'  => array( 'label' => __( '۱۰۰ میلی‌لیتر', 'lylyrose' ), 'test' => function ( $ml ) { return $ml >= 70 && $ml < 120; } ),
	'other' => array( 'label' => __( 'سایر حجم‌ها', 'lylyrose' ), 'test' => function ( $ml ) { return $ml < 25 || $ml >= 120; } ),
);

/* -------------------------------------------------------------------------
   The grid query.
   ------------------------------------------------------------------------- */
$pf_args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => 8,
	'paged'               => $pf_page,
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
	case 'rating':
		$pf_args['meta_key'] = '_wc_average_rating';
		$pf_args['orderby']  = 'meta_value_num';
		$pf_args['order']    = 'DESC';
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
	case 'newest':
	default:
		$pf_args['orderby'] = 'date';
		$pf_args['order']   = 'DESC';
}

$pf_query = new WP_Query( $pf_args );

$pf_sort_options = array(
	'newest'    => __( 'جدیدترین', 'lylyrose' ),
	'popular'   => __( 'پرفروش‌ترین', 'lylyrose' ),
	'rating'    => __( 'پرامتیازترین', 'lylyrose' ),
	'cheapest'  => __( 'ارزان‌ترین', 'lylyrose' ),
	'expensive' => __( 'گران‌ترین', 'lylyrose' ),
);

$pf_active_count = $pf_ml ? 1 : 0;
foreach ( $pf_selected as $pf_ids ) {
	$pf_active_count += count( $pf_ids );
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

$pf_family_by_name = array();
foreach ( $pf_family_terms as $pf_term ) {
	$pf_family_by_name[ $pf_term->name ] = $pf_term;
}

/**
 * The seven tiles. Six are real pa_fragrance_family terms; the seventh
 * ("همه رایحه‌ها") resets the facet. Images are theme assets so a term
 * without a thumbnail never falls back to a bare letter.
 */
$pf_family_tiles = array(
	'floral'   => array( 'term' => 'گل', 'label' => __( 'گلی', 'lylyrose' ), 'image' => 'family-floral.webp', 'alt' => __( 'گل‌های رز صورتی', 'lylyrose' ) ),
	'aquatic'  => array( 'term' => 'آکواتیک', 'label' => __( 'آکواتیک', 'lylyrose' ), 'image' => 'family-aquatic.webp', 'alt' => __( 'مرکبات و شکوفه‌های سفید', 'lylyrose' ) ),
	'fruity'   => array( 'term' => 'میوه', 'label' => __( 'میوه‌ای', 'lylyrose' ), 'image' => 'family-fruity.webp', 'alt' => __( 'میوه‌های قرمز و گل', 'lylyrose' ) ),
	'woody'    => array( 'term' => 'چوب', 'label' => __( 'چوبی', 'lylyrose' ), 'image' => 'family-woody.webp', 'alt' => __( 'چوب و ادویه', 'lylyrose' ) ),
	'spicy'    => array( 'term' => 'ادویه', 'label' => __( 'ادویه‌ای', 'lylyrose' ), 'image' => 'family-spicy.webp', 'alt' => __( 'دارچین و ادویه گرم', 'lylyrose' ) ),
	'oriental' => array( 'term' => 'شرقی', 'label' => __( 'شرقی', 'lylyrose' ), 'image' => 'family-oriental.webp', 'alt' => __( 'شکلات و رایحه شیرین', 'lylyrose' ) ),
	'all'      => array( 'term' => '', 'label' => __( 'همه رایحه‌ها', 'lylyrose' ), 'image' => 'family-all.webp', 'alt' => __( 'گل‌های سفید', 'lylyrose' ) ),
);

// Best sellers, for the closing rail. total_sales is the catalogue's only
// popularity signal; the date tiebreak keeps a zero-sales catalogue stable.
$pf_best = new WP_Query( array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 10,
	'meta_key'       => 'total_sales',
	'orderby'        => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
	'no_found_rows'  => true,
	'tax_query'      => $pf_cat ? array( array(
		'taxonomy' => 'product_cat',
		'field'    => 'term_id',
		'terms'    => $pf_cat->term_id,
	) ) : array(),
) );

$pf_finder_page = get_page_by_path( 'perfume-finder' );
$pf_finder_url  = $pf_finder_page ? get_permalink( $pf_finder_page ) : home_url( '/perfume-finder/' );
$pf_theme_img   = get_theme_file_uri( 'assets/images/perfume/' );

/**
 * Inline line icons. Static markup only, safe to echo.
 */
$pf_icon = static function ( $name ) {
	$icons = array(
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/>',
		'filter'  => '<path d="M3 6h18M6 12h12M10 18h4"/>',
		'bottle'  => '<path d="M9 3h6M10 3v3.2L7.4 9.7A4 4 0 0 0 7 12.3V19a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-6.7a4 4 0 0 0-.4-2.6L14 6.2V3"/><path d="M7 12h10"/>',
		'heart'   => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
		'cart'    => '<circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2.5 3h2l2.6 12.5a2 2 0 0 0 2 1.5h8.7a2 2 0 0 0 2-1.6L21.5 7H6"/>',
		'shield'  => '<path d="M12 3l7 3v6c0 4.4-3 8-7 9-4-1-7-4.6-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
		'truck'   => '<path d="M3 7h9v8H3z"/><path d="M12 10h4l3 3v2h-7z"/><circle cx="6.5" cy="17.5" r="1.6"/><circle cx="16.5" cy="17.5" r="1.6"/>',
		'support' => '<path d="M4 13a8 8 0 0 1 16 0"/><path d="M4 13v3a2 2 0 0 0 2 2h1v-7H6a2 2 0 0 0-2 2z"/><path d="M20 13v3a2 2 0 0 1-2 2h-1v-7h1a2 2 0 0 1 2 2z"/>',
		'lock'    => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
		'chevron' => '<path d="M6 9l6 6 6-6"/>',
		'close'   => '<path d="M18 6 6 18M6 6l12 12"/>',
		'arrow'   => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
	);
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $icons[ $name ] ?? '' ) . '</svg>';
};

/**
 * Star row with a partial fill. The gold layer is clipped by --pf-rating, so a
 * 4.5 product shows four and a half stars without a second image.
 */
$pf_stars = static function ( $rating ) {
	$rating = max( 0, min( 5, (float) $rating ) );
	$label  = sprintf(
		/* translators: %s: average rating out of five. */
		__( 'امتیاز %s از ۵', 'lylyrose' ),
		lylyrose_fa_num( number_format_i18n( $rating, 1 ) )
	);
	return '<span class="pf-stars" style="--pf-rating:' . esc_attr( $rating ) . '" role="img" aria-label="' . esc_attr( $label ) . '"><span class="pf-stars-base" aria-hidden="true">★★★★★</span><span class="pf-stars-fill" aria-hidden="true">★★★★★</span></span>';
};

get_header();
?>

<main class="dk-main pf-main">

	<!-- ================= Hero ================= -->
	<section class="pf-hero" aria-labelledby="pf-hero-title">
		<img class="pf-hero-img" src="<?php echo esc_url( lylyrose_asset( 'perfume.hero', $pf_theme_img . 'hero.webp' ) ); ?>" alt="" width="1680" height="403" fetchpriority="high" decoding="async">
		<span class="pf-hero-scrim" aria-hidden="true"></span>
		<div class="pf-shell pf-hero-inner">
			<div class="pf-hero-copy">
				<h1 id="pf-hero-title"><?php echo esc_html( lylyrose_text( 'perfume.hero_title', __( 'دنیای عطرها، به سلیقه تو', 'lylyrose' ) ) ); ?></h1>
				<p><?php echo esc_html( lylyrose_text( 'perfume.hero_copy', __( 'از رایحه‌های خنک و روزانه تا عطرهای گرم و ماندگار، عطر دلخواهت رو پیدا کن.', 'lylyrose' ) ) ); ?></p>
				<a class="pf-btn pf-btn-primary" href="#pf-grid"><?php echo esc_html( lylyrose_text( 'perfume.hero_cta', __( 'مشاهده همه عطرها', 'lylyrose' ) ) ); ?> <?php echo $pf_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></a>
			</div>
		</div>
	</section>

	<div class="pf-shell">

		<!-- ================= Quick access ================= -->
		<section class="pf-quick" aria-label="<?php esc_attr_e( 'راه‌های پیدا کردن عطر', 'lylyrose' ); ?>">
			<a class="pf-quick-card pf-quick-finder" href="<?php echo esc_url( $pf_finder_url ); ?>">
				<span class="pf-quick-img">
					<img src="<?php echo esc_url( lylyrose_asset( 'perfume.quiz', $pf_theme_img . 'quiz.webp' ) ); ?>" alt="" width="360" height="580" loading="lazy" decoding="async">
				</span>
				<span class="pf-quick-body">
					<span class="pf-quick-icon" aria-hidden="true"><?php echo $pf_icon( 'bottle' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
					<b><?php echo esc_html( lylyrose_text( 'perfume.quiz_title', __( 'کمکم کن عطر انتخاب کنم', 'lylyrose' ) ) ); ?></b>
					<small><?php echo esc_html( lylyrose_text( 'perfume.quiz_copy', __( 'هنوز انتخاب‌ت رو نمی‌دونی؟ با چند سوال ساده بهترین گزینه‌ها رو پیشنهاد می‌دیم', 'lylyrose' ) ) ); ?></small>
					<span class="pf-quick-cta"><?php echo esc_html( lylyrose_text( 'perfume.quiz_cta', __( 'شروع تست انتخاب عطر', 'lylyrose' ) ) ); ?> <?php echo $pf_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
				</span>
			</a>

			<a class="pf-quick-card" href="#pf-filters" data-pf-open-filters>
				<span class="pf-quick-img">
					<img src="<?php echo esc_url( lylyrose_asset( 'perfume.quick_a', $pf_theme_img . 'quick-a.webp' ) ); ?>" alt="" width="600" height="432" loading="lazy" decoding="async">
				</span>
				<span class="pf-quick-body">
					<span class="pf-quick-icon" aria-hidden="true"><?php echo $pf_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
					<b><?php echo esc_html( lylyrose_text( 'perfume.quick_a_title', __( 'می‌خوام خودم بگردم', 'lylyrose' ) ) ); ?></b>
					<small><?php echo esc_html( lylyrose_text( 'perfume.quick_a_copy', __( 'با فیلترها و دسته‌بندی‌ها عطر مناسب خودت رو پیدا کن', 'lylyrose' ) ) ); ?></small>
					<span class="pf-quick-go" aria-hidden="true">‹</span>
				</span>
			</a>

			<a class="pf-quick-card" href="#pf-grid" data-pf-focus-search>
				<span class="pf-quick-img">
					<img src="<?php echo esc_url( lylyrose_asset( 'perfume.quick_b', $pf_theme_img . 'quick-b.webp' ) ); ?>" alt="" width="600" height="432" loading="lazy" decoding="async">
				</span>
				<span class="pf-quick-body">
					<span class="pf-quick-icon" aria-hidden="true"><?php echo $pf_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
					<b><?php echo esc_html( lylyrose_text( 'perfume.quick_b_title', __( 'عطرم رو می‌شناسم', 'lylyrose' ) ) ); ?></b>
					<small><?php echo esc_html( lylyrose_text( 'perfume.quick_b_copy', __( 'اسم عطر یا برندش رو می‌دونی؟ همین‌جا جستجو کن', 'lylyrose' ) ) ); ?></small>
					<span class="pf-quick-go" aria-hidden="true">‹</span>
				</span>
			</a>
		</section>

		<!-- ================= Scent families ================= -->
		<section class="pf-families" aria-labelledby="pf-families-title">
			<div class="pf-section-head">
				<h2 id="pf-families-title"><?php echo esc_html( lylyrose_text( 'perfume.families_title', __( 'انتخاب سریع بر اساس رایحه', 'lylyrose' ) ) ); ?></h2>
			</div>
			<div class="pf-family-row">
				<?php foreach ( $pf_family_tiles as $pf_tile_key => $pf_tile ) :
					$pf_term_obj = $pf_tile['term'] && isset( $pf_family_by_name[ $pf_tile['term'] ] ) ? $pf_family_by_name[ $pf_tile['term'] ] : null;
					if ( 'all' === $pf_tile_key ) {
						$pf_tile_active = empty( $pf_selected['pf_family'] );
						$pf_tile_url    = $pf_base_url . '#pf-grid';
					} elseif ( $pf_term_obj ) {
						$pf_tile_active = in_array( (int) $pf_term_obj->term_id, $pf_selected['pf_family'], true );
						$pf_tile_url    = pf_url( $pf_base_url, array( 'pf_family' => $pf_tile_active ? array() : array( $pf_term_obj->term_id ) ) ) . '#pf-grid';
					} else {
						continue;
					}
					?>
					<a class="pf-family<?php echo $pf_tile_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pf_tile_url ); ?>"<?php echo $pf_tile_active ? ' aria-current="true"' : ''; ?>>
						<span class="pf-family-img">
							<img src="<?php echo esc_url( lylyrose_asset( 'perfume.family.' . $pf_tile_key, $pf_theme_img . $pf_tile['image'] ) ); ?>" alt="<?php echo esc_attr( $pf_tile['alt'] ); ?>" width="320" height="154" loading="lazy" decoding="async">
						</span>
						<span class="pf-family-name"><?php echo esc_html( $pf_tile['label'] ); ?> <span aria-hidden="true">‹</span></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- ================= Grid + filters ================= -->
		<div class="pf-layout" id="pf-grid">

			<aside class="pf-filters" id="pf-filters" aria-label="<?php esc_attr_e( 'فیلترها', 'lylyrose' ); ?>">
				<div class="pf-filter-head">
					<span class="pf-filter-title"><?php echo $pf_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?><?php esc_html_e( 'فیلترها', 'lylyrose' ); ?></span>
					<button type="button" class="pf-filter-close" data-pf-close-filters aria-label="<?php esc_attr_e( 'بستن فیلترها', 'lylyrose' ); ?>"><?php echo $pf_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></button>
				</div>

				<form method="get" action="<?php echo esc_url( $pf_base_url ); ?>" class="pf-filter-form" id="pf-filter-form">
					<?php if ( $pf_sort ) : ?><input type="hidden" name="pf_sort" value="<?php echo esc_attr( $pf_sort ); ?>"><?php endif; ?>

					<?php
					$pf_groups = array(
						'pf_gender'    => array( 'label' => __( 'جنسیت', 'lylyrose' ), 'terms' => $pf_gender_terms, 'id' => 'gender' ),
						'pf_family'    => array( 'label' => __( 'نوع رایحه', 'lylyrose' ), 'terms' => $pf_family_terms, 'id' => 'family' ),
						'pf_occasion'  => array( 'label' => __( 'موقعیت استفاده', 'lylyrose' ), 'terms' => $pf_occasion_terms, 'id' => 'occasion' ),
						'pf_longevity' => array( 'label' => __( 'ماندگاری', 'lylyrose' ), 'terms' => $pf_longevity_terms, 'id' => 'longevity' ),
					);
					foreach ( $pf_groups as $pf_param => $pf_group ) :
						if ( empty( $pf_group['terms'] ) ) { continue; }
						$pf_group_id = 'pf-group-' . $pf_group['id'];
						?>
						<div class="pf-filter-group">
							<button type="button" class="pf-filter-group-title" aria-expanded="true" aria-controls="<?php echo esc_attr( $pf_group_id ); ?>">
								<span><?php echo esc_html( $pf_group['label'] ); ?></span>
								<?php echo $pf_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
							</button>
							<div class="pf-filter-options" id="<?php echo esc_attr( $pf_group_id ); ?>" role="group" aria-label="<?php echo esc_attr( $pf_group['label'] ); ?>">
								<?php foreach ( $pf_group['terms'] as $pf_term ) :
									$pf_checked = in_array( (int) $pf_term->term_id, $pf_selected[ $pf_param ], true );
									?>
									<label class="pf-check">
										<input type="checkbox" name="<?php echo esc_attr( $pf_param ); ?>[]" value="<?php echo esc_attr( $pf_term->term_id ); ?>" <?php checked( $pf_checked ); ?>>
										<span class="pf-box" aria-hidden="true"></span>
										<span class="pf-check-label"><?php echo esc_html( $pf_term->name ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>

					<?php if ( $pf_brand_terms ) : ?>
						<div class="pf-filter-group" id="pf-brand-group">
							<button type="button" class="pf-filter-group-title" aria-expanded="true" aria-controls="pf-group-brand">
								<span><?php esc_html_e( 'برند', 'lylyrose' ); ?></span>
								<?php echo $pf_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
							</button>
							<div class="pf-filter-options" id="pf-group-brand" role="group" aria-label="<?php esc_attr_e( 'برند', 'lylyrose' ); ?>">
								<div class="pf-brand-search">
									<?php echo $pf_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
									<input type="search" id="pf-brand-search" placeholder="<?php esc_attr_e( 'جستجوی برند…', 'lylyrose' ); ?>" aria-label="<?php esc_attr_e( 'جستجوی برند', 'lylyrose' ); ?>" autocomplete="off">
								</div>
								<div class="pf-brand-list" id="pf-brand-list">
									<?php foreach ( $pf_brand_terms as $pf_term ) :
										$pf_checked = in_array( (int) $pf_term->term_id, $pf_selected['pf_brand'], true );
										?>
										<label class="pf-check pf-brand-row<?php echo $pf_checked ? ' is-selected' : ''; ?>" data-pf-brand-name="<?php echo esc_attr( $pf_term->name ); ?>">
											<input type="checkbox" name="pf_brand[]" value="<?php echo esc_attr( $pf_term->term_id ); ?>" <?php checked( $pf_checked ); ?>>
											<span class="pf-box" aria-hidden="true"></span>
											<span class="pf-check-label"><?php echo esc_html( $pf_term->name ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<?php if ( count( $pf_brand_terms ) > 6 ) : ?>
									<button type="button" class="pf-brand-more" data-pf-brand-more aria-expanded="false"><?php esc_html_e( 'نمایش همه برندها', 'lylyrose' ); ?> <span aria-hidden="true">‹</span></button>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php
					// Volume buckets are derived from titles, so only buckets that the
					// catalogue actually fills are offered.
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
						<div class="pf-filter-group">
							<button type="button" class="pf-filter-group-title" aria-expanded="true" aria-controls="pf-group-volume">
								<span><?php esc_html_e( 'حجم', 'lylyrose' ); ?></span>
								<?php echo $pf_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
							</button>
							<div class="pf-filter-options" id="pf-group-volume" role="group" aria-label="<?php esc_attr_e( 'حجم', 'lylyrose' ); ?>">
								<?php foreach ( $pf_ml_available as $pf_key => $pf_bucket ) : ?>
									<label class="pf-check">
										<input type="radio" name="pf_ml" value="<?php echo esc_attr( $pf_key ); ?>" <?php checked( $pf_ml, $pf_key ); ?>>
										<span class="pf-box" aria-hidden="true"></span>
										<span class="pf-check-label"><?php echo esc_html( $pf_bucket['label'] ); ?></span>
									</label>
								<?php endforeach; ?>
								<label class="pf-check">
									<input type="radio" name="pf_ml" value="" <?php checked( '', $pf_ml ); ?>>
									<span class="pf-box" aria-hidden="true"></span>
									<span class="pf-check-label"><?php esc_html_e( 'همه حجم‌ها', 'lylyrose' ); ?></span>
								</label>
							</div>
						</div>
					<?php endif; ?>

					<div class="pf-filter-actions">
						<a class="pf-clear" href="<?php echo esc_url( $pf_base_url ); ?>"><?php esc_html_e( 'پاک کردن فیلترها', 'lylyrose' ); ?></a>
						<noscript><button type="submit" class="pf-apply"><?php esc_html_e( 'اعمال فیلترها', 'lylyrose' ); ?></button></noscript>
					</div>
				</form>

				<?php if ( $pf_brand_terms ) : ?>
					<div class="pf-brand-suggest">
						<h3><?php esc_html_e( 'برندهای پیشنهادی', 'lylyrose' ); ?></h3>
						<ul>
							<?php foreach ( array_slice( $pf_brand_terms, 0, 3 ) as $pf_term ) : ?>
								<li><a href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_brand' => array( $pf_term->term_id ) ) ) . '#pf-grid' ); ?>"><?php echo esc_html( $pf_term->name ); ?> <span aria-hidden="true">‹</span></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</aside>

			<div class="pf-content">
				<button type="button" class="pf-filter-trigger" data-pf-open-filters aria-controls="pf-filters" aria-expanded="false">
					<?php echo $pf_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
					<?php esc_html_e( 'فیلترها', 'lylyrose' ); ?>
					<?php if ( $pf_active_count ) : ?><span class="pf-filter-count"><?php echo esc_html( lylyrose_fa_num( $pf_active_count ) ); ?></span><?php endif; ?>
				</button>

				<div class="pf-toolbar">
					<h2 class="pf-products-title">
						<?php echo $pf_icon( 'bottle' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
						<?php esc_html_e( 'جدیدترین عطرها', 'lylyrose' ); ?>
					</h2>
					<div class="pf-toolbar-sort">
						<span><?php esc_html_e( 'مرتب‌سازی بر اساس:', 'lylyrose' ); ?></span>
						<select id="pf-sort-select" aria-label="<?php esc_attr_e( 'مرتب‌سازی', 'lylyrose' ); ?>">
							<?php foreach ( $pf_sort_options as $pf_key => $pf_label ) : ?>
								<option value="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_sort' => 'newest' === $pf_key ? '' : $pf_key ) ) . '#pf-grid' ); ?>"
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
							$pf_brand    = wp_get_post_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
							$pf_rating   = (float) $product->get_average_rating();
							$pf_reviews  = (int) $product->get_review_count();
							$pf_tags     = array_merge( $pf_genders, array_slice( $pf_families, 0, 2 ) );
							$pf_in_stock = $product->is_in_stock();
							?>
							<li class="pf-card<?php echo $pf_in_stock ? '' : ' is-out'; ?>">
								<div class="pf-card-media">
									<?php if ( $pf_percent ) : ?>
										<span class="pf-badge"><?php echo esc_html( lylyrose_fa_num( $pf_percent ) ); ?>٪</span>
									<?php endif; ?>
									<?php if ( ! $pf_in_stock ) : ?>
										<span class="pf-out-badge"><?php esc_html_e( 'ناموجود', 'lylyrose' ); ?></span>
									<?php endif; ?>
									<button type="button" class="pf-wish" aria-label="<?php echo esc_attr( sprintf( __( 'افزودن %s به علاقه‌مندی‌ها', 'lylyrose' ), $product->get_name() ) ); ?>" aria-pressed="false">
										<?php echo $pf_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
									</button>
									<a class="pf-card-photo" href="<?php echo esc_url( $product->get_permalink() ); ?>">
										<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'class' => 'pf-product-img' ) ); ?>
									</a>
								</div>

								<h3 class="pf-card-title">
									<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
								</h3>

								<?php if ( $pf_brand ) : ?>
									<p class="pf-card-brand"><?php echo esc_html( $pf_brand[0] ); ?></p>
								<?php endif; ?>

								<?php if ( $pf_tags ) : ?>
									<p class="pf-card-tags"><?php echo esc_html( implode( ' | ', array_filter( $pf_tags ) ) ); ?></p>
								<?php endif; ?>

								<?php if ( $pf_reviews > 0 ) : ?>
									<div class="pf-card-rating">
										<?php echo $pf_stars( $pf_rating ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
										<span class="pf-rating-num">(<?php echo esc_html( lylyrose_fa_num( number_format_i18n( $pf_rating, 1 ) ) ); ?>)</span>
										<span class="pf-rating-count"><?php echo esc_html( lylyrose_fa_num( $pf_reviews ) ); ?></span>
									</div>
								<?php else : ?>
									<div class="pf-card-rating is-empty"><?php esc_html_e( 'بدون امتیاز', 'lylyrose' ); ?></div>
								<?php endif; ?>

								<div class="pf-card-price">
									<strong><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></strong>
									<?php if ( $product->is_on_sale() && $product->get_regular_price() ) : ?>
										<del><?php echo wp_kses_post( wc_price( $product->get_regular_price() ) ); ?></del>
									<?php endif; ?>
								</div>

								<?php if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $pf_in_stock ) : ?>
									<button type="button" class="pf-add" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-pf-add aria-label="<?php echo esc_attr( sprintf( __( 'افزودن %s به سبد خرید', 'lylyrose' ), $product->get_name() ) ); ?>">
										<?php echo $pf_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
										<?php esc_html_e( 'افزودن به سبد خرید', 'lylyrose' ); ?>
									</button>
								<?php elseif ( ! $pf_in_stock ) : ?>
									<button type="button" class="pf-add is-disabled" disabled><?php esc_html_e( 'ناموجود', 'lylyrose' ); ?></button>
								<?php else : ?>
									<a class="pf-add pf-add-link" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php esc_html_e( 'مشاهده محصول', 'lylyrose' ); ?></a>
								<?php endif; ?>
							</li>
						<?php endwhile; ?>
					</ul>

					<?php
					$pf_total_pages = (int) $pf_query->max_num_pages;
					if ( $pf_total_pages > 1 ) :
						$pf_show   = array( 1, $pf_total_pages );
						for ( $pf_i = max( 1, $pf_page - 1 ); $pf_i <= min( $pf_total_pages, $pf_page + 1 ); $pf_i++ ) {
							$pf_show[] = $pf_i;
						}
						$pf_show = array_values( array_unique( $pf_show ) );
						sort( $pf_show );
						?>
						<nav class="pf-pagination" aria-label="<?php esc_attr_e( 'صفحه‌بندی', 'lylyrose' ); ?>">
							<?php if ( $pf_page > 1 ) : ?>
								<a class="pf-page-arrow" href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_page' => $pf_page - 1 ) ) . '#pf-grid' ); ?>" aria-label="<?php esc_attr_e( 'صفحه قبل', 'lylyrose' ); ?>">›</a>
							<?php endif; ?>
							<?php
							$pf_prev = 0;
							foreach ( $pf_show as $pf_num ) :
								if ( $pf_prev && $pf_num - $pf_prev > 1 ) : ?>
									<span class="pf-page-gap" aria-hidden="true">…</span>
								<?php endif;
								$pf_prev = $pf_num;
								if ( $pf_num === $pf_page ) : ?>
									<span class="pf-page is-current" aria-current="page"><?php echo esc_html( lylyrose_fa_num( $pf_num ) ); ?></span>
								<?php else : ?>
									<a class="pf-page" href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_page' => $pf_num ) ) . '#pf-grid' ); ?>"><?php echo esc_html( lylyrose_fa_num( $pf_num ) ); ?></a>
								<?php endif;
							endforeach;
							?>
							<?php if ( $pf_page < $pf_total_pages ) : ?>
								<a class="pf-page-arrow" href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_page' => $pf_page + 1 ) ) . '#pf-grid' ); ?>" aria-label="<?php esc_attr_e( 'صفحه بعد', 'lylyrose' ); ?>">‹</a>
							<?php endif; ?>
						</nav>
					<?php endif; ?>
				<?php else : ?>
					<div class="pf-empty">
						<h2><?php esc_html_e( 'عطری مطابق فیلترهای شما پیدا نشد', 'lylyrose' ); ?></h2>
						<p><?php esc_html_e( 'فیلترها را تغییر دهید یا همه عطرها را ببینید.', 'lylyrose' ); ?></p>
						<a class="pf-btn pf-btn-primary" href="<?php echo esc_url( $pf_base_url ); ?>"><?php esc_html_e( 'نمایش همه عطرها', 'lylyrose' ); ?></a>
					</div>
				<?php endif; ?>
				<?php wp_reset_postdata(); ?>

				<!-- ================= Guide banner ================= -->
				<section class="pf-guide" aria-labelledby="pf-guide-title">
					<div class="pf-guide-copy">
						<h2 id="pf-guide-title"><?php echo esc_html( lylyrose_text( 'perfume.guide_title', __( 'راهنمای انتخاب عطر', 'lylyrose' ) ) ); ?></h2>
						<p><?php echo esc_html( lylyrose_text( 'perfume.guide_copy', __( 'با چند سوال کوتاه، عطر مناسب شخصیت و سلیقه‌ات رو پیدا کن.', 'lylyrose' ) ) ); ?></p>
						<a class="pf-btn pf-btn-primary" href="<?php echo esc_url( $pf_finder_url ); ?>"><?php echo esc_html( lylyrose_text( 'perfume.guide_cta', __( 'شروع تست انتخاب عطر', 'lylyrose' ) ) ); ?> <?php echo $pf_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></a>
					</div>
					<ul class="pf-guide-badges">
						<?php
						$pf_badges = array(
							array( 'label' => __( 'ضمانت اصالت', 'lylyrose' ), 'icon' => 'shield' ),
							array( 'label' => __( 'ارسال سریع', 'lylyrose' ), 'icon' => 'truck' ),
							array( 'label' => __( 'مشاوره تخصصی', 'lylyrose' ), 'icon' => 'support' ),
							array( 'label' => __( 'پرداخت امن', 'lylyrose' ), 'icon' => 'lock' ),
						);
						foreach ( $pf_badges as $pf_badge ) : ?>
							<li>
								<span aria-hidden="true"><?php echo $pf_icon( $pf_badge['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
								<?php echo esc_html( $pf_badge['label'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<div class="pf-guide-media">
						<img src="<?php echo esc_url( lylyrose_asset( 'perfume.guide', $pf_theme_img . 'guide.webp' ) ); ?>" alt="" width="600" height="480" loading="lazy" decoding="async">
					</div>
				</section>

				<!-- ================= Best sellers ================= -->
				<?php if ( $pf_best->have_posts() ) : ?>
					<section class="pf-best" aria-labelledby="pf-best-title">
						<div class="pf-section-head">
							<h2 id="pf-best-title"><?php esc_html_e( 'پرفروش‌ترین عطرها', 'lylyrose' ); ?></h2>
							<a class="pf-section-more" href="<?php echo esc_url( pf_url( $pf_base_url, array( 'pf_sort' => 'popular' ) ) . '#pf-grid' ); ?>"><?php esc_html_e( 'مشاهده همه ‹', 'lylyrose' ); ?></a>
						</div>
						<div class="pf-best-rail">
							<button type="button" class="pf-rail-nav pf-rail-prev" aria-label="<?php esc_attr_e( 'قبلی', 'lylyrose' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
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
									$pf_b_brand   = wp_get_post_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
									$pf_b_family  = wp_get_post_terms( $product->get_id(), 'pa_fragrance_family', array( 'fields' => 'names' ) );
									$pf_b_tags    = array_merge( array_slice( $pf_b_brand, 0, 1 ), array_slice( $pf_b_family, 0, 1 ) );
									?>
									<a class="pf-best-card" href="<?php echo esc_url( $product->get_permalink() ); ?>">
										<span class="pf-best-media">
											<?php if ( $pf_b_percent ) : ?>
												<span class="pf-badge"><?php echo esc_html( lylyrose_fa_num( $pf_b_percent ) ); ?>٪</span>
											<?php endif; ?>
											<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'class' => 'pf-product-img' ) ); ?>
										</span>
										<span class="pf-best-name"><?php echo esc_html( $product->get_name() ); ?></span>
										<?php if ( $pf_b_tags ) : ?>
											<span class="pf-best-tags"><?php echo esc_html( implode( ' | ', $pf_b_tags ) ); ?></span>
										<?php endif; ?>
										<?php if ( $pf_b_rating > 0 ) : ?>
											<span class="pf-best-rating"><?php echo $pf_stars( $pf_b_rating ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?> <span><?php echo esc_html( lylyrose_fa_num( number_format_i18n( $pf_b_rating, 1 ) ) ); ?></span></span>
										<?php endif; ?>
										<span class="pf-best-price"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span>
									</a>
								<?php endwhile; ?>
							</div>
							<button type="button" class="pf-rail-nav pf-rail-next" aria-label="<?php esc_attr_e( 'بعدی', 'lylyrose' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>
						</div>
					</section>
				<?php wp_reset_postdata(); endif; ?>
			</div>
		</div>
	</div>

	<div class="pf-filter-overlay" data-pf-close-filters hidden></div>
	<div class="pf-toast" id="pf-toast" role="status" aria-live="polite"></div>
</main>

<?php get_footer(); ?>
