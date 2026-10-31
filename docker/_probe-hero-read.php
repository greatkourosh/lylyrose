<?php
/**
 * READ-ONLY: identify live attachment 1937 and every hero-ish attachment, plus
 * whether the clean files just uploaded have attachment rows yet.
 */
@ini_set( 'display_errors', '0' );
require_once __DIR__ . '/wp-load.php';

try {
	global $wpdb;

	foreach ( array( 1937 ) as $id ) {
		$p = get_post( $id );
		echo "== post $id ==\n";
		if ( ! $p ) { echo "  NO SUCH POST\n"; continue; }
		printf(
			"  type=%s status=%s title=%s\n  guid=%s\n",
			$p->post_type, $p->post_status, $p->post_title, $p->guid
		);
		echo '  _wp_attached_file=' . var_export( get_post_meta( $id, '_wp_attached_file', true ), true ) . "\n";
		echo '  full url=' . wp_get_attachment_image_url( $id, 'full' ) . "\n";
		echo '  full path exists=' . var_export( file_exists( get_attached_file( $id ) ), true ) . "\n";
	}

	echo "\n== all attachments with 'hero' in title ==\n";
	$rows = $wpdb->get_results(
		"SELECT ID, post_title, post_status FROM {$wpdb->posts}
		 WHERE post_type='attachment' AND post_title LIKE '%hero%' ORDER BY ID"
	);
	foreach ( $rows as $r ) {
		printf(
			"  #%-6d %-28s %-8s file=%s\n",
			$r->ID, $r->post_title, $r->post_status,
			get_post_meta( $r->ID, '_wp_attached_file', true )
		);
	}
	echo '  (' . count( $rows ) . " rows)\n";

	echo "\n== does a clean attachment exist? ==\n";
	$clean = $wpdb->get_results(
		"SELECT ID, post_title FROM {$wpdb->posts}
		 WHERE post_type='attachment' AND post_title LIKE '%clean%'"
	);
	foreach ( $clean as $r ) {
		printf( "  #%-6d %s\n", $r->ID, $r->post_title );
	}
	echo '  (' . count( $clean ) . " rows)\n";

	// The files were uploaded by FTP, so WP may have no row for them at all.
	foreach ( array( 'hero-banner-clean.webp', 'hero-banner-clean.avif' ) as $f ) {
		$path = WP_CONTENT_DIR . '/uploads/2026/10/' . $f;
		printf( "\n  on disk %-26s %s (%s bytes)\n", $f, file_exists( $path ) ? 'YES' : 'NO',
			file_exists( $path ) ? filesize( $path ) : '-' );
		$att = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value=%s LIMIT 1",
			'2026/10/' . $f
		) );
		echo '  attachment row: ' . ( $att ? "#$att" : 'NONE' ) . "\n";
	}

	echo "\n== uploads dir writable by php? ==\n";
	$d = WP_CONTENT_DIR . '/uploads/2026/10';
	echo '  is_writable=' . var_export( is_writable( $d ), true ) . "\n";
} catch ( Throwable $e ) {
	echo 'caught: ' . $e->getMessage() . "\n";
}
