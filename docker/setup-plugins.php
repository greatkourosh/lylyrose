<?php
/**
 * One-off plugin setup & verification script, run inside the lylyrose-wp container:
 *   docker cp docker/setup-plugins.php lylyrose-wp:/tmp/setup.php
 *   docker exec lylyrose-wp php /tmp/setup.php
 */
require '/var/www/html/wp-load.php';

echo "== Wordfence ==\n";
if ( class_exists( 'wfConfig' ) ) {
    echo 'loginSecurity: '; var_dump( wfConfig::get( 'loginSecurityEnabled' ) );
    echo 'coreScan: ';      var_dump( wfConfig::get( 'scansEnabled_core' ) );
    echo 'scheduledScans: ';var_dump( wfConfig::get( 'scheduledScansEnabled' ) );
}

echo "\n== TI Wishlist page assignment ==\n";
$page = get_option( 'tinvwl-page' );
echo 'wishlist page id: '; var_dump( $page['wishlist'] ?? null );

echo "\n== WP Super Cache files ==\n";
foreach ( array(
    '/var/www/html/wp-content/advanced-cache.php',
    '/var/www/html/wp-content/wp-cache-config.php',
) as $f ) {
    echo basename( $f ) . ': ';
    var_dump( file_exists( $f ) );
}
