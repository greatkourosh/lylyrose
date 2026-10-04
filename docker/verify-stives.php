<?php
/**
 * Read-only verification of the St. Ives import.
 * Run via docker exec lylyrose-wp php /tmp/verify-stives.php
 */
define( 'WP_USE_THEMES', false );
require_once '/var/www/html/wp-load.php';
class_exists( 'WooCommerce' );

$spec  = json_decode( file_get_contents( '/tmp/import-catalogue.json' ), true );
$skus  = wp_list_pluck( $spec['products'], 'sku' );

$ids = get_posts( array(
	'post_type'   => 'product',
	'posts_per_page' => -1,
	'fields'      => 'ids',
	'post_status' => 'publish',
) );

$by_sku = array();
foreach ( $ids as $id ) {
	$s = get_post_meta( $id, '_sku', true );
	if ( $s ) {
		$by_sku[ $s ] = $id;
	}
}

$missing = array();
$no_img  = array();
$no_price = array();
$bad_cat = array();
$webp = $notwebp = 0;

foreach ( $skus as $sku ) {
	if ( ! isset( $by_sku[ $sku ] ) ) {
		$missing[] = $sku;
		continue;
	}
	$p = wc_get_product( $by_sku[ $sku ] );
	if ( ! $p->get_image_id() ) {
		$no_img[] = $sku;
	} else {
		$file = get_attached_file( $p->get_image_id() );
		if ( $file && file_exists( $file ) ) {
			mime_content_type( $file ) === 'image/webp' ? $webp++ : $notwebp++;
		}
	}
	if ( '' === $p->get_price() || '0' === $p->get_price() ) {
		$no_price[] = $sku;
	}
	$cats = wp_get_object_terms( $by_sku[ $sku ], 'product_cat', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $cats ) || ! $cats ) {
		$bad_cat[] = $sku;
	}
}

echo "SKUs in catalogue: " . count( $skus ) . "\n";
echo "missing products: " . ( $missing ? implode( ', ', $missing ) : 'none' ) . "\n";
echo "without image: " . ( $no_img ? implode( ', ', $no_img ) : 'none' ) . "\n";
echo "without price: " . ( $no_price ? implode( ', ', $no_price ) : 'none' ) . "\n";
echo "without category: " . ( $bad_cat ? implode( ', ', $bad_cat ) : 'none' ) . "\n";
echo "packshots webp {$webp} / non-webp {$notwebp}\n";
echo "total published products: " . count( $ids ) . "\n";

echo "\nnew categories:\n";
foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) ) as $t ) {
	if ( ! $t->parent ) {
		continue;
	}
	printf( "  %-22s %-24s count=%-4d parent=%d\n", $t->slug, $t->name, $t->count, $t->parent );
}

echo "\nheader nav (what a visitor sees, first 9):\n";
$nav = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'exclude'    => array( get_option( 'default_product_cat' ) ),
) );
foreach ( array_slice( $nav, 0, 9 ) as $t ) {
	echo "  {$t->name} ({$t->count})\n";
}
echo "  clipped: " . implode( ', ', array_map( function ( $t ) {
	return $t->name;
}, array_slice( $nav, 9 ) ) ) . "\n";

echo "\nperfume finder safety — products carrying a fragrance axis:\n";
$scored = 0;
foreach ( $ids as $id ) {
	$prof = class_exists( 'ASC_Perfume_Finder' ) ? ASC_Perfume_Finder::profile( wc_get_product( $id ) ) : null;
	if ( null !== $prof ) {
		$scored++;
	}
}
echo "  rankable in finder: {$scored} (was 8 before this import)\n";