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
		add_action( 'init', array( __CLASS__, 'maybe_backfill' ), 99 );
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
	 * Record conversion in the debug log so deployments can be verified, and
	 * correct the attachment's stored mime type.
	 *
	 * image_editor_output_format changes the extension WP writes, but
	 * wp_insert_attachment() has already recorded the *original* type by then
	 * (it comes from the upload's real mime, not the output format). So a
	 * converted upload keeps a row saying image/jpeg or image/png while the file
	 * on disk is WebP. Anything that trusts the column -- the media library's
	 * own type filter, the admin list-table column, REST /wp/v2/media and
	 * attachment search -- then describes the file as a format it no longer is.
	 */
	public static function log_conversion( $metadata, $attachment_id ) {
		if ( ! empty( $metadata['file'] ) && substr( $metadata['file'], -5 ) === '.webp' ) {
			error_log( "ASC_Images: attachment {$attachment_id} stored as " . $metadata['file'] );
			self::correct_mime_type( $attachment_id );
		}
		return $metadata;
	}

	/**
	 * One-time repair of rows written before the type was corrected. Keyed on
	 * the plugin version so it re-runs if a future release changes the policy,
	 * the same idiom ASC_Product_Code::maybe_flush() uses.
	 */
	public static function maybe_backfill() {
		if ( get_option( 'asc_images_mime_version' ) === ASC_Images::BACKFILL_VERSION ) {
			return;
		}
		self::correct_all_mime_types();
		update_option( 'asc_images_mime_version', ASC_Images::BACKFILL_VERSION );
	}

	const BACKFILL_VERSION = '1';

	/**
	 * Only files that are genuinely WebP on disk get relabelled; the original
	 * is still kept alongside by WP, and it is a real PNG/JPEG, so this must
	 * not be trusted blindly from the path alone.
	 */
	public static function correct_mime_type( $attachment_id ) {
		if ( get_post_mime_type( $attachment_id ) === 'image/webp' ) {
			return;
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return;
		}
		$type = function_exists( 'mime_content_type' ) ? mime_content_type( $file ) : '';
		if ( 'image/webp' !== $type ) {
			return;
		}
		wp_update_post( array(
			'ID'             => $attachment_id,
			'post_mime_type' => 'image/webp',
		) );
	}

	/**
	 * Relabel every attachment whose file on disk is WebP but whose row is not.
	 * Bounded to the image library and safe to re-run: it is a no-op once every
	 * file agrees with its row.
	 */
	public static function correct_all_mime_types() {
		$ids = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image/jpeg, image/png',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'numberposts'    => -1,
		) );
		$fixed = 0;
		foreach ( $ids as $id ) {
			$before = get_post_mime_type( $id );
			self::correct_mime_type( $id );
			if ( get_post_mime_type( $id ) !== $before ) {
				$fixed++;
			}
		}
		if ( $fixed ) {
			error_log( "ASC_Images: relabelled {$fixed} attachment(s) to image/webp" );
		}
	}
}
