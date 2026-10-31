<?php
/**
 * One-shot: purge the page caches after a deploy.
 * Upload, request once, delete.
 *
 * Live runs BOTH LiteSpeed and WP Super Cache, and neither purges the other —
 * a stale LiteSpeed hit is why a correct deploy can look unshipped on the plain
 * URL. Purge both. Prints one line per cache found.
 */
@ini_set( 'display_errors', '0' );
try {
	define( 'WP_USE_THEMES', false );
	foreach ( array( __DIR__ . '/wp-load.php', '/var/www/html/wp-load.php' ) as $c ) {
		if ( is_readable( $c ) ) {
			require_once $c;
			break;
		}
	}
	if ( ! defined( 'ABSPATH' ) ) {
		echo "NO_WP_LOAD\n";
		return;
	}
	if ( has_action( 'litespeed_purge_all' ) ) {
		do_action( 'litespeed_purge_all' );
		echo "LITESPEED_PURGED\n";
	} else {
		echo "NO_LS\n";
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
		echo "WPSUPERCACHE_PURGED\n";
	} else {
		echo "NO_WPSC\n";
	}
} catch ( Throwable $e ) {
	echo 'ERR ' . $e->getMessage() . "\n";
}
