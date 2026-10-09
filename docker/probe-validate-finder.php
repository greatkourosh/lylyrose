<?php
/**
 * Read-only: could the local finder profiles be seeded on THIS host?
 *
 * Runs the seeder's own checks and nothing else — no vocabulary term, no axis and
 * no note is written. Answers three questions production has to be asked before
 * a seed is proposed there: do the SKUs resolve, do the terms pass the quiz's
 * vocabulary gate on this install, and how many products would become rankable.
 */
if ( ! file_exists( '/var/www/html/wp-load.php' ) ) {
	require_once __DIR__ . '/wp-load.php';
} else {
	require_once '/var/www/html/wp-load.php';
}

// The data file's name is randomised by the uploader, so find it rather than
// assume: the entry script cannot know the tag it was stored under.
$data = glob( __DIR__ . '/_*_d_*.json' );
if ( ! $data ) {
	echo "cannot locate the uploaded profile file\n";
	exit( 2 );
}
$profiles = json_decode( file_get_contents( $data[0] ), true );
if ( ! is_array( $profiles ) ) {
	echo "cannot read the profile file\n";
	exit( 2 );
}

$vocab  = ASC_Perfume_Finder::vocabularies();
$levels = ASC_Perfume_Finder::longevity_levels();

$axes = array(
	'family'      => 'pa_fragrance_family',
	'occasion'    => 'pa_occasion',
	'season'      => 'pa_season',
	'personality' => 'pa_personality',
	'gender'      => 'pa_gender',
);

$missing_sku = array();
$bad_term    = array();
$would_rank  = 0;

foreach ( $profiles as $sku => $p ) {
	$id = wc_get_product_id_by_sku( $sku );
	if ( ! $id ) {
		$missing_sku[] = $sku;
		continue;
	}
	$bad = false;
	foreach ( $axes as $key => $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			$bad_term[] = "{$sku}: taxonomy {$taxonomy} does not exist here";
			$bad = true;
			continue;
		}
		foreach ( (array) ( $p[ $key ] ?? array() ) as $name ) {
			if ( ! in_array( $name, $vocab[ $taxonomy ], true ) ) {
				$bad_term[] = "{$sku}: \"{$name}\" is not a {$taxonomy} term";
				$bad = true;
			}
		}
	}
	$longevity = $p['longevity'] ?? '';
	if ( ! isset( $levels[ $longevity ] ) ) {
		$bad_term[] = "{$sku}: \"{$longevity}\" is not a longevity level";
		$bad = true;
	}
	if ( ! $bad ) {
		$would_rank++;
	}
}

printf(
	"profiles:      %d\nSKUs missing:  %d\nterm errors:   %d\nwould rank:    %d\n",
	count( $profiles ), count( $missing_sku ), count( $bad_term ), $would_rank
);

echo "\nclass present: " . ( class_exists( 'ASC_Perfume_Finder' ) ? 'yes' : 'NO' ) . "\n";

foreach ( array_slice( $missing_sku, 0, 10 ) as $sku ) {
	echo "  MISSING $sku\n";
}
foreach ( array_slice( $bad_term, 0, 10 ) as $e ) {
	echo "  $e\n";
}