<?php
/**
 * One-time seed: extract brands from product titles into the pa_brand
 * attribute taxonomy so the shop filter rail has real data.
 *
 * Usage: docker cp to container, then
 *   php /tmp/wp-cli.phar eval-file /tmp/seed-brands.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Words that can never be a brand name.
$stop = array_flip( array(
	'ادو', 'ادوپرفیوم', 'ادیوپرفیوم', 'پرفیوم', 'تویلت', 'عطر', 'جیبی', 'روغنی',
	'بادی', 'اسپلش', 'ست', 'هدیه', 'سمپل', 'مردانه', 'زنانه', 'یونیسکس',
	'مدل', 'حجم', 'رایحه', 'با', 'و', 'در', 'تلخ', 'گرم', 'خنک', 'معتدل',
	'تند', 'شیرین', 'میلی', 'لیتر', 'مجموعه', 'عددی', 'اسپرت', 'سایه', 'بازی',
	'شماره',
) );

/**
 * Walk backwards from the word right before "مدل", collecting up to
 * $max words that look like a brand name (skip pure digits/stopwords).
 */
function dk_brand_before_model( $words, $idx, $stop, $max = 3 ) {
	$collected = array();
	for ( $i = $idx - 1; $i >= 0 && count( $collected ) < $max; $i-- ) {
		$w = $words[ $i ];
		if ( ctype_digit( $w ) || isset( $stop[ $w ] ) ) {
			break;
		}
		array_unshift( $collected, $w );
	}
	return $collected;
}

/**
 * Brand = words after a category/gender marker, up to a stopword.
 */
function dk_brand_after_marker( $words, $start, $stop, $max = 1 ) {
	$collected = array();
	for ( $i = $start; $i < count( $words ) && count( $collected ) < $max; $i++ ) {
		$w = $words[ $i ];
		if ( ctype_digit( $w ) || isset( $stop[ $w ] ) ) {
			break;
		}
		$collected[] = $w;
	}
	return $collected;
}

// Ensure the pa_brand WooCommerce attribute exists (check table directly;
// wc_get_attribute_taxonomies() caches and can go stale mid-request).
global $wpdb;
$found = (bool) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
		'brand'
	)
);
if ( ! $found ) {
	$new = wc_create_attribute(
		array(
			'name'         => 'برند',
			'slug'         => 'brand',
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => 0,
		)
	);
	if ( is_wp_error( $new ) ) {
		echo "ERROR creating attribute: " . $new->get_error_message() . PHP_EOL;
		exit( 1 );
	}
	echo "created pa_brand attribute\n";
}
delete_transient( 'wc_attribute_taxonomies' );
register_taxonomy( 'pa_brand', array( 'product' ), array( 'hierarchical' => false, 'show_admin_column' => true ) );

$products = wc_get_products(
	array(
		'limit'  => -1,
		'status' => array( 'publish', 'draft', 'pending', 'private' ),
	)
);

$assigned = 0;
$skipped  = 0;
$names    = array();

foreach ( $products as $product ) {
	$title = $product->get_name();
	$words = preg_split( '/\s+/u', trim( $title ) );
	$brand = array();

	$model_idx = array_search( 'مدل', $words, true );
	if ( false !== $model_idx && $model_idx > 0 ) {
		$brand = dk_brand_before_model( $words, $model_idx, $stop );
	}

	if ( ! $brand ) {
		foreach ( $words as $i => $w ) {
			if ( in_array( $w, array( 'مردانه', 'زنانه', 'یونیسکس' ), true ) ) {
				$brand = dk_brand_after_marker( $words, $i + 1, $stop );
				break;
			}
		}
	}

	if ( ! $brand ) {
		$markers = array( 'ادو پرفیوم', 'ادو تویلت', 'ادوپرفیوم', 'ادیوپرفیوم', 'عطر', 'پرفیوم', 'بادی اسپلش', 'ست هدیه', 'سمپل' );
		foreach ( $markers as $marker ) {
			$pos = mb_strpos( $title, $marker );
			if ( false !== $pos ) {
				$after = trim( mb_substr( $title, $pos + mb_strlen( $marker ) ) );
				$parts = preg_split( '/\s+/u', $after );
				$brand = dk_brand_after_marker( $parts, 0, $stop );
				break;
			}
		}
	}

	$name = trim( implode( ' ', $brand ) );
	if ( '' === $name || isset( $stop[ $name ] ) || ctype_digit( str_replace( ' ', '', $name ) ) ) {
		++$skipped;
		continue;
	}

	$term = get_term_by( 'name', $name, 'pa_brand' );
	if ( ! $term ) {
		$new_term = wp_insert_term( $name, 'pa_brand' );
		if ( is_wp_error( $new_term ) ) {
			$term = get_term_by( 'name', $name, 'pa_brand' );
		} else {
			$term = get_term( $new_term['term_id'], 'pa_brand' );
		}
	}
	if ( ! $term ) {
		++$skipped;
		continue;
	}

	$names[ $name ] = ( $names[ $name ] ?? 0 ) + 1;
	wp_set_object_terms( $product->get_id(), array( (int) $term->term_id ), 'pa_brand', false );
	++$assigned;
}

echo "assigned: $assigned, skipped: $skipped\n";
arsort( $names );
foreach ( $names as $n => $c ) {
	echo "  $n: $c\n";
}
