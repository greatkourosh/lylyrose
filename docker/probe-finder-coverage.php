<?php
/**
 * Read-only: Perfume Finder coverage on whichever DB this probe is fetched from.
 *
 * The docs record 110/165 rankable locally and say "both live DBs need their own
 * pass". This answers that for the host it is uploaded to: how many published
 * products profile() would accept, and which ones it would not.
 */
// The docroot probe runs on the host, where the container path does not exist;
// wp-load.php sits in the docroot beside the probe itself.
if ( ! file_exists( '/var/www/html/wp-load.php' ) ) {
	require_once __DIR__ . '/wp-load.php';
} else {
	require_once '/var/www/html/wp-load.php';
}

$published = wc_get_products( array(
	'limit'    => -1,
	'status'   => 'publish',
	'orderby'  => 'ID',
	'order'    => 'ASC',
	'paginate' => false,
) );

$total = count( $published );
$rankable = array();
$not = array();

foreach ( $published as $p ) {
	$prof = ASC_Perfume_Finder::profile( $p );
	if ( null === $prof ) {
		$not[] = $p->get_id() . ' ' . $p->get_sku() . ' | ' . $p->get_name();
	} else {
		$rankable[] = $p->get_id() . ' ' . $p->get_sku();
	}
}

echo "PUBLISHED: $total\n";
echo "RANKABLE:  " . count( $rankable ) . "\n";
echo "NOT:       " . count( $not ) . "\n\n";

echo "== sample rankable ==\n";
foreach ( array_slice( $rankable, 0, 3 ) as $line ) {
	echo "  $line\n";
}

echo "\n== not rankable ==\n";
foreach ( $not as $line ) {
	echo "  $line\n";
}