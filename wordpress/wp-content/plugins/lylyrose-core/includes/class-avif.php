<?php
/**
 * Serve AVIF to browsers that accept it, WebP to everyone else.
 *
 * The .avif sits next to each .webp. Image URLs are rewritten only when the
 * request's Accept header offers image/avif and the file exists on disk, so a
 * missing AVIF falls back to the WebP it was derived from. Responses carry
 * Vary: Accept so a shared cache never hands an AVIF URL to a browser without
 * AVIF support.
 *
 * @package Lylyrose_Core
 */

defined( 'ABSPATH' ) || exit;

class ASC_Avif {

	const QUALITY = 50;

	public static function init() {
		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'url' ) );
		add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'image_src' ) );
		add_filter( 'wp_calculate_image_srcset', array( __CLASS__, 'srcset' ) );
		add_action( 'send_headers', array( __CLASS__, 'vary' ) );
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'generate' ), 20 );
	}

	public static function accepts_avif() {
		return ! is_admin() && isset( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( $_SERVER['HTTP_ACCEPT'], 'image/avif' );
	}

	public static function url( $url ) {
		return self::accepts_avif() ? self::to_avif( $url ) : $url;
	}

	public static function image_src( $image ) {
		if ( $image && self::accepts_avif() ) {
			$image[0] = self::to_avif( $image[0] );
		}
		return $image;
	}

	public static function srcset( $sources ) {
		if ( ! self::accepts_avif() ) {
			return $sources;
		}
		foreach ( $sources as $w => $source ) {
			$sources[ $w ]['url'] = self::to_avif( $source['url'] );
		}
		return $sources;
	}

	public static function vary() {
		if ( ! is_admin() ) {
			header( 'Vary: Accept', false );
		}
	}

	/** Maps a WebP URL in the uploads folder to its AVIF sibling when that file exists. */
	public static function to_avif( $url ) {
		if ( ! is_string( $url ) || '.webp' !== strtolower( substr( $url, -5 ) ) ) {
			return $url;
		}
		$uploads = wp_upload_dir( null, false );
		if ( 0 !== strpos( $url, $uploads['baseurl'] ) ) {
			return $url;
		}
		$path = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
		$avif = substr( $path, 0, -5 ) . '.avif';
		return file_exists( $avif ) ? substr( $url, 0, -5 ) . '.avif' : $url;
	}

	/** Writes an AVIF beside every WebP WordPress just generated for this upload. */
	public static function generate( $metadata ) {
		if ( empty( $metadata['file'] ) || '.webp' !== substr( $metadata['file'], -5 ) ) {
			return $metadata;
		}
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . dirname( $metadata['file'] ) . '/';
		$files   = array( basename( $metadata['file'] ) );
		foreach ( isset( $metadata['sizes'] ) ? $metadata['sizes'] : array() as $size ) {
			$files[] = $size['file'];
		}
		foreach ( array_unique( $files ) as $file ) {
			self::encode( $dir . $file );
		}
		return $metadata;
	}

	public static function encode( $webp ) {
		$avif = substr( $webp, 0, -5 ) . '.avif';
		if ( ! file_exists( $webp ) || file_exists( $avif ) ) {
			return file_exists( $avif );
		}
		if ( function_exists( 'imageavif' ) ) {
			$im = @imagecreatefromwebp( $webp );
			if ( ! $im ) {
				return false;
			}
			$ok = imageavif( $im, $avif, self::QUALITY );
			imagedestroy( $im );
			return $ok;
		}
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}
		try {
			$image = new Imagick( $webp );
			$image->setImageFormat( 'avif' );
			$image->setImageCompressionQuality( self::QUALITY );
			$ok = $image->writeImage( $avif );
			$image->clear();
		} catch ( Exception $e ) {
			error_log( 'ASC_Avif: ' . $webp . ' -- ' . $e->getMessage() );
			return false;
		}
		return $ok;
	}
}
