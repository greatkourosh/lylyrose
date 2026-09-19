<?php

namespace Nabik\Gateland\Helpers;

use Sqids\Sqids;

class SQID {

	private static string $alphabet = '';

	public static function encode( int $number ): string {

		$sqids = new Sqids( self::get_alphabet(), 5 );

		return $sqids->encode( [ $number ] );
	}

	public static function decode( string $token ): int {

		$sqids = new Sqids( self::get_alphabet(), 5 );

		return $sqids->decode( $token )[0] ?? 0;
	}

	private static function get_alphabet(): string {

		if ( self::$alphabet ) {
			return self::$alphabet;
		}

		$alphabet = AUTH_KEY . sha1( DB_HOST . DB_USER . DB_PASSWORD . DB_NAME );
		$alphabet = preg_replace( "/[^a-z0-9]/", '', strtolower( $alphabet ) );
		$alphabet = implode( '', array_unique( str_split( $alphabet ) ) );

		self::$alphabet = str_replace( [ 'i', '1', 'l', 'o', '0' ], '', $alphabet );

		return self::$alphabet;
	}

}