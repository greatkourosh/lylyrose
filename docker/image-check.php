<?php
/**
 * Verifies uploads are stored as WebP (ASC_Images::output_format).
 * Sideloads a generated JPEG + PNG, asserts the stored file is image/webp,
 * then removes both attachments.
 */
define( 'ABSPATH', '/var/www/html/' );
$_SERVER['HTTP_HOST'] = 'localhost:8080';
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

echo 'FILTER_JPEG: ' . ( apply_filters( 'image_editor_output_format', array() )['image/jpeg'] ?? 'none' ) . "\n";
echo 'FILTER_PNG: ' . ( apply_filters( 'image_editor_output_format', array() )['image/png'] ?? 'none' ) . "\n";

function asc_probe( $ext, $mime ) {
	$src = '/tmp/asc-probe.' . $ext;
	$im  = imagecreatetruecolor( 900, 900 );
	imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 57, 78 ) );
	if ( 'png' === $ext ) {
		imagepng( $im, $src );
	} else {
		imagejpeg( $im, $src, 92 );
	}
	imagedestroy( $im );

	$id = media_handle_sideload(
		array(
			'tmp_name' => $src,
			'name'     => 'asc-probe.' . $ext,
			'type'     => $mime,
		),
		0
	);
	if ( is_wp_error( $id ) ) {
		echo strtoupper( $ext ) . '_RESULT: ERROR ' . $id->get_error_message() . "\n";
		return;
	}
	$meta = wp_get_attachment_metadata( $id );
	$path = get_attached_file( $id );
	echo strtoupper( $ext ) . '_STORED_MIME: ' . mime_content_type( $path ) . "\n";
	echo strtoupper( $ext ) . '_STORED_FILE: ' . basename( $meta['file'] ) . "\n";
	$sizes    = is_array( $meta['sizes'] ?? null ) ? $meta['sizes'] : array();
	$nonwebp  = 0;
	foreach ( $sizes as $s ) {
		if ( ( $s['mime-type'] ?? '' ) !== 'image/webp' ) {
			$nonwebp++;
		}
	}
	echo strtoupper( $ext ) . '_SIZES: ' . count( $sizes ) . ' generated, ' . $nonwebp . " non-webp\n";
	wp_delete_attachment( $id, true );
}

asc_probe( 'jpg', 'image/jpeg' );
asc_probe( 'png', 'image/png' );

// Existing library must already be fully WebP.
global $wpdb;
$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'" );
$nonwebp  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%' AND post_mime_type <> 'image/webp'" );
echo "LIBRARY_IMAGES: $total\n";
echo "LIBRARY_NON_WEBP: $nonwebp\n";
echo 'BIG_THRESHOLD: ' . apply_filters( 'big_image_size_threshold', 2560 ) . "\n";
