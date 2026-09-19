<?php

namespace Nabik\Gateland\Services;

use Exception;
use Nabik\Gateland\Gateland;
use Nabik\Gateland\Helpers\Curl;

class ZohalService {

	/**
	 * @throws Exception
	 */
	public static function call( string $service, array $data ): array {

		$response = Curl::post( 'https://service.zohal.io/api/v0/' . trim( $service, '/' ), json_encode( $data ), [
			'Content-Type: application/json',
			'Authorization: Bearer ' . Gateland::get_option( 'zohal.api_key' ),
			'zohal-reseller-id: gateland',
		] );

		$result = $response['result'] ?? 0;

		if ( $result == 1 ) {
			return $response['response_body'];
		}

		throw new Exception( $response['response_body']['message'], $result );
	}

	public static function is_enable(): bool {
		$api_key = Gateland::get_option( 'zohal.api_key' );

		return ! empty( $api_key );
	}

}