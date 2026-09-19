<?php
/**
 * Reschedule WP Super Cache GC properly and verify.
 * Run: docker cp docker/gc-fix.php lylyrose-wp:/tmp/gc-fix.php
 *      docker exec lylyrose-wp php /tmp/gc-fix.php
 */
require '/var/www/html/wp-load.php';

echo "== Before ==\n";
$next = wp_next_scheduled( 'wp_cache_gc' );
echo 'wp_cache_gc next: ' . ( $next ? date( 'Y-m-d H:i:s', $next ) : 'NOT SCHEDULED' ) . "\n";

// Clear stale/incorrectly scheduled GC hooks and reschedule cleanly.
wp_clear_scheduled_hook( 'wp_cache_gc' );
wp_clear_scheduled_hook( 'wp_cache_gc_preload' );
wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );

wp_schedule_event( time() + 600, 'hourly', 'wp_cache_gc' );
wp_schedule_event( time() + 1800, 'daily', 'wp_cache_check_site_hook' );

echo "\n== After ==\n";
$next = wp_next_scheduled( 'wp_cache_gc' );
echo 'wp_cache_gc next: ' . ( $next ? date( 'Y-m-d H:i:s', $next ) : 'NOT SCHEDULED' ) . "\n";
$next = wp_next_scheduled( 'wp_cache_check_site_hook' );
echo 'check_site next: ' . ( $next ? date( 'Y-m-d H:i:s', $next ) : 'NOT SCHEDULED' ) . "\n";

// Run cron once now so any due tasks execute immediately.
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
    echo "\nRunning wp-cron now...\n";
    // spawn cron via loopback request like WP does
    wp_remote_post(
        site_url( 'wp-cron.php?doing_wp_cron' ),
        array( 'timeout' => 10, 'blocking' => false )
    );
    echo "cron spawned\n";
}
