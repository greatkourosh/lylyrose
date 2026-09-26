<?php

namespace Nabik\Gateland\API;

use Nabik\Gateland\Services\TransactionService;
use Rakit\Validation\Validator;
use WP_REST_Request;

class DashboardAPI extends RestAPI {

	public function register_routes() {

		register_rest_route( 'gateland/dashboard', 'overview', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'overview' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

	}

	public function overview( WP_REST_Request $request ) {

		$validator = new Validator;

		$validation = $validator->validate( $request->get_body_params(), [
			'from_date' => 'required|numeric',
			'to_date'   => 'required|numeric',
		] );

		if ( $validation->fails() ) {
			self::response( false, null, [
				'errors' => $validation->errors()->toArray(),
			] );
		}

		extract( $validation->getValidData() );

		try {
			$overview = TransactionService::overview( $from_date, $to_date );
		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		self::response( true, null, $overview );
	}

}