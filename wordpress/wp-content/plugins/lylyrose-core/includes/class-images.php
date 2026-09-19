<?php
/**
 * Image delivery optimization.
 *
 * The media library is already 100% WebP (Digikala import), so this class only
 * has to keep it that way: future uploads (manual JPG/PNG, vendor photos) are
 * converted to WebP by WP core's image editor, avoiding any need for a
 * converter plugin, .htaccess rewrite rules, or extra server load.
 *
 * WP converts on upload and keeps the original; attachment URLs, srcset and
 * all generated sizes then point at the .webp variant.
 */
class ASC_Images {

	public static function init() {
		add_filter( 'image_editor_output_format', array( __CLASS__, 'output_format' ) );
		add_filter( 'big_image_size_threshold', array( __CLASS__, 'big_image_threshold' ) );
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'log_conversion' ), 10, 2 );
	}

	/**
	 * Convert every raster upload to WebP via the WP image editor.
	 */
	public static function output_format( $formats ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';

		return $formats;
	}

	/**
	 * Cap large originals at 2560px on the longest side.
	 */
	public static function big_image_threshold( $threshold ) {
		return 2560;
	}

	/**
	 * Record conversion in the debug log so deployments can be verified.
	 */
	public static function log_conversion( $metadata, $attachment_id ) {
		if ( ! empty( $metadata['file'] ) && substr( $metadata['file'], -5 ) === '.webp' ) {
			error_log( "ASC_Images: attachment {$attachment_id} stored as " . $metadata['file'] );
		}
		return $metadata;
	}
}
