<?php
/**
 * Encode an .avif beside every .webp in the uploads folder that lacks one.
 *
 * Why this exists: ASC_Avif::generate() fires on wp_generate_attachment_metadata,
 * so it only covers uploads that went through the media library. Files that land
 * in wp-content/uploads some other way -- a Digikala sync writing straight to
 * the folder, an image pushed over FTP with deploy-targeted.py -- never get an
 * AVIF sibling, and those pages serve the heavier WebP to browsers that asked
 * for AVIF.
 *
 * 8 of 2,593 uploads on lylyrose.ir were in that state on 2026-10-20 (fixed),
 * and 7 of 2,804 locally (the WooCommerce placeholders). This script is the
 * repeatable version, for the next bulk upload.
 *
 * Usage, locally:
 *   docker compose exec -T -u root wordpress php /var/www/html/docker/avif-backfill.php
 *
 * Do NOT put this in the docroot: an unauthenticated URL that burns CPU is a
 * denial-of-service lever. For live, run it as a WP-CLI-less one-off instead --
 * copy it into the docroot under a random name, request it once, delete it
 * immediately, and confirm the 404 (see docs/CONTINUATION.md, "probe").
 *
 * Reads and writes files only. It does not touch the database.
 */

// Run inside WordPress, whether or not a front controller got us here.
if ( ! defined( 'ABSPATH' ) ) {
	// NOT $wp: WordPress keeps the WP object in a global named $wp, and a
	// variable of that name at file scope is what this require leaks into
	// $GLOBALS. It then hits register_taxonomy() as a string and fatals with
	// "add_query_var() on string".
	$wp_load = dirname( __DIR__ ) . '/wp-load.php';
	if ( ! file_exists( $wp_load ) ) {
		fwrite( STDERR, "cannot find wp-load.php next to this file\n" );
		exit( 1 );
	}
	// wp-load.php in CLI mode needs a host and a request line, or WordPress
	// builds its rewrite rules from an empty site URL and fatals with
	// "add_query_var() on string". The container's config derives WP_HOME and
	// WP_SITEURL from HTTP_HOST itself, so setting the server variable is
	// enough -- do NOT define them here, or the config's own define() collides
	// with this one and the URL ends up malformed.
	if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
		$_SERVER['HTTP_HOST']      = 'localhost';
		$_SERVER['REQUEST_URI']    = '/';
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}
	// WordPress resolves some paths against the current directory.
	chdir( dirname( $wp_load ) );
	require_once $wp_load;
}

if ( ! class_exists( 'ASC_Avif' ) ) {
	fwrite( STDERR, "ASC_Avif is not loaded; the lylyrose-core plugin must be active\n" );
	exit( 1 );
}

$uploads = wp_upload_dir();
$root    = rtrim( $uploads['basedir'], '/' );
if ( ! is_dir( $root ) ) {
	fwrite( STDERR, "uploads dir not found: $root\n" );
	exit( 1 );
}

$webps = array();
$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $file ) {
	if ( $file->isFile() && 'webp' === strtolower( $file->getExtension() ) ) {
		$webps[] = $file->getPathname();
	}
}
sort( $webps );

$made = 0;
$skip = 0;
$fail = array();
foreach ( $webps as $webp ) {
	$avif = substr( $webp, 0, -5 ) . '.avif';
	if ( file_exists( $avif ) ) {
		$skip++;
		continue;
	}
	if ( ASC_Avif::encode( $webp ) && file_exists( $avif ) ) {
		$made++;
		printf( "  created %s (%d B)\n", basename( $avif ), filesize( $avif ) );
	} else {
		$fail[] = basename( $webp );
	}
}

printf( "\n%d webp files, %d already had an avif, %d encoded, %d failed\n", count( $webps ), $skip, $made, count( $fail ) );
foreach ( $fail as $f ) {
	echo "  FAILED: $f\n";
}
exit( $fail ? 1 : 0 );
