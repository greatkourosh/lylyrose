<?php
 defined( 'ABSPATH' ) || exit;

class ASC_Flash_Sales {
	const PER_PAGE    = 24;
	const HERO_COUNT  = 12;
	const MIN_BRAND  = 3;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_page' ), 30 );
	}

	public static function ensure_page() {
		if ( get_option( 'asc_offers_page_created' ) ) {
			return;
		}
		$page = get_page_by_path( 'incredible-offers' );
		$id = $page ? $page->ID : wp_insert_post( array(
			'post_type' => 'page', 'post_name' => 'incredible-offers',
			'post_title' => 'پیشنهادهای شگفت‌انگیز', 'post_status' => 'publish',
			'comment_status' => 'closed',
		), true );
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( 'asc_offers_page_created', 1 );
		}
	}

	public static function param( $key, $default = '' ) {
		return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] )
			? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
	}

	public static function query_products( $category = 0 ) {
		$tax_query = WC()->query->get_tax_query();
		if ( $category ) {
			$tax_query[] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => absint( $category ) );
		}
		$args = array(
			'post_type' => 'product', 'post_status' => 'publish',
			'post__in' => array_merge( array( 0 ), wc_get_product_ids_on_sale() ),
			'posts_per_page' => self::PER_PAGE,
			'paged' => max( 1, absint( self::param( 'offers_page', 1 ) ) ),
			'tax_query' => $tax_query, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ),
		);
		if ( self::param( 'in_stock' ) === '1' ) {
			$args['meta_query'][] = array( 'key' => '_stock_status', 'value' => 'outofstock', 'compare' => '!=' );
		}
		$sorts = array( 'cheapest' => array( '_price', 'ASC' ), 'expensive' => array( '_price', 'DESC' ), 'popular' => array( 'total_sales', 'DESC' ) );
		$sort = self::param( 'sort', 'newest' );
		if ( isset( $sorts[ $sort ] ) ) {
			$args['meta_key'] = $sorts[ $sort ][0];
			$args['orderby'] = array( 'meta_value_num' => $sorts[ $sort ][1], 'ID' => 'DESC' );
		}
		return new WP_Query( $args );
	}

	public static function top_categories() {
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'exclude' => array( (int) get_option( 'default_product_cat' ) ) ) );
		return is_wp_error( $cats ) ? array() : $cats;
	}

	/**
	 * Sale IDs honouring the current sort / stock / category filters, unpaged.
	 *
	 * The page renders every offer in titled rows rather than a pager, so this
	 * deliberately ignores `offers_page`: the toolbar filters still apply.
	 */
	public static function filtered_sale_ids() {
		$args = array(
			'post_type' => 'product', 'post_status' => 'publish',
			'post__in' => array_merge( array( 0 ), wc_get_product_ids_on_sale() ),
			'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => false,
			'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ),
		);
		$tax_query = WC()->query->get_tax_query();
		$category = absint( self::param( 'offer_cat', 0 ) );
		if ( $category ) {
			$tax_query[] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $category );
		}
		$args['tax_query'] = $tax_query;
		if ( self::param( 'in_stock' ) === '1' ) {
			$args['meta_query'][] = array( 'key' => '_stock_status', 'value' => 'outofstock', 'compare' => '!=' );
		}
		$sorts = array( 'cheapest' => array( '_price', 'ASC' ), 'expensive' => array( '_price', 'DESC' ), 'popular' => array( 'total_sales', 'DESC' ) );
		$sort = self::param( 'sort', 'newest' );
		if ( isset( $sorts[ $sort ] ) ) {
			$args['meta_key'] = $sorts[ $sort ][0];
			$args['orderby'] = array( 'meta_value_num' => $sorts[ $sort ][1], 'ID' => 'DESC' );
		}
		return get_posts( $args );
	}

	/** Percentage off, or 0 when the product is not discounted. */
	public static function discount_of( $product ) {
		$regular = (float) $product->get_regular_price();
		if ( $regular <= 0 ) {
			return 0;
		}
		return (int) round( ( $regular - (float) $product->get_price() ) / $regular * 100 );
	}

	/**
	 * Titled rows of sale products, Digikala's /incredible-offers/ shape.
	 *
	 * Grouped by brand, not category: every discounted product in this catalog
	 * sits in a single product_cat, so per-category rows would be one very long
	 * row beside several near-empty ones. `pa_brand` is the only axis with
	 * spread. A brand needs MIN_BRAND to earn its own row; the rest fall into a
	 * trailing catch-all so no discounted product is ever hidden.
	 *
	 * Products are claimed once — a product in two brands appears in the first
	 * row that wanted it — so a row set never double-renders one card.
	 */
	public static function carousel_rows() {
		$ids = array_map( 'absint', self::filtered_sale_ids() );
		if ( ! $ids ) {
			return array();
		}

		$by_discount = array();
		$ending = array();
		$visible = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			$visible[] = $id;
			$percent = self::discount_of( $product );
			$by_discount[] = array( 'id' => $id, 'percent' => $percent );
			$end = self::sale_end( $product );
			if ( $end ) {
				$ending[] = array( 'id' => $id, 'end' => $end, 'percent' => $percent );
			}
		}
		usort( $by_discount, function( $a, $b ) { return $b['percent'] <=> $a['percent'] ?: $a['id'] <=> $b['id']; } );
		usort( $ending, function( $a, $b ) { return $a['end'] <=> $b['end'] ?: $b['percent'] <=> $a['percent']; } );

		$claimed = array();
		$take = function( $candidates, $limit ) use ( &$claimed ) {
			$picked = array();
			foreach ( $candidates as $candidate ) {
				if ( count( $picked ) >= $limit ) {
					break;
				}
				$id = (int) $candidate['id'];
				if ( isset( $claimed[ $id ] ) ) {
					continue;
				}
				$claimed[ $id ] = true;
				$picked[] = $id;
			}
			return $picked;
		};

		$rows = array();

		// Brand rows claim first. The two hero rows are drawn from the same
		// discounted pool, and if they went first they would strip the biggest
		// brands down to a token row — هارلینگن keeps 7 discounted products but
		// only 1 survived the hero rows taking 6. Brands are the structure here;
		// the hero rows are the garnish, so garnish is served second.
		$brands = self::sale_brands( $visible, $claimed );
		foreach ( $brands as $brand ) {
			$row = $take( array_map( function( $id ) { return array( 'id' => $id ); }, $brand['ids'] ), count( $brand['ids'] ) );
			if ( $row ) {
				$rows[] = array( 'key' => 'brand-' . $brand['term_id'], 'title' => $brand['name'], 'href' => $brand['href'], 'ids' => $row, 'hero' => false );
			}
		}

		$hero = $take( $by_discount, self::HERO_COUNT );
		if ( $hero ) {
			$rows[] = array( 'key' => 'hero', 'title' => 'شگفت‌انگیز روز', 'href' => '', 'ids' => $hero, 'hero' => true );
		}

		// "Ending soon" is a deadline sort. With nothing scheduled it has no
		// members, so fall back to the next best discounts rather than dropping
		// the section Digikala always shows.
		$soon = $ending ? $take( $ending, self::HERO_COUNT ) : $take( $by_discount, self::HERO_COUNT );
		if ( $soon ) {
			$rows[] = array( 'key' => 'ending', 'title' => 'شگفت‌انگیزهای رو به اتمام', 'href' => '', 'ids' => $soon, 'hero' => false );
		}

		$rest = array();
		foreach ( $visible as $id ) {
			if ( ! isset( $claimed[ $id ] ) ) {
				$claimed[ $id ] = true;
				$rest[] = $id;
			}
		}
		if ( $rest ) {
			$rows[] = array( 'key' => 'more', 'title' => 'شگفت‌انگیزهای دیگر', 'href' => '', 'ids' => $rest, 'hero' => false );
		}

		return $rows;
	}

	/**
	 * Brands that earn a row, richest first.
	 *
	 * $sale_ids is the caller's already-sorted, already-filtered list, and brand
	 * membership is read out of it rather than re-queried per brand. A per-brand
	 * get_posts() carried its own 'orderby', which overrode the visitor's ?sort=
	 * everywhere: every row came out newest-first whatever they asked for.
	 *
	 * The MIN_BRAND threshold and the row ranking use the brand's TOTAL on-sale
	 * depth, not what the current filter left behind. Otherwise a strong brand gets
	 * narrowed away and demoted — هارلینگن has 7 discounted products but kept
	 * only 1, because the two hero rows had already taken 6 of them.
	 *
	 * @param array $sale_ids Sale product ids, in the order they should render.
	 * @param array $exclude  id => true map of products already placed in an earlier row.
	 * @return array List of arrays with term_id, name, href, ids and total.
	 */
	public static function sale_brands( array $sale_ids, $exclude = array() ) {
		$sale_ids = array_map( 'absint', $sale_ids );
		$terms    = get_terms( array( 'taxonomy' => 'pa_brand', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || ! $sale_ids ) {
			return array();
		}

		$members = array();
		$depth   = array();
		foreach ( array( 0 => $sale_ids, 1 => wc_get_product_ids_on_sale() ) as $unfiltered => $set ) {
			$set = array_map( 'absint', $set );
			if ( ! $set ) {
				continue;
			}
			$rank = array_flip( $set );
			$pairs = wp_get_object_terms( $set, 'pa_brand', array( 'fields' => 'all_with_object_id' ) );
			if ( is_wp_error( $pairs ) ) {
				continue;
			}
			foreach ( $pairs as $pair ) {
				$term_id = (int) $pair->term_id;
				$id      = (int) $pair->object_id;
				if ( $unfiltered ) {
					$depth[ $term_id ] = isset( $depth[ $term_id ] ) ? $depth[ $term_id ] + 1 : 1;
				} elseif ( ! isset( $exclude[ $id ] ) ) {
					$members[ $term_id ][ $id ] = $rank[ $id ];
				}
			}
		}

		$out = array();
		foreach ( $terms as $term ) {
			$term_id = (int) $term->term_id;
			$total   = isset( $depth[ $term_id ] ) ? $depth[ $term_id ] : 0;
			if ( empty( $members[ $term_id ] ) || $total < self::MIN_BRAND ) {
				continue;
			}
			asort( $members[ $term_id ] );
			$out[] = array(
				'term_id' => $term_id,
				'name'    => $term->name,
				'href'    => add_query_arg( 'dk_brands[]', $term_id, wc_get_page_permalink( 'shop' ) ),
				'ids'     => array_keys( $members[ $term_id ] ),
				'total'   => $total,
			);
		}
		usort( $out, function( $a, $b ) { return $b['total'] <=> $a['total'] ?: strcmp( $a['name'], $b['name'] ); } );
		return $out;
	}

	public static function sale_end( $product ) {
		$date = $product->get_date_on_sale_to();
		return $product->is_on_sale() && $date ? $date->getTimestamp() : null;
	}

	public static function stock_left( $product ) {
		return $product->is_in_stock() && $product->managing_stock() ? max( 0, (int) $product->get_stock_quantity() ) : null;
	}
}

function is_incredible_offers() {
	return is_page( 'incredible-offers' );
}
