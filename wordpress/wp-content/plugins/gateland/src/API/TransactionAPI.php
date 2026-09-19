<?php

namespace Nabik\Gateland\API;

use Illuminate\Database\Eloquent\Builder;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Gateway;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Services\GatewayService;
use Nabik\Gateland\Services\TransactionService;
use Nabik\GatelandPro\Exports\TransactionExport;
use Rakit\Validation\Validator;
use WP_REST_Request;

class TransactionAPI extends RestAPI {

	public function register_routes() {

		register_rest_route( 'gateland/transaction', 'filters', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'filters' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/transaction', 'index', [
			'methods'             => [ 'GET', 'POST' ],
			'callback'            => [ $this, 'index' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/transaction', 'view', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'view' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/transaction', 'inquiry', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'inquiry' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/transaction', 'refund', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'refund' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

	}

	public function filters( WP_REST_Request $request ) {

		// Gateways
		$gateways = GatewayService::used();
		$gateways = collect( $gateways )
			->map( function ( Gateway $gateway ) {
				return [
					'key'   => $gateway->id,
					'value' => $gateway->build()->name(),
				];
			} )
			->toArray();

		// Clients
		$clients = Transaction::getClients();
		$clients = collect( $clients )
			->map( function ( $value, $key ) {
				return [
					'key'   => $key,
					'value' => $value,
				];
			} )
			->values()
			->toArray();

		// Statuses
		$statuses = StatusesEnum::cases();
		$statuses = collect( $statuses )
			->map( function ( StatusesEnum $enum ) {
				return [
					'key'   => $enum->value,
					'value' => $enum->name(),
				];
			} )
			->values()
			->toArray();

		self::response( true, null, [
			'gateways' => $gateways,
			'clients'  => $clients,
			'statuses' => $statuses,
		] );
	}

	public function index( WP_REST_Request $request ) {

		$validator = new Validator;

		$statuses = implode( ',', array_keys( StatusesEnum::cases() ) );
		$clients  = implode( ',', array_keys( Transaction::getClients() ) );

		$validation = $validator->validate( $request->get_params(), [
			'page'           => 'required|numeric|min:1',
			'per_page'       => 'required|numeric|between:20,100',
			'gateway_ids'    => 'nullable|array',
			'gateway_ids.*'  => 'required|numeric',
			'clients'        => 'nullable|array',
			'clients.*'      => 'required|in:' . $clients,
			'status'         => 'nullable|in:' . $statuses,
			'from_date'      => 'nullable|numeric',
			'to_date'        => 'nullable|numeric',
			'card_number'    => 'nullable|numeric',
			'description'    => 'nullable|min:1',
			'gateway_au'     => 'nullable|min:1',
			'ip'             => 'nullable|min:1',
			'mobile'         => 'nullable|min:1',
			'transaction_id' => 'nullable|numeric',
			'order_id'       => 'nullable|numeric',
			'amount'         => 'nullable|numeric',
			'min_amount'     => 'nullable|numeric',
			'max_amount'     => 'nullable|numeric',
			'export'         => 'nullable|boolean',
		] );

		// @todo add error param

		if ( $validation->fails() ) {
			self::response( false, null, [
				'errors' => $validation->errors()->toArray(),
			] );
		}

		extract( $validation->getValidData() );

		$timezone = wp_timezone_string();

		if ( $from_date ) {

			$from_date = verta()
				->timestamp( intval( $from_date ) )
				->timezone( $timezone )
				->startDay()
				->toCarbon()
				->utc();

		}

		if ( $to_date ) {

			$to_date = verta()
				->timestamp( intval( $to_date ) )
				->timezone( $timezone )
				->endDay()
				->toCarbon()
				->utc();

		}

		/** @var Transaction[]|Builder $transactions */
		$transactions = Transaction::query()
		                           ->with( 'gateway' )
		                           ->orderByDesc( 'created_at' )
		                           ->when( $gateway_ids, function ( Builder $query ) use ( $gateway_ids ) {
			                           $query->whereIn( 'gateway_id', $gateway_ids );
		                           } )
		                           ->when( $clients, function ( Builder $query ) use ( $clients ) {
			                           $query->whereIn( 'client', $clients );
		                           } )
		                           ->when( $status, function ( Builder $query ) use ( $status ) {
			                           $query->where( 'status', $status );
		                           } )
		                           ->when( $card_number, function ( Builder $query ) use ( $card_number ) {
			                           $query->where( 'card_number', 'LIKE', "%{$card_number}%" );
		                           } )
		                           ->when( $description, function ( Builder $query ) use ( $description ) {
			                           $query->where( 'description', 'LIKE', "%{$description}%" );
		                           } )
		                           ->when( $mobile, function ( Builder $query ) use ( $mobile ) {
			                           $query->where( 'mobile', 'LIKE', "%{$mobile}%" );
		                           } )
		                           ->when( $ip, function ( Builder $query ) use ( $ip ) {
			                           $query->where( 'ip', 'LIKE', "%{$ip}%" );
		                           } )
		                           ->when( $gateway_au, function ( Builder $query ) use ( $gateway_au ) {
			                           $query->where( 'gateway_au', 'LIKE', "%{$gateway_au}%" );
		                           } )
		                           ->when( $transaction_id, function ( Builder $query ) use ( $transaction_id ) {
			                           $query->where( 'id', 'LIKE', "%{$transaction_id}%" );
		                           } )
		                           ->when( $order_id, function ( Builder $query ) use ( $order_id ) {
			                           $query->where( 'order_id', $order_id );
		                           } )
		                           ->when( $order_id, function ( Builder $query ) use ( $order_id ) {
			                           $query->where( 'order_id', $order_id );
		                           } )
		                           ->when( $amount, function ( Builder $query ) use ( $amount ) {
			                           $query->where( 'amount', $amount );
		                           } )
		                           ->when( $min_amount, function ( Builder $query ) use ( $min_amount ) {
			                           $query->where( 'amount', '>=', $min_amount );
		                           } )
		                           ->when( $max_amount, function ( Builder $query ) use ( $max_amount ) {
			                           $query->where( 'amount', '<=', $max_amount );
		                           } )
		                           ->when( $from_date, function ( Builder $query ) use ( $from_date ) {
			                           $query->where( 'created_at', '>=', $from_date );
		                           } )
		                           ->when( $to_date, function ( Builder $query ) use ( $to_date ) {
			                           $query->where( 'created_at', '<=', $to_date );
		                           } );

		$total_items = $transactions->count();

		if ( $export ) {

			if ( ! class_exists( TransactionExport::class ) ) {
				wp_die( 'جهت دریافت خروجی اکسل، گیت‌لند حرفه‌ای را نصب و فعال نمایید.' );
			}

			$filters = compact(
				'from_date',
				'to_date',
				'gateway_ids',
				'clients',
				'status',
				'card_number',
				'description',
				'gateway_au',
				'ip',
				'mobile',
				'transaction_id',
				'order_id',
				'amount',
				'min_amount',
				'max_amount'
			);

			TransactionExport::excel( $transactions->limit( 1000 )->get(), $filters );
		}

		$statuses = $transactions->clone()
		                         ->selectRaw( 'status, count(*) as count' )
		                         ->orWhereNotNull( 'status' )
		                         ->groupBy( 'status' )
		                         ->get()
		                         ->toArray();

		$transactions = $transactions->forPage( $page, $per_page )
		                             ->get()
		                             ->map( [ $this, 'resource' ] )
		                             ->toArray();

		self::response( true, null, [
			'current_page' => intval( $page ),
			'total_items'  => intval( $total_items ),
			'statuses'     => $statuses,
			'transactions' => $transactions,
		] );
	}

	public function view( WP_REST_Request $request ) {

		$transaction_id = $request->get_param( 'transaction_id' );

		try {

			/** @var Transaction $transaction */
			$transaction = Transaction::query()->findOrFail( $transaction_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		$gateway_features = [];

		if ( $transaction->gateway ) {
			$gateway_features = GatewayService::get_features( $transaction->gateway->class );
		}

		$refund_meta = [];
		$refunded_at = null;

		if ( $transaction->refund ) {
			$refund_meta = [
				'مبلغ'          => CurrenciesEnum::tryFrom( $transaction->currency )->price( $transaction->refund->amount ),
				'شناسه استرداد' => $transaction->refund->refund_id,
				'توضیحات'       => $transaction->refund->description,
				'شناسه کاربر'   => get_userdata( $transaction->refund->user_id )->display_name,
			];

			$refunded_at = Helper::date( $transaction->refund->created_at );
		}

		self::response( true, null, [
			'id'               => $transaction->id,
			'amount'           => $transaction->amount,
			'currency'         => CurrenciesEnum::tryFrom( $transaction->currency )->symbol(),
			'status'           => $transaction->status,
			'gateway'          => $transaction->gateway_label,
			'created_at'       => Helper::date( $transaction->created_at ),
			'paid_at'          => $transaction->paid_at ? Helper::date( $transaction->paid_at ) : null,
			'verified_at'      => $transaction->verified_at ? Helper::date( $transaction->verified_at ) : null,
			'gateway_features' => $gateway_features,
			'can_refund'       => $transaction->status == StatusesEnum::STATUS_PAID,
			'meta'             => [
				'توضیحات'      => $transaction->description,
				'شناسه سفارش'  => $transaction->order_id,
				'آدرس آی.پی'   => $transaction->ip,
				'شناسه پیگیری' => $transaction->gateway_trans_id,
				'وضعیت درگاه'  => $transaction->gateway_status,
				'شناسه پرداخت' => $transaction->gateway_au,
				'شماره کارت'   => $transaction->card_number,
				'تلفن همراه'   => $transaction->mobile,
				'پذیرنده'      => $transaction->client_label,
			],
			'order'            => [
				'url' => $transaction->getClientOrderUrl(),
				'id'  => $transaction->order_id,
			],
			'refund_meta'      => $refund_meta,
			'refunded_at'      => $refunded_at,
		] );
	}

	public function inquiry( WP_REST_Request $request ) {

		$transaction_id = $request->get_param( 'transaction_id' );

		try {

			/** @var Transaction $transaction */
			$transaction = Transaction::query()->findOrFail( $transaction_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( $transaction->isVerified() ) {
			self::response( false, 'تراکنش نیاز به استعلام ندارد.' );
		}

		try {
			TransactionService::fix( $transaction );
		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		self::response( true );
	}

	public function refund( WP_REST_Request $request ) {

		$transaction_id = $request->get_param( 'transaction_id' );

		try {

			/** @var Transaction $transaction */
			$transaction = Transaction::query()->findOrFail( $transaction_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( ! class_exists( \Nabik\GatelandPro\Services\TransactionService::class ) ) {
			self::response( true, 'قابلیت عودت وجه در گیت‌لند حرفه‌ای فعال است.' );
		}

		$amount      = $request->get_param( 'amount' );
		$description = $request->get_param( 'description' );
		$refund_id   = $request->get_param( 'refund_id' );

		try {
			\Nabik\GatelandPro\Services\TransactionService::refund( $transaction, $amount, $description, $refund_id );

			self::response( true, 'عودت وجه با موفقیت انجام شد.' );
		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}
	}

	public function resource( Transaction $transaction ): array {
		return [
			'id'         => $transaction->id,
			'client'     => $transaction->client_label,
			'gateway'    => $transaction->gateway_label,
			'amount'     => CurrenciesEnum::tryFrom( $transaction->currency )->price( $transaction->amount ),
			'created_at' => Helper::date( $transaction->created_at, 'Y/m/d H:i' ),
			'order_url'  => $transaction->getClientOrderUrl(),
			'order_id'   => $transaction->order_id,
			'mobile'     => Helper::mobile( $transaction->mobile, false ),
			'status'     => $transaction->status,
		];
	}

}