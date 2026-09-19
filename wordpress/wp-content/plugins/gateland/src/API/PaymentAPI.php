<?php

namespace Nabik\Gateland\API;

use Nabik\Gateland\Pay;
use WP_REST_Request;

class PaymentAPI extends RestAPI {

	public function register_routes() {

		register_rest_route( 'gateland/payment', '(?P<token>[^/]+)/start', [
			'methods'             => [ 'GET', 'POST' ],
			'callback'            => [ $this, 'start' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'gateland/payment', '(?P<token>[^/]+)/callback', [
			'methods'             => [ 'GET', 'POST' ],
			'callback'            => [ $this, 'callback' ],
			'permission_callback' => '__return_true',
		] );

	}

	public function start( WP_REST_Request $request ) {

		$url_params        = $request->get_url_params();
		$transaction_token = strval( $url_params['token'] ?? null );

		header( 'Content-Type: text/html' );

		Pay::pay( $transaction_token );

		exit();
	}

	public function callback( WP_REST_Request $request ) {

		$url_params        = $request->get_url_params();
		$transaction_token = strval( $url_params['token'] ?? null );

		$query_params = $request->get_query_params();
		$sign         = strval( $query_params['sign'] ?? null );

		header( 'Content-Type: text/html' );

		Pay::callback( $transaction_token, $sign );

		exit();
	}

}