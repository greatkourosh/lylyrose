<?php
/**
 * Read-only: every published SKU on this host, one per line, so the local
 * finder-profiles.json can be diffed against it without writing anything.
 */
if ( ! file_exists( '/var/www/html/wp-load.php' ) ) {
	require_once __DIR__ . '/wp-load.php';
} else {
	require_once '/var/www/html/wp-load.php';
}

foreach ( wc_get_products( array(
	'limit'    => -1,
	'status'   => 'publish',
	'orderby'  => 'ID',
	'order'    => 'ASC',
	'paginate' => false,
) ) as $p ) {
	echo $p->get_sku() . "\t" . $p->get_id() . "\n";
}