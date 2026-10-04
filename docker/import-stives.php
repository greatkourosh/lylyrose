<?php
/**
 * Import the St. Ives catalogue as WooCommerce products.
 *
 * Run via docker exec, e.g.
 *   docker cp new_products/import-catalogue.json lylyrose-wp:/tmp/
 *   docker cp docker/import-stives.php lylyrose-wp:/tmp/
 *   docker exec lylyrose-wp php /tmp/import-stives.php /tmp/import-catalogue.json
 *
 * Idempotent: keyed on SKU, so re-running updates rather than duplicating.
 * That matters here — create_products.php seeded three copies of every product
 * once before it matched on SKU, and the duplicates reached both live stores.
 *
 * Body-care taxonomy terms are created if absent. The catalogue's perfume
 * taxonomy terms are deliberately NOT set: the Perfume Finder ranks anything
 * carrying a fragrance axis, and body care carries none, so leaving those
 * attributes off is what keeps the 51 products out of its results.
 */
define( 'WP_USE_THEMES', false );
require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$spec = json_decode( file_get_contents( $argv[1] ), true );
if ( ! $spec ) {
	echo "cannot read catalogue\n";
	exit( 1 );
}

$photo_dir = rtrim( $argv[2] ?? '/tmp/photos', '/' ) . '/';

function asc_term( $name, $slug, $taxonomy, $parent = 0 ) {
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing ) {
		return (int) $existing->term_id;
	}
	$args = array( 'slug' => $slug );
	if ( $parent ) {
		$args['parent'] = $parent;
	}
	$t = wp_insert_term( $name, $taxonomy, $args );
	if ( is_wp_error( $t ) ) {
		echo "  TERM FAILED {$taxonomy}/{$slug}: " . $t->get_error_message() . "\n";
		return 0;
	}
	return (int) $t['term_id'];
}

echo "currency: " . get_woocommerce_currency() . "  tax: " . ( wc_tax_enabled() ? 'on' : 'off' ) . "\n\n";

// Parent categories first, so children can point at them.
$parent_ids = array();
foreach ( $spec['parents'] as $slug => $label ) {
	$parent_ids[ $slug ] = asc_term( $label, $slug, 'product_cat' );
}
echo "parent categories: " . implode( ', ', array_keys( $parent_ids ) ) . "\n";

$cat_ids = array();
foreach ( $spec['categories'] as $slug => $meta ) {
	$cat_ids[ $slug ] = asc_term(
		$meta['label'], $slug, 'product_cat',
		$parent_ids[ $meta['parent'] ] ?? 0
	);
}
echo "leaf categories: " . count( $cat_ids ) . "\n\n";

// The brand, once.
$brand_id = asc_term( 'استیوز', 'st-ives', 'pa_brand' );
echo "brand term id: " . ( $brand_id ?: 'FAILED' ) . "\n\n";

$made = $updated = $noimg = 0;
foreach ( $spec['products'] as $p ) {
	$sku = $p['sku'];

	// Attach the packshot to the product, or find it again on a re-run.
	$att_id = 0;
	if ( ! empty( $p['packshot'] ) ) {
		$existing = wc_get_product_id_by_sku( $sku );
		if ( $existing ) {
			$have = get_post_thumbnail_id( $existing );
			if ( $have ) {
				$att_id = (int) $have;
			}
		}
		if ( ! $att_id ) {
			$src = $photo_dir . $p['packshot'];
			if ( ! is_readable( $src ) ) {
				echo "  MISSING FILE $sku {$p['packshot']}\n";
				$noimg++;
			} else {
				$tmp = wp_tempnam( $p['packshot'] );
				copy( $src, $tmp );
				$att_id = media_handle_sideload(
					array(
						'tmp_name' => $tmp,
						'name'     => $sku . '.jpg',
						'type'     => 'image/jpeg',
					),
					0
				);
				if ( is_wp_error( $att_id ) ) {
					echo "  SIDELOAD FAILED $sku: " . $att_id->get_error_message() . "\n";
					$att_id = 0;
					$noimg++;
				} else {
					$att_id = (int) $att_id;
				}
			}
		}
	} else {
		$noimg++;
	}

	$existing = wc_get_product_id_by_sku( $sku );
	$post_id  = wp_insert_post( array(
		'ID'           => $existing ?: 0,
		'post_title'   => $p['name_fa'],
		'post_content' => wpautop( $p['desc_fa'] ),
		'post_excerpt' => wp_trim_words( wp_strip_all_tags( $p['desc_fa'] ), 22, '…' ),
		'post_status'  => 'publish',
		'post_type'    => 'product',
	), true );

	if ( is_wp_error( $post_id ) ) {
		echo "  POST FAILED $sku: " . $post_id->get_error_message() . "\n";
		continue;
	}
	$existing ? $updated++ : $made++;

	wp_set_object_terms( $post_id, 'simple', 'product_type' );
	wp_set_object_terms( $post_id, array( $cat_ids[ $p['cat'] ] ), 'product_cat' );
	if ( $brand_id ) {
		wp_set_object_terms( $post_id, array( $brand_id ), 'pa_brand' );
	}

	// Price via the CRUD setters, not raw meta: Woo recomputes _price from
	// _regular_price/_sale_price, and writing the meta by hand leaves a stale
	// cached price that only clears on a manual save.
	$product = wc_get_product( $post_id );
	$product->set_name( $p['name_fa'] );
	$product->set_description( $p['desc_fa'] );
	$product->set_short_description( $p['desc_fa'] );
	$product->set_sku( $sku );
	$product->set_regular_price( (string) $p['price'] );
	$product->set_price( (string) $p['price'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 25 );
	$product->set_stock_status( 'instock' );
	$product->set_tax_status( 'taxable' );
	if ( $att_id ) {
		$product->set_image_id( $att_id );
	}
	$product->save();

	printf( "%-38s #%-5d %-8s %9s  img=%s\n",
		$sku, $post_id, $existing ? 'updated' : 'created',
		number_format( $p['price'] ), $att_id ?: 'NONE' );
}

echo "\ncreated {$made}, updated {$updated}, without photo {$noimg}\n";
echo "products now in catalogue: " . wp_count_posts( 'product' )->publish . "\n";