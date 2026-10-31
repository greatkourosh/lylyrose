<?php
/** READ-ONLY: attachment 1937's stored metadata, so overwriting its file keeps it truthful. */
@ini_set( 'display_errors', '0' );
require_once __DIR__ . '/wp-load.php';
try {
	$id = 1937;
	$m  = get_post_meta( $id, '_wp_attachment_metadata', true );
	echo "metadata:\n";
	if ( is_array( $m ) ) {
		echo '  width=' . ( $m['width'] ?? '?' ) . ' height=' . ( $m['height'] ?? '?' ) . "\n";
		echo '  file=' . ( $m['file'] ?? '?' ) . "\n";
		$sizes = $m['sizes'] ?? array();
		echo '  sizes: ' . ( $sizes ? implode( ', ', array_keys( $sizes ) ) : 'NONE' ) . "\n";
	} else {
		echo '  not an array: ' . var_export( $m, true ) . "\n";
	}
	$dir = dirname( get_attached_file( $id ) );
	echo "\nfiles present in the banner's upload dir matching hero-banner:\n";
	foreach ( glob( $dir . '/hero-banner*' ) as $f ) {
		printf( "  %-46s %8d\n", basename( $f ), filesize( $f ) );
	}
	// Dimensions of the files just uploaded, read from the bytes themselves.
	foreach ( array( 'hero-banner.webp', 'hero-banner-clean.webp' ) as $f ) {
		$p = $dir . '/' . $f;
		if ( ! file_exists( $p ) ) { continue; }
		$info = @getimagesize( $p );
		printf( "\n  %-26s getimagesize=%s\n", $f, $info ? "{$info[0]}x{$info[1]} {$info['mime']}" : 'failed' );
	}
} catch ( Throwable $e ) {
	echo 'caught: ' . $e->getMessage() . "\n";
}
