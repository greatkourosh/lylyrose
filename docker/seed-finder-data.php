<?php
/**
 * Seed the Perfume Finder's axes for the fragrance catalogue.
 *
 * The finder scores products on six taxonomies and a note pyramid. On this
 * catalogue those were almost empty — 0 of 165 products were complete on all six
 * axes — so the quiz answered "nothing matches" for nearly every shopper.
 *
 * The data comes from docker/finder-profiles.json, which is authored per real
 * perfume (fourteen products here are Dior Sauvage alone; the model is the
 * fragrance, not the house name the product is sold under). Only the 110
 * products in the عطر و ادکلن category are seeded: the finder queries every
 * product with no category filter, so a body lotion carrying a fragrance family
 * would rank in perfume recommendations.
 *
 * Every term is checked against ASC_Perfume_Finder::vocabularies() and
 * longevity_levels() before anything is written. A term the quiz cannot answer
 * with is not a term it can score on, so a typo here would silently shrink the
 * catalogue instead of failing.
 *
 * Usage:  docker cp docker/seed-finder-data.php lylyrose-wp:/tmp/seed-finder.php
 *         docker cp docker/finder-profiles.json lylyrose-wp:/tmp/finder-profiles.json
 *         docker exec -u www-data lylyrose-wp php /tmp/seed-finder.php [dry-run]
 *
 * The profile path is relative to this file so the same script runs from the
 * docroot on a live host, where nothing exists under /tmp and wp-load.php is a
 * sibling rather than at the container path.
 */
if ( ! file_exists( '/var/www/html/wp-load.php' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
	require_once __DIR__ . '/wp-load.php';
} else {
	define( 'ABSPATH', '/var/www/html/' );
	require '/var/www/html/wp-load.php';
}

$dry_run = PHP_SAPI === 'cli'
	? count( preg_grep( '/^-*dry-run$/', $_SERVER['argv'] ?? array() ) ) > 0
	: isset( $_GET['dry-run'] );
$profiles = json_decode( file_get_contents( __DIR__ . '/finder-profiles.json' ), true );
if ( ! is_array( $profiles ) ) {
	fwrite( STDERR, "cannot read /tmp/finder-profiles.json\n" );
	exit( 2 );
}

$vocab    = ASC_Perfume_Finder::vocabularies();
$levels   = ASC_Perfume_Finder::longevity_levels();

// finder-profiles.json key => taxonomy slug.
const AXES = array(
	'family'       => 'pa_fragrance_family',
	'occasion'     => 'pa_occasion',
	'season'       => 'pa_season',
	'personality'  => 'pa_personality',
	'gender'       => 'pa_gender',
);
const LONGEVITY_TAXONOMY = 'pa_longevity';

/**
 * Set terms on a product, creating taxonomy terms that do not exist yet.
 * wp_set_object_terms() silently drops a name it cannot resolve to a term, so
 * terms are created here rather than assumed.
 */
function lr_set_terms( $product_id, $taxonomy, $names ) {
	$ids = array();
	foreach ( $names as $name ) {
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( ! $term ) {
			$created = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $created ) ) {
				fwrite( STDERR, "term \"{$name}\" in {$taxonomy}: " . $created->get_error_message() . "\n" );
				return false;
			}
			$term = get_term( $created['term_id'], $taxonomy );
		}
		$ids[] = (int) $term->term_id;
	}
	wp_set_object_terms( $product_id, $ids, $taxonomy, false );
	return true;
}

$errors = array();
$report = array( 'products' => 0, 'axes' => 0, 'notes' => 0 );

foreach ( $profiles as $sku => $p ) {
	$product = wc_get_product_id_by_sku( $sku );
	if ( ! $product ) {
		$errors[] = "no product with SKU {$sku}";
		continue;
	}
	$report['products']++;

	// --- vocabulary gate -------------------------------------------------
	$bad = array();
	foreach ( AXES as $key => $taxonomy ) {
		foreach ( (array) ( $p[ $key ] ?? array() ) as $name ) {
			if ( ! in_array( $name, $vocab[ $taxonomy ], true ) ) {
				$bad[] = "{$sku}: \"{$name}\" is not a {$taxonomy} term";
			}
		}
	}
	$longevity = $p['longevity'] ?? '';
	if ( ! isset( $levels[ $longevity ] ) ) {
		$bad[] = "{$sku}: \"{$longevity}\" is not a longevity level";
	}
	if ( $bad ) {
		$errors = array_merge( $errors, $bad );
		continue; // write nothing for a product that failed validation
	}

	if ( $dry_run ) {
		foreach ( array_keys( AXES ) as $key ) {
			if ( ! empty( $p[ $key ] ) ) {
				$report['axes']++;
			}
		}
		if ( ! empty( $p['notes'] ) ) {
			$report['notes']++;
		}
		continue;
	}

	foreach ( AXES as $key => $taxonomy ) {
		if ( ! lr_set_terms( $product, $taxonomy, (array) ( $p[ $key ] ?? array() ) ) ) {
			$errors[] = "{$sku}: could not set {$taxonomy}";
		}
	}
	// Longevity is scored with >= across an ordered scale, so it stores exactly
	// one level, not a list.
	lr_set_terms( $product, LONGEVITY_TAXONOMY, array( $longevity ) );

	foreach ( array( 'top', 'heart', 'base' ) as $tier ) {
		$value = $p['notes'][ $tier ] ?? '';
		if ( '' !== $value && null !== $value ) {
			update_post_meta( $product, '_asc_notes_' . $tier, $value );
			$report['notes']++;
		}
	}
	$report['axes'] += count( AXES );
}

printf(
	"%s\n  products: %d\n  axes set: %d\n  note tiers written: %d\n",
	$dry_run ? 'DRY RUN — nothing written' : 'seeded',
	$report['products'],
	$report['axes'],
	$report['notes']
);

if ( $errors ) {
	echo "errors (nothing was written for these):\n";
	foreach ( array_slice( $errors, 0, 20 ) as $e ) {
		echo "  {$e}\n";
	}
	echo '  ... ' . count( $errors ) . " total\n";
	exit( 1 );
}

// Verify from the database rather than trusting the loop above: count products
// that carry every axis, the way the finder itself decides a product is rankable.
$complete = 0;
foreach ( wc_get_products( array( 'limit' => -1, 'return' => 'objects' ) ) as $product ) {
	if ( $product->get_sku() && str_starts_with( $product->get_sku(), 'GC-' ) ) {
		continue;
	}
	if ( ! ASC_Perfume_Finder::profile( $product ) ) {
		continue;
	}
	$complete++;
}

echo "products the finder can now rank: {$complete}\n";
exit( $complete ? 0 : 1 );