<?php
/**
 * Shared body for the care landing pages (/skincare/, /haircare/).
 *
 * Included by page-skincare.php and page-haircare.php, each of which sets
 * `$care` first. The two pages are the same components with different copy,
 * taxonomy and tiles, so the markup lives here once and each page file stays a
 * small, independently editable config — the point of keeping them standalone
 * without duplicating 700 lines.
 *
 * Modelled on page-perfume.php. Differences, and why:
 *   - The care trees are single-brand (St. Ives), so there is no brand facet.
 *   - Nothing in either tree has a rating or a sale, so "popular"/"rated" sorts
 *     and a "best sellers" rail would be empty claims. The rail shows new
 *     arrivals instead.
 *   - Facets come from pa_skin_type/pa_skin_concern (skin) and
 *     pa_hair_type/pa_hair_concern (hair) — real taxonomies migrated from the
 *     _needs_* meta by docker/migrate-care-attributes.php.
 *
 * @package lylyrose
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $care ) || ! is_array( $care ) ) {
	return;
}

if ( ! function_exists( 'WC' ) ) {
	get_header();
	echo '<main class="dk-container"><p>' . esc_html__( 'فروشگاه در دسترس نیست.', 'lylyrose' ) . '</p></main>';
	get_footer();
	return;
}

$care_page_id  = get_queried_object_id();
$care_base_url = get_permalink( $care_page_id );
$care_cat      = get_term_by( 'slug', $care['root'], 'product_cat' );

/* GET facets. Any positive term id arrays. */
$care_tax_facets = array(
	'care_type'    => $care['type_tax'],
	'care_concern' => $care['concern_tax'],
	'care_cat'     => 'product_cat',
);

$care_selected = array();
foreach ( array_keys( $care_tax_facets ) as $care_param ) {
	$care_ids = isset( $_GET[ $care_param ] ) ? array_map( 'absint', (array) wp_unslash( $_GET[ $care_param ] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$care_selected[ $care_param ] = array_values( array_filter( $care_ids ) );
}

$care_sort = isset( $_GET['care_sort'] ) ? sanitize_key( wp_unslash( $_GET['care_sort'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$care_sort = in_array( $care_sort, array( 'newest', 'cheapest', 'expensive' ), true ) ? $care_sort : '';
$care_page = isset( $_GET['care_page'] ) ? max( 1, absint( wp_unslash( $_GET['care_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$care_page = min( $care_page, 999 );

if ( ! function_exists( 'care_url' ) ) {
	/**
	 * This page's URL with GET params added/replaced; an empty value removes the
	 * param. Page number is dropped unless the caller sets it (pagination), so a
	 * filter never lands on an out-of-range page.
	 */
	function care_url( $base, $changes = array() ) {
		$params = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $params['paged'], $params['care_page'] );
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

/* -------------------------------------------------------------------------
   The grid query.
   ------------------------------------------------------------------------- */
$care_args = array(
	'post_type'           => 'product',
	'post_status'         => 'publish',
	'posts_per_page'      => 8,
	'paged'               => $care_page,
	'ignore_sticky_posts' => true,
);

if ( $care_cat ) {
	$care_args['tax_query'] = array( array(
		'taxonomy' => 'product_cat',
		'field'    => 'term_id',
		'terms'    => $care_cat->term_id,
	) );
}

/*
 * The type/concern facets. A product may carry several terms in each, so a
 * multi-select is IN (any of the chosen), matching the shop's behaviour.
 */
foreach ( array( 'care_type', 'care_concern' ) as $care_param ) {
	if ( empty( $care_selected[ $care_param ] ) ) {
		continue;
	}
	$care_args['tax_query'][] = array(
		'taxonomy' => $care_tax_facets[ $care_param ],
		'field'    => 'term_id',
		'terms'    => $care_selected[ $care_param ],
		'operator' => 'IN',
	);
}

/*
 * The subcategory facet is constrained to this tree's own children. Without the
 * parent clause, a term id from another tree could be passed in the URL and
 * would widen rather than narrow the result.
 */
if ( ! empty( $care_selected['care_cat'] ) ) {
	$care_sub_ids = array();
	if ( $care_cat ) {
		$care_sub_ids = array_map( 'intval', get_term_children( $care_cat->term_id, 'product_cat' ) );
	}
	$care_keep = array_intersect( $care_selected['care_cat'], $care_sub_ids );
	$care_args['tax_query'][] = array(
		'taxonomy' => 'product_cat',
		'field'    => 'term_id',
		'terms'    => $care_keep ? $care_keep : array( 0 ),
		'operator' => 'IN',
	);
}

switch ( $care_sort ) {
	case 'cheapest':
		$care_args['meta_key'] = '_price';
		$care_args['orderby']  = 'meta_value_num';
		$care_args['order']    = 'ASC';
		break;
	case 'expensive':
		$care_args['meta_key'] = '_price';
		$care_args['orderby']  = 'meta_value_num';
		$care_args['order']    = 'DESC';
		break;
	case 'newest':
	default:
		$care_args['orderby'] = 'date';
		$care_args['order']   = 'DESC';
}

$care_query = new WP_Query( $care_args );

$care_sort_options = array(
	'newest'    => __( 'جدیدترین', 'lylyrose' ),
	'cheapest'  => __( 'ارزان‌ترین', 'lylyrose' ),
	'expensive' => __( 'گران‌ترین', 'lylyrose' ),
);

$care_active_count = 0;
foreach ( $care_selected as $care_ids ) {
	$care_active_count += count( $care_ids );
}

/* Facet terms, with counts, resolved once. */
$care_type_terms    = get_terms( array( 'taxonomy' => $care['type_tax'], 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
$care_concern_terms = get_terms( array( 'taxonomy' => $care['concern_tax'], 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
$care_type_terms    = is_wp_error( $care_type_terms ) ? array() : $care_type_terms;
$care_concern_terms = is_wp_error( $care_concern_terms ) ? array() : $care_concern_terms;

/* Subcategory terms for this tree, non-empty only. Drives both the tiles and
 * the rail's category group. */
$care_sub_terms = array();
if ( $care_cat ) {
	$care_raw_subs = get_terms( array(
		'taxonomy'   => 'product_cat',
		'parent'     => $care_cat->term_id,
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	) );
	$care_sub_terms = is_wp_error( $care_raw_subs ) ? array() : $care_raw_subs;
}

/*
 * The tile row. Each tile is a subcategory resolved against live terms, so a
 * renamed term follows the rename and an empty one is skipped rather than
 * rendered as a dead link. The declared order is the merchandising order; any
 * live term the list does not name is appended after it.
 */
$care_tiles = array();
$care_by_slug = array();
foreach ( $care_sub_terms as $care_term ) {
	$care_by_slug[ $care_term->slug ] = $care_term;
}
foreach ( $care['tile_order'] as $care_slug ) {
	if ( isset( $care_by_slug[ $care_slug ] ) ) {
		$care_tiles[] = $care_by_slug[ $care_slug ];
		unset( $care_by_slug[ $care_slug ] );
	}
}
foreach ( $care_by_slug as $care_term ) {
	$care_tiles[] = $care_term;
}

$care_new = new WP_Query( array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 10,
	'no_found_rows'  => true,
	'tax_query'      => $care_cat ? array( array(
		'taxonomy' => 'product_cat',
		'field'    => 'term_id',
		'terms'    => $care_cat->term_id,
	) ) : array(),
) );

$care_quiz_url = $care['quiz_url'];
$care_img      = get_theme_file_uri( 'assets/images/' . $care['slug'] . '/' );

/** Inline line icons. Static markup only. */
$care_icon = static function ( $name ) {
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
		'drop'    => '<path d="M12 3s6 6.6 6 10.5a6 6 0 0 1-12 0C6 9.6 12 3 12 3z"/>',
		'chevron' => '<path d="M6 9l6 6 6-6"/>',
		'close'   => '<path d="M18 6 6 18M6 6l12 12"/>',
		'arrow'   => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
	);
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $icons[ $name ] ?? '' ) . '</svg>';
};

/** Star row with partial fill, clipped by --pf-rating. */
$care_stars = static function ( $rating ) {
	$rating = max( 0, min( 5, (float) $rating ) );
	$label  = sprintf(
		/* translators: %s: average rating out of five. */
		__( 'امتیاز %s از ۵', 'lylyrose' ),
		lylyrose_fa_num( number_format_i18n( $rating, 1 ) )
	);
	return '<span class="pf-stars" style="--pf-rating:' . esc_attr( $rating ) . '" role="img" aria-label="' . esc_attr( $label ) . '"><span class="pf-stars-base" aria-hidden="true">★★★★★</span><span class="pf-stars-fill" aria-hidden="true">★★★★★</span></span>';
};

/** One facet checkbox group. */
if ( ! function_exists( 'care_filter_group' ) ) {
	function care_filter_group( $param, $label, $terms, $selected, $care_icon ) {
		if ( empty( $terms ) ) {
			return;
		}
		$group_id = 'care-group-' . $param;
		?>
		<div class="pf-filter-group">
			<button type="button" class="pf-filter-group-title" aria-expanded="true" aria-controls="<?php echo esc_attr( $group_id ); ?>">
				<span><?php echo esc_html( $label ); ?></span>
				<?php echo $care_icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
			</button>
			<div class="pf-filter-options" id="<?php echo esc_attr( $group_id ); ?>" role="group" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php foreach ( $terms as $term ) :
					$checked = in_array( (int) $term->term_id, $selected, true );
					?>
					<label class="pf-check">
						<input type="checkbox" name="<?php echo esc_attr( $param ); ?>[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( $checked ); ?>>
						<span class="pf-box" aria-hidden="true"></span>
						<span class="pf-check-label"><?php echo esc_html( $term->name ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

get_header();
?>

<main class="dk-main pf-main">

	<!-- ================= Hero ================= -->
	<section class="pf-hero" aria-labelledby="care-hero-title">
		<img class="pf-hero-img" src="<?php echo esc_url( $care_img . 'hero.webp' ); ?>" alt="" width="1680" height="403" fetchpriority="high" decoding="async">
		<span class="pf-hero-scrim" aria-hidden="true"></span>
		<div class="pf-shell pf-hero-inner">
			<div class="pf-hero-copy">
				<h1 id="care-hero-title"><?php echo esc_html( $care['hero_title'] ); ?></h1>
				<p><?php echo esc_html( $care['hero_copy'] ); ?></p>
				<a class="pf-btn pf-btn-primary" href="#care-grid"><?php echo esc_html( $care['hero_cta'] ); ?> <?php echo $care_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></a>
			</div>
		</div>
	</section>

	<div class="pf-shell">

		<!-- ================= Quick access ================= -->
		<section class="pf-quick" aria-label="<?php echo esc_attr( $care['quick_label'] ); ?>">
			<a class="pf-quick-card pf-quick-finder" href="<?php echo esc_url( $care_quiz_url ); ?>">
				<span class="pf-quick-body">
					<span class="pf-quick-icon" aria-hidden="true"><?php echo $care_icon( 'drop' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
					<b><?php echo esc_html( $care['quiz_card_title'] ); ?></b>
					<small><?php echo esc_html( $care['quiz_card_copy'] ); ?></small>
					<span class="pf-quick-cta"><?php echo esc_html( $care['quiz_card_cta'] ); ?> <?php echo $care_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
				</span>
			</a>

			<a class="pf-quick-card" href="#pf-filters" data-pf-open-filters>
				<span class="pf-quick-body">
					<span class="pf-quick-icon" aria-hidden="true"><?php echo $care_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
					<b><?php esc_html_e( 'می‌خوام خودم بگردم', 'lylyrose' ); ?></b>
					<small><?php esc_html_e( 'با فیلتر نوع و نیاز، محصول مناسب خودت رو پیدا کن', 'lylyrose' ); ?></small>
					<span class="pf-quick-go" aria-hidden="true">‹</span>
				</span>
			</a>

			<a class="pf-quick-card" href="#care-grid" data-pf-focus-search>
				<span class="pf-quick-body">
					<span class="pf-quick-icon" aria-hidden="true"><?php echo $care_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
					<b><?php esc_html_e( 'محصول رو می‌شناسم', 'lylyrose' ); ?></b>
					<small><?php esc_html_e( 'اسم محصول یا برندش رو می‌دونی؟ همین‌جا جستجو کن', 'lylyrose' ); ?></small>
					<span class="pf-quick-go" aria-hidden="true">‹</span>
				</span>
			</a>
		</section>

		<!-- ================= Subcategory tiles ================= -->
		<?php if ( $care_tiles ) : ?>
			<section class="pf-families" aria-labelledby="care-tiles-title">
				<div class="pf-section-head">
					<h2 id="care-tiles-title"><?php echo esc_html( $care['tiles_title'] ); ?></h2>
				</div>
				<div class="pf-family-row" style="--pf-tiles:<?php echo (int) count( $care_tiles ); ?>">
					<?php foreach ( $care_tiles as $care_tile ) :
						$care_tile_active = in_array( (int) $care_tile->term_id, $care_selected['care_cat'], true );
						$care_tile_url    = care_url( $care_base_url, array( 'care_cat' => $care_tile_active ? array() : array( $care_tile->term_id ) ) ) . '#care-grid';
						/* A composed lifestyle photo per subcategory, built by
						 * docker/compose-care-images.py. Falls back to the term's own
						 * product image so a tile for a new subcategory still shows
						 * something real without a rebuild. */
						$care_tile_rel = 'assets/images/' . $care['slug'] . '/tile-' . $care_tile->slug . '.webp';
						$care_tile_img = file_exists( get_theme_file_path( $care_tile_rel ) )
							? get_theme_file_uri( $care_tile_rel )
							: lylyrose_term_image( $care_tile );
						?>
						<a class="pf-family<?php echo $care_tile_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $care_tile_url ); ?>"<?php echo $care_tile_active ? ' aria-current="true"' : ''; ?>>
							<span class="pf-family-img">
								<?php if ( $care_tile_img ) : ?>
									<img src="<?php echo esc_url( $care_tile_img ); ?>" alt="" width="320" height="154" loading="lazy" decoding="async">
								<?php else : ?>
									<span class="pf-family-icon" aria-hidden="true"><?php echo $care_icon( 'bottle' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
								<?php endif; ?>
							</span>
							<span class="pf-family-name"><?php echo esc_html( $care_tile->name ); ?> <span aria-hidden="true">‹</span></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<!-- ================= Grid + filters ================= -->
		<div class="pf-layout" id="care-grid">

			<aside class="pf-filters" id="pf-filters" aria-label="<?php esc_attr_e( 'فیلترها', 'lylyrose' ); ?>">
				<div class="pf-filter-head">
					<span class="pf-filter-title"><?php echo $care_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?><?php esc_html_e( 'فیلترها', 'lylyrose' ); ?></span>
					<button type="button" class="pf-filter-close" data-pf-close-filters aria-label="<?php esc_attr_e( 'بستن فیلترها', 'lylyrose' ); ?>"><?php echo $care_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></button>
				</div>

				<form method="get" action="<?php echo esc_url( $care_base_url ); ?>" class="pf-filter-form" id="pf-filter-form">
					<?php if ( $care_sort ) : ?><input type="hidden" name="care_sort" value="<?php echo esc_attr( $care_sort ); ?>"><?php endif; ?>

					<?php
					care_filter_group( 'care_type', $care['type_label'], $care_type_terms, $care_selected['care_type'], $care_icon );
					care_filter_group( 'care_concern', $care['concern_label'], $care_concern_terms, $care_selected['care_concern'], $care_icon );
					care_filter_group( 'care_cat', __( 'دسته‌بندی', 'lylyrose' ), $care_sub_terms, $care_selected['care_cat'], $care_icon );
					?>

					<div class="pf-filter-actions">
						<a class="pf-clear" href="<?php echo esc_url( $care_base_url ); ?>"><?php esc_html_e( 'پاک کردن فیلترها', 'lylyrose' ); ?></a>
						<noscript><button type="submit" class="pf-apply"><?php esc_html_e( 'اعمال فیلترها', 'lylyrose' ); ?></button></noscript>
					</div>
				</form>
			</aside>

			<div class="pf-content">
				<button type="button" class="pf-filter-trigger" data-pf-open-filters aria-controls="pf-filters" aria-expanded="false">
					<?php echo $care_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
					<?php esc_html_e( 'فیلترها', 'lylyrose' ); ?>
					<?php if ( $care_active_count ) : ?><span class="pf-filter-count"><?php echo esc_html( lylyrose_fa_num( $care_active_count ) ); ?></span><?php endif; ?>
				</button>

				<div class="pf-toolbar">
					<h2 class="pf-products-title">
						<?php echo $care_icon( 'bottle' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
						<?php esc_html_e( 'جدیدترین محصولات', 'lylyrose' ); ?>
					</h2>
					<div class="pf-toolbar-sort">
						<span><?php esc_html_e( 'مرتب‌سازی بر اساس:', 'lylyrose' ); ?></span>
						<select id="pf-sort-select" aria-label="<?php esc_attr_e( 'مرتب‌سازی', 'lylyrose' ); ?>">
							<?php foreach ( $care_sort_options as $care_key => $care_label ) : ?>
								<option value="<?php echo esc_url( care_url( $care_base_url, array( 'care_sort' => 'newest' === $care_key ? '' : $care_key ) ) . '#care-grid' ); ?>"
									<?php selected( $care_sort ? $care_sort : 'newest', $care_key ); ?>><?php echo esc_html( $care_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<?php if ( $care_query->have_posts() ) : ?>
					<ul class="pf-grid">
						<?php
						while ( $care_query->have_posts() ) :
							$care_query->the_post();
							global $product;
							if ( ! $product instanceof WC_Product ) {
								continue;
							}
							$care_percent = lylyrose_discount_percent( $product );
							$care_brand   = wp_get_post_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
							$care_rating  = (float) $product->get_average_rating();
							$care_reviews = (int) $product->get_review_count();
							$care_in      = $product->is_in_stock();
							$care_tags    = array_merge(
								(array) wp_get_post_terms( $product->get_id(), $care['type_tax'], array( 'fields' => 'names' ) ),
								array_slice( (array) wp_get_post_terms( $product->get_id(), $care['concern_tax'], array( 'fields' => 'names' ) ), 0, 2 )
							);
							?>
							<li class="pf-card<?php echo $care_in ? '' : ' is-out'; ?>">
								<div class="pf-card-media">
									<?php if ( $care_percent ) : ?>
										<span class="pf-badge"><?php echo esc_html( lylyrose_fa_num( $care_percent ) ); ?>٪</span>
									<?php endif; ?>
									<?php if ( ! $care_in ) : ?>
										<span class="pf-out-badge"><?php esc_html_e( 'ناموجود', 'lylyrose' ); ?></span>
									<?php endif; ?>
									<button type="button" class="pf-wish" aria-label="<?php echo esc_attr( sprintf( __( 'افزودن %s به علاقه‌مندی‌ها', 'lylyrose' ), $product->get_name() ) ); ?>" aria-pressed="false">
										<?php echo $care_icon( 'heart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
									</button>
									<a class="pf-card-photo" href="<?php echo esc_url( $product->get_permalink() ); ?>">
										<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'class' => 'pf-product-img' ) ); ?>
									</a>
								</div>

								<h3 class="pf-card-title">
									<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
								</h3>

								<?php if ( $care_brand ) : ?>
									<p class="pf-card-brand"><?php echo esc_html( $care_brand[0] ); ?></p>
								<?php endif; ?>

								<?php if ( $care_tags ) : ?>
									<p class="pf-card-tags"><?php echo esc_html( implode( ' | ', array_filter( $care_tags ) ) ); ?></p>
								<?php endif; ?>

								<?php if ( $care_reviews > 0 ) : ?>
									<div class="pf-card-rating">
										<?php echo $care_stars( $care_rating ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
										<span class="pf-rating-num">(<?php echo esc_html( lylyrose_fa_num( number_format_i18n( $care_rating, 1 ) ) ); ?>)</span>
										<span class="pf-rating-count"><?php echo esc_html( lylyrose_fa_num( $care_reviews ) ); ?></span>
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

								<?php if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $care_in ) : ?>
									<button type="button" class="pf-add" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-pf-add aria-label="<?php echo esc_attr( sprintf( __( 'افزودن %s به سبد خرید', 'lylyrose' ), $product->get_name() ) ); ?>">
										<?php echo $care_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?>
										<?php esc_html_e( 'افزودن به سبد خرید', 'lylyrose' ); ?>
									</button>
								<?php elseif ( ! $care_in ) : ?>
									<button type="button" class="pf-add is-disabled" disabled><?php esc_html_e( 'ناموجود', 'lylyrose' ); ?></button>
								<?php else : ?>
									<a class="pf-add pf-add-link" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php esc_html_e( 'مشاهده محصول', 'lylyrose' ); ?></a>
								<?php endif; ?>
							</li>
						<?php endwhile; ?>
					</ul>

					<?php
					$care_total_pages = (int) $care_query->max_num_pages;
					if ( $care_total_pages > 1 ) :
						$care_show = array( 1, $care_total_pages );
						for ( $care_i = max( 1, $care_page - 1 ); $care_i <= min( $care_total_pages, $care_page + 1 ); $care_i++ ) {
							$care_show[] = $care_i;
						}
						$care_show = array_values( array_unique( $care_show ) );
						sort( $care_show );
						?>
						<nav class="pf-pagination" aria-label="<?php esc_attr_e( 'صفحه‌بندی', 'lylyrose' ); ?>">
							<?php if ( $care_page > 1 ) : ?>
								<a class="pf-page-arrow" href="<?php echo esc_url( care_url( $care_base_url, array( 'care_page' => $care_page - 1 ) ) . '#care-grid' ); ?>" aria-label="<?php esc_attr_e( 'صفحه قبل', 'lylyrose' ); ?>">›</a>
							<?php endif; ?>
							<?php
							$care_prev = 0;
							foreach ( $care_show as $care_num ) :
								if ( $care_prev && $care_num - $care_prev > 1 ) : ?>
									<span class="pf-page-gap" aria-hidden="true">…</span>
								<?php endif;
								$care_prev = $care_num;
								if ( $care_num === $care_page ) : ?>
									<span class="pf-page is-current" aria-current="page"><?php echo esc_html( lylyrose_fa_num( $care_num ) ); ?></span>
								<?php else : ?>
									<a class="pf-page" href="<?php echo esc_url( care_url( $care_base_url, array( 'care_page' => $care_num ) ) . '#care-grid' ); ?>"><?php echo esc_html( lylyrose_fa_num( $care_num ) ); ?></a>
								<?php endif;
							endforeach;
							?>
							<?php if ( $care_page < $care_total_pages ) : ?>
								<a class="pf-page-arrow" href="<?php echo esc_url( care_url( $care_base_url, array( 'care_page' => $care_page + 1 ) ) . '#care-grid' ); ?>" aria-label="<?php esc_attr_e( 'صفحه بعد', 'lylyrose' ); ?>">‹</a>
							<?php endif; ?>
						</nav>
					<?php endif; ?>
				<?php else : ?>
					<div class="pf-empty">
						<h2><?php echo esc_html( $care['empty_title'] ); ?></h2>
						<p><?php esc_html_e( 'فیلترها را تغییر دهید یا همه محصولات را ببینید.', 'lylyrose' ); ?></p>
						<a class="pf-btn pf-btn-primary" href="<?php echo esc_url( $care_base_url ); ?>"><?php echo esc_html( $care['empty_cta'] ); ?></a>
					</div>
				<?php endif; ?>
				<?php wp_reset_postdata(); ?>

				<!-- ================= Guide banner ================= -->
				<section class="pf-guide" aria-labelledby="care-guide-title">
					<div class="pf-guide-copy">
						<h2 id="care-guide-title"><?php echo esc_html( $care['guide_title'] ); ?></h2>
						<p><?php echo esc_html( $care['guide_copy'] ); ?></p>
						<a class="pf-btn pf-btn-primary" href="<?php echo esc_url( $care_quiz_url ); ?>"><?php echo esc_html( $care['guide_cta'] ); ?> <?php echo $care_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></a>
					</div>
					<ul class="pf-guide-badges">
						<?php
						$care_badges = array(
							array( 'label' => __( 'ضمانت اصالت', 'lylyrose' ), 'icon' => 'shield' ),
							array( 'label' => __( 'ارسال سریع', 'lylyrose' ), 'icon' => 'truck' ),
							array( 'label' => __( 'مشاوره تخصصی', 'lylyrose' ), 'icon' => 'support' ),
							array( 'label' => __( 'پرداخت امن', 'lylyrose' ), 'icon' => 'lock' ),
						);
						foreach ( $care_badges as $care_badge ) : ?>
							<li>
								<span aria-hidden="true"><?php echo $care_icon( $care_badge['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?></span>
								<?php echo esc_html( $care_badge['label'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<div class="pf-guide-media">
						<img src="<?php echo esc_url( $care_img . 'guide.webp' ); ?>" alt="" width="600" height="480" loading="lazy" decoding="async">
					</div>
				</section>

				<!-- ================= New arrivals rail ================= -->
				<?php if ( $care_new->have_posts() ) : ?>
					<section class="pf-best" aria-labelledby="care-new-title">
						<div class="pf-section-head">
							<h2 id="care-new-title"><?php echo esc_html( $care['rail_title'] ); ?></h2>
							<a class="pf-section-more" href="<?php echo esc_url( care_url( $care_base_url, array( 'care_sort' => '' ) ) . '#care-grid' ); ?>"><?php esc_html_e( 'مشاهده همه ‹', 'lylyrose' ); ?></a>
						</div>
						<div class="pf-best-rail">
							<button type="button" class="pf-rail-nav pf-rail-prev" aria-label="<?php esc_attr_e( 'قبلی', 'lylyrose' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
							<div class="pf-best-track" data-pf-track>
								<?php
								while ( $care_new->have_posts() ) :
									$care_new->the_post();
									global $product;
									if ( ! $product instanceof WC_Product ) {
										continue;
									}
									$care_b_percent = lylyrose_discount_percent( $product );
									$care_b_rating  = (float) $product->get_average_rating();
									$care_b_brand   = wp_get_post_terms( $product->get_id(), 'pa_brand', array( 'fields' => 'names' ) );
									$care_b_type    = wp_get_post_terms( $product->get_id(), $care['type_tax'], array( 'fields' => 'names' ) );
									$care_b_tags    = array_merge( array_slice( (array) $care_b_brand, 0, 1 ), array_slice( (array) $care_b_type, 0, 1 ) );
									?>
									<a class="pf-best-card" href="<?php echo esc_url( $product->get_permalink() ); ?>">
										<span class="pf-best-media">
											<?php if ( $care_b_percent ) : ?>
												<span class="pf-badge"><?php echo esc_html( lylyrose_fa_num( $care_b_percent ) ); ?>٪</span>
											<?php endif; ?>
											<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'class' => 'pf-product-img' ) ); ?>
										</span>
										<span class="pf-best-name"><?php echo esc_html( $product->get_name() ); ?></span>
										<?php if ( $care_b_tags ) : ?>
											<span class="pf-best-tags"><?php echo esc_html( implode( ' | ', array_filter( $care_b_tags ) ) ); ?></span>
										<?php endif; ?>
										<?php if ( $care_b_rating > 0 ) : ?>
											<span class="pf-best-rating"><?php echo $care_stars( $care_b_rating ); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?> <span><?php echo esc_html( lylyrose_fa_num( number_format_i18n( $care_b_rating, 1 ) ) ); ?></span></span>
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
