<?php
 defined( 'ABSPATH' ) || exit;

class ASC_Flash_Sales {
	const PER_PAGE = 24;

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
