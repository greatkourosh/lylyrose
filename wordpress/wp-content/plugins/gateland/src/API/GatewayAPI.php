<?php

namespace Nabik\Gateland\API;

use Nabik\Gateland\Gateways\BaseGateway;
use Nabik\Gateland\Models\Gateway;
use Nabik\Gateland\Services\GatewayService;
use Nabik\Gateland\Services\TransactionService;
use Nabik\GatelandPro\GatelandPro;
use Rakit\Validation\Validator;
use WP_REST_Request;

class GatewayAPI extends RestAPI {

	public function register_routes() {

		register_rest_route( 'gateland/gateway', 'list', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'list' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/gateway', 'get-options', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'get_options' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/gateway', 'add', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'add' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/gateway', 'index', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'index' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/gateway', 'sort', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'sort' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/gateway', 'change-status', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'change_status' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/gateway', 'delete', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/gateway', 'update', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'update' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/gateway', 'overview', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'overview' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

	}

	public function list( WP_REST_Request $request ) {

		$used_gateways = GatewayService::used( false );
		$used_gateways = collect( $used_gateways )->map( function ( Gateway $gateway ) {
			return $gateway->class;
		} )->toArray();

		$gateways = [];

		foreach ( GatewayService::loaded() as $gateway ) {

			$class = get_class( $gateway );

			if ( in_array( $class, $used_gateways ) ) {
				continue;
			}

			$gateways[] = [
				'class'       => $class,
				'name'        => $gateway->name(),
				'description' => $gateway->description(),
				'url'         => $gateway->url(),
				'icon'        => $gateway->icon(),
				'features'    => GatewayService::get_features( $gateway ),
			];

		}

		self::response( true, null, [
			'is_pro_active' => class_exists( GatelandPro::class ) && GatelandPro::is_active(),
			'gateways'      => $gateways,
			'host_ip'       => GatewayService::get_host_ip(),
		] );
	}

	public function get_options( WP_REST_Request $request ) {

		$class = $request->get_param( 'class' );

		$valid_class = collect( GatewayService::loaded() )
			->map( function ( $gateway ) {
				return get_class( $gateway );
			} )
			->search( $class );

		if ( ! $valid_class ) {
			self::response( false, 'درگاه انتخاب شده معتبر نمی‌باشد.' );
		}

		/** @var BaseGateway $gateway */
		$gateway = new $class();

		self::response( true, null, [
			'options' => $gateway->options(),
		] );
	}

	public function add( WP_REST_Request $request ) {

		$class = $request->get_param( 'class' );

		$valid_class = collect( GatewayService::loaded() )
			->map( function ( $gateway ) {
				return get_class( $gateway );
			} )
			->search( $class );

		if ( ! $valid_class ) {
			self::response( false, 'درگاه انتخاب شده معتبر نمی‌باشد.' );
		}

		$data = $request->get_param( 'data' );

		if ( empty( $data ) ) {
			self::response( false, 'تنظیمات درگاه نمی‌تواند خالی باشد.' );
		}

		if ( ! is_array( $data ) ) {
			self::response( false, 'تنظیمات باید به صورت آرایه باشد.' );
		}

		/** @var Gateway $gateway */
		$gateway = Gateway::create( [
			'class'      => $class,
			'status'     => 'active',
			'data'       => $data,
			'currencies' => ( new $class )->currencies(),
		] );

		GatewayService::reset_activated();

		self::response( true, null, [
			'gateway_id' => $gateway->id,
		] );
	}

	public function index( WP_REST_Request $request ) {
		self::response( true, null, [
			'is_pro_active' => class_exists( GatelandPro::class ) && GatelandPro::is_active(),
			'gateways'      => $this->gateways(),
			'host_ip'       => GatewayService::get_host_ip(),
		] );
	}

	public function sort( WP_REST_Request $request ) {

		$gateway_ids = $request->get_param( 'gateway_ids' );

		if ( ! is_array( $gateway_ids ) ) {
			self::response( false, 'لیست درگاه‌ها معتبر نمی‌باشد.' );
		}

		// @todo check all gateways received

		try {

			collect( $gateway_ids )->each( function ( int $gateway_id, int $key ) {
				Gateway::query()->findOrFail( $gateway_id )->update( [ 'sort' => $key + 1 ] );
			} );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		GatewayService::reset_activated();

		self::response( true, 'درگاه‌ها با موفقیت اولویت‌بندی شدند.', [
			'gateways' => $this->gateways(),
		] );
	}

	public function change_status( WP_REST_Request $request ) {

		$gateway_id = $request->get_param( 'gateway_id' );
		$status     = $request->get_param( 'status' );

		if ( ! in_array( $status, [ 'active', 'inactive' ] ) ) {
			self::response( false, 'وضعیت وارد شده معتبر نمی‌باشد.' );
		}

		try {

			/** @var Gateway $gateway */
			$gateway = Gateway::query()->findOrFail( $gateway_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		$gateway->status = $status;
		$gateway->save();

		GatewayService::reset_activated();

		self::response( true, 'وضعیت با موفقیت ذخیره شد.', [
			'gateways' => $this->gateways(),
		] );
	}

	public function delete( WP_REST_Request $request ) {

		$gateway_id = $request->get_param( 'gateway_id' );

		try {

			/** @var Gateway $gateway */
			$gateway = Gateway::query()->findOrFail( $gateway_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( $gateway->transactions()->count() ) {
			self::response( false, 'درگاه‌های دارای تراکنش قابل حذف نیستند.' );
		}

		$gateway->delete();

		GatewayService::reset_activated();

		self::response( true, 'درگاه با موفقیت حذف شد.', [
			'gateways' => $this->gateways(),
		] );
	}

	public function update( WP_REST_Request $request ) {

		$gateway_id = $request->get_param( 'gateway_id' );
		$data       = $request->get_param( 'data' );

		if ( empty( $data ) ) {
			self::response( false, 'تنظیمات درگاه نمی‌تواند خالی باشد.' );
		}

		if ( ! is_array( $data ) ) {
			self::response( false, 'تنظیمات باید به صورت آرایه باشد.' );
		}

		try {

			/** @var Gateway $gateway */
			$gateway = Gateway::query()->findOrFail( $gateway_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		$gateway->data = $data;
		$gateway->save();

		self::response( true, 'درگاه با موفقیت بروزرسانی شد.' );
	}

	public function overview( WP_REST_Request $request ) {

		$validator = new Validator;

		$validation = $validator->validate( $request->get_body_params(), [
			'gateway_id' => 'required|numeric',
			'from_date'  => 'required|numeric',
			'to_date'    => 'required|numeric',
		] );

		if ( $validation->fails() ) {
			self::response( false, null, [
				'errors' => $validation->errors()->toArray(),
			] );
		}

		extract( $validation->getValidData() );

		$gateway_id = $request->get_param( 'gateway_id' );

		try {

			/** @var Gateway $gateway */
			$gateway = Gateway::query()->findOrFail( $gateway_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		$overview = TransactionService::overview( $from_date, $to_date, $gateway_id );

		self::response( true, '', $overview + [
				'gateway' => [
					'id'          => $gateway->id,
					'name'        => $gateway->build()->name(),
					'description' => $gateway->build()->description(),
					'status'      => $gateway->status,
					'sort'        => $gateway->sort,
					'options'     => $gateway->build()->options(),
					'data'        => $gateway->data,
				],
			] );
	}

	public function gateways(): array {
		return Gateway::query()
		              ->orderBy( 'sort' )
		              ->get()
		              ->map( [ $this, 'resource' ] )
		              ->toArray();
	}

	public function resource( Gateway $gateway ): array {

		$builder = $gateway->build();

		return [
			'id'          => $gateway->id,
			'name'        => $builder->name(),
			'description' => $builder->description(),
			'url'         => $builder->url(),
			'icon'        => $builder->icon(),
			'sort'        => $gateway->sort,
			'status'      => $gateway->status,
		];
	}
}