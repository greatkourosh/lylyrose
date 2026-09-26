<?php

namespace Nabik\Gateland;

defined( 'ABSPATH' ) || exit;

class Helper {

	public static function redirect( string $url ) {
		header( 'Content-type: text/html; charset=utf-8' );

		header( "Location: " . $url, true, 307 );

		echo "<html lang='fa'></html>";
		flush();
		ob_flush();
		exit;
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	public static function fa_num( string $string ): string {
		return str_replace( range( 0, 9 ), [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ], $string );
	}

	public static function mobile( string $mobile, $link = true ): string {

		$_mobile = str_replace( [ '+98', ' ' ], [ '0', '' ], $mobile );
		$_mobile = self::fa_num( $_mobile );

		if ( $link ) {
			$mobile = sprintf( '<a href="tel:%s" target="_blank">%s</a>', $mobile, $_mobile );
		} else {
			$mobile = $_mobile;
		}

		return $mobile;
	}

	/**
	 * @param string $dateTime
	 * @param string $format
	 *
	 * @return false|string
	 */
	public static function date( string $dateTime, string $format = 'Y/m/d H:i:s' ) {
		return wp_date( $format, strtotime( $dateTime ) );
	}

	/**
	 * @param string $string
	 *
	 * @return string
	 */
	public static function en_num( string $string ): string {
		return str_replace( [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ], range( 0, 9 ), $string );
	}

	/**
	 * @return false|mixed|string
	 */
	public static function get_real_ip() {

		$proxy_headers = [
			'HTTP_CF_CONNECTING_IP',
			'CLIENT_IP',
			'FORWARDED',
			'FORWARDED_FOR',
			'FORWARDED_FOR_IP',
			'HTTP_CLIENT_IP',
			'HTTP_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED_FOR_IP',
			'HTTP_PC_REMOTE_ADDR',
			'HTTP_PROXY_CONNECTION',
			'HTTP_VIA',
			'HTTP_X_FORWARDED',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED_FOR_IP',
			'HTTP_X_IMFORWARDS',
			'HTTP_XROXY_CONNECTION',
			'VIA',
			'X_FORWARDED',
			'X_FORWARDED_FOR',
			'REMOTE_ADDR',
		];

		$pattern = "/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/";

		foreach ( $proxy_headers as $proxy_header ) {

			$ip = $_SERVER[ $proxy_header ] ?? null;

			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				continue;
			}

			if ( preg_match( $pattern, $ip ) ) {
				return $ip;
			}

			if ( stristr( ',', $ip ) !== false ) {

				$server            = explode( ',', $ip );
				$proxy_header_temp = trim( array_shift( $server ) );

				if ( ( $pos_temp = stripos( $proxy_header_temp, ':' ) ) !== false ) {
					$proxy_header_temp = substr( $proxy_header_temp, 0, $pos_temp );
				}

				if ( preg_match( $pattern, $proxy_header_temp ) ) {
					return $proxy_header_temp;
				}

			}

		}

		return '127.0.0.1';
	}
}