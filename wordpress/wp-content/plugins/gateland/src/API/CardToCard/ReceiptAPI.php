<?php

namespace Nabik\Gateland\API\CardToCard;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Nabik\Gateland\API\RestAPI;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Card;
use Nabik\Gateland\Models\Receipt;
use Nabik\Gateland\Services\TransactionService;
use Nabik\Gateland\Services\ZohalService;
use Nabik\GatelandPro\Exports\ReceiptExport;
use Nabik\GatelandPro\Services\CardToCardService;
use Rakit\Validation\Validator;
use WP_REST_Request;

class ReceiptAPI extends RestAPI {

	public function register_routes() {

		register_rest_route( 'gateland/card-to-card/receipt', 'filters', [
			'methods'             => [ 'POST' ],
			'callback'            => [ $this, 'filters' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/receipt', 'index', [
			'methods'             => [ 'GET', 'POST' ],
			'callback'            => [ $this, 'index' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/receipt', 'view', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'view' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/receipt', 'accept', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'accept' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/receipt', 'reject', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reject' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/receipt', 'inquiry-card-number', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'inquiry_card_number' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function filters( WP_REST_Request $request ) {

		// Destination cards
		$destination_cards = Card::query()
		                         ->orderBy( 'status' )
		                         ->get()
		                         ->map( function ( Card $card ) {
			                         return [
				                         'id'                    => $card->id,
				                         'name'                  => $card->name,
				                         'card_number'           => $card->card_number,
				                         'formatted_card_number' => $card->formatted_card_number,
				                         'status'                => $card->status,
			                         ];
		                         } )
		                         ->values()
		                         ->toArray();

		self::response( true, null, [
			'destination_cards' => $destination_cards,
		] );
	}

	public function index( WP_REST_Request $request ) {

		$validator = new Validator;

		$destination_cards = implode( ',', Card::query()->get( 'id' )->pluck( 'id' )->toArray() );

		$validation = $validator->validate( $request->get_params(), [
			'page'                => 'required|numeric|min:1',
			'per_page'            => 'required|numeric|between:20,100',
			'receipt_id'          => 'nullable|integer',
			'transaction_id'      => 'nullable|integer',
			'destination_card_id' => 'nullable|integer|in:' . $destination_cards,
			'card_number'         => 'nullable',
			'status'              => 'nullable|in:pending,accepted,rejected',
			'amount'              => 'nullable|numeric',
			'min_amount'          => 'nullable|numeric',
			'max_amount'          => 'nullable|numeric',
			'export'              => 'nullable|boolean',
		] );

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

		$receipts = Receipt::query()
		                   ->when( $receipt_id, function ( Builder $query ) use ( $receipt_id ) {
			                   $query->where( 'id', 'LIKE', "%{$receipt_id}%" );
		                   } )
		                   ->when( $transaction_id, function ( Builder $query ) use ( $transaction_id ) {
			                   $query->where( 'transaction_id', 'LIKE', "%{$transaction_id}%" );
		                   } )
		                   ->when( $destination_card_id, function ( Builder $query ) use ( $destination_card_id ) {
			                   $query->where( 'card_id', $destination_card_id );
		                   } )
		                   ->when( $card_number, function ( Builder $query ) use ( $card_number ) {
			                   $query->where( 'card_number', "%{$card_number}%" );
		                   } )
		                   ->when( $status, function ( Builder $query ) use ( $status ) {
			                   $query->where( 'status', $status );
		                   } )
		                   ->when( $amount, function ( Builder $query ) use ( $amount ) {
			                   $query->where( 'accepted_amount', $amount );
		                   } )
		                   ->when( $min_amount, function ( Builder $query ) use ( $min_amount ) {
			                   $query->where( 'accepted_amount', '>=', $min_amount );
		                   } )
		                   ->when( $max_amount, function ( Builder $query ) use ( $max_amount ) {
			                   $query->where( 'accepted_amount', '<=', $max_amount );
		                   } )
		                   ->when( $from_date, function ( Builder $query ) use ( $from_date ) {
			                   $query->where( 'created_at', '>=', $from_date );
		                   } )
		                   ->when( $to_date, function ( Builder $query ) use ( $to_date ) {
			                   $query->where( 'created_at', '<=', $to_date );
		                   } )
		                   ->orderByDesc( 'created_at' )
		                   ->with( [ 'transaction', 'card' ] );

		$total_items = $receipts->count();

		if ( $export ) {

			if ( ! class_exists( ReceiptExport::class ) ) {
				wp_die( 'جهت دریافت خروجی اکسل، گیت‌لند حرفه‌ای را نصب و فعال نمایید.' );
			}

			$filters = compact(
				'status',
			);

			ReceiptExport::excel( $receipts->limit( 1000 )->get(), $filters );
		}

		$statuses = $receipts->clone()
		                     ->selectRaw( 'status, count(*) as count' )
		                     ->orWhereNotNull( 'status' )
		                     ->groupBy( 'status' )
		                     ->get()
		                     ->toArray();

		$receipts = $receipts->forPage( $page, $per_page )
		                     ->get()
		                     ->map( [ $this, 'resource' ] )
		                     ->toArray();

		self::response( true, null, [
			'current_page' => intval( $page ),
			'total_items'  => intval( $total_items ),
			'statuses'     => $statuses,
			'receipts'     => $receipts,
		] );
	}

	public function view( WP_REST_Request $request ) {

		$receipt = $this->get_receipt( $request );

		$next_receipt_id = Receipt::query()
		                          ->where( 'status', 'pending' )
		                          ->where( 'id', '!=', $receipt->id )
		                          ->orderBy( 'id' )
		                          ->value( 'id' );

		$pending_receipts_count = Receipt::query()
		                                 ->where( 'status', 'pending' )
		                                 ->count();

		if ( $receipt->status == 'pending' ) {
			$pending_receipts_count --;
		}

		self::response( true, null, [
			'tracking_number'        => $receipt->tracking_number,
			'amount'                 => $receipt->amount,
			'accepted_amount'        => $receipt->accepted_amount,
			'currency'               => CurrenciesEnum::tryFrom( $receipt->transaction->currency )->symbol(),
			'status'                 => $receipt->status,
			'created_at'             => Helper::date( $receipt->created_at ),
			'updated_at'             => Helper::date( $receipt->updated_at ),
			'reviewed'               => [
				'by' => get_userdata( $receipt->reviewed_by )->display_name ?? null,
				'at' => $receipt->reviewed_at ? Helper::date( $receipt->reviewed_at ) : null,
			],
			'attachment'             => [
				'id'  => $receipt->attachment_id,
				'url' => $receipt->attachment_url,
			],
			'transaction'            => [
				'id'         => $receipt->transaction->id,
				'amount'     => $receipt->transaction->amount,
				'client'     => $receipt->transaction->client_label,
				'status'     => $receipt->transaction->status,
				'created_at' => Helper::date( $receipt->transaction->created_at ),
			],
			'source_card'            => [
				'name'        => $receipt->meta['card_number_owner'] ?? null,
				'card_number' => $receipt->formatted_card_number,
			],
			'destination_card'       => [
				'id'          => $receipt->card->id,
				'name'        => $receipt->card->name,
				'card_number' => $receipt->card->formatted_card_number,
			],
			'next_receipt_id'        => $next_receipt_id,
			'pending_receipts_count' => $pending_receipts_count,
		] );
	}

	public function accept( WP_REST_Request $request ) {

		$receipt = $this->get_receipt( $request );

		if ( $receipt->status == 'accepted' ) {
			self::response( false, 'این رسید قبلا تایید شده است.' );
		}

		$accepted_amount = intval( $request->get_param( 'accepted_amount' ) );

		if ( $accepted_amount == 0 ) {
			self::response( false, 'مبلغ تایید شده نمی‌تواند صفر باشد.' );
		}

		$receipt->status          = 'accepted';
		$receipt->accepted_amount = $accepted_amount;
		$receipt->reviewed_by     = get_current_user_id();
		$receipt->reviewed_at     = Carbon::now();
		$receipt->save();

		$receipt->transaction->logs()->create( [
			'event' => 'CardToCard::accept',
			'data'  => [
				'accepted_amount' => $receipt->accepted_amount,
				'reviewed_by'     => $receipt->reviewed_by,
			],
		] );

		// @todo move to cardToCardService
		$accepted_amount = Receipt::query()
		                          ->where( 'transaction_id', $receipt->transaction_id )
		                          ->where( 'status', 'accepted' )
		                          ->sum( 'accepted_amount' );

		if ( $accepted_amount >= $receipt->transaction->amount ) {

			$receipt->transaction->logs()->create( [
				'event' => 'CardToCard::complete',
				'data'  => [
					'accepted_amount' => $receipt->accepted_amount,
					'reviewed_by'     => $receipt->reviewed_by,
				],
			] );

			$receipt->transaction->status  = 'paid';
			$receipt->transaction->paid_at = Carbon::now();
			$receipt->transaction->save();

			try {
				TransactionService::fix( $receipt->transaction );
			} catch ( \Exception $e ) {

				$receipt->transaction->logs()->create( [
					'event' => 'CardToCard::completeFailed',
					'data'  => [
						'error' => $e->getMessage(),
					],
				] );

				self::response( true, 'رسید تایید شد ولی خطایی در زمان تغییر تراکنش به پرداخت شده رخ داد.' );
			}

			self::response( true, 'رسید تایید شد، تراکنش به پرداخت شده تغییر کرد.' );
		}

		self::response( true, 'رسید با موفقیت تایید شد.' );
	}

	public function reject( WP_REST_Request $request ) {

		$receipt = $this->get_receipt( $request );

		if ( $receipt->status != 'pending' ) {
			self::response( false, 'فقط رسید‌های در انتظار قابل رد کردن هستند.' );
		}

		$receipt->status          = 'rejected';
		$receipt->accepted_amount = 0;
		$receipt->reviewed_by     = get_current_user_id();
		$receipt->reviewed_at     = Carbon::now();
		$receipt->save();

		$receipt->transaction->logs()->create( [
			'event' => 'CardToCard::reject',
			'data'  => [
				'reviewed_by' => $receipt->reviewed_by,
			],
		] );

		self::response( true, 'رسید تراکنش رد شد.' );
	}

	public function inquiry_card_number( WP_REST_Request $request ) {

		$receipt = $this->get_receipt( $request );

		if ( isset( $receipt->meta['card_number_owner'] ) ) {
			self::response( true, 1, [
				'card_number_owner' => $receipt->meta['card_number_owner'],
			] );
		}

		if ( ZohalService::is_enable() ) {

			try {

				$response = ZohalService::call( 'services/inquiry/card_inquiry', [
					'card_number' => $receipt->card_number,
				] );

			} catch ( \Exception $e ) {
				self::response( false, $e->getMessage() );
			}

			if ( isset( $response['data']['name'] ) ) {

				$receipt->meta = [ 'card_number_owner' => $response['data']['name'] ] + (array) $receipt->meta;
				$receipt->save();

				self::response( true, 'زحل: ' . $receipt->meta['card_number_owner'], [
					'card_number_owner' => $receipt->meta['card_number_owner'],
				] );

			}

			self::response( false, 'زحل: ' . $response['message'] );
		}

		self::response( false, 'سرویس زحل را از مسیر گیت‌لند » تنظیمات » زحل، فعال کنید.' );
	}

	public function resource( Receipt $receipt ): array {
		return [
			'id'              => $receipt->id,
			'transaction_id'  => $receipt->transaction_id,
			'amount'          => $receipt->amount,
			'accepted_amount' => $receipt->accepted_amount,
			'created_at'      => Helper::date( $receipt->created_at, 'Y/m/d H:i' ),
			'status'          => $receipt->status,
			'currency'        => CurrenciesEnum::tryFrom( $receipt->transaction->currency )->symbol(),
		];
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return Receipt
	 */
	public function get_receipt( WP_REST_Request $request ): Receipt {

		$receipt_id = $request->get_param( 'receipt_id' );

		try {

			/** @var Receipt $receipt */
			$receipt = Receipt::query()
			                  ->with( [ 'transaction', 'card' ] )
			                  ->findOrFail( $receipt_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		return $receipt;
	}

	public function permission_callback( WP_REST_Request $request ): bool {

		if ( ! class_exists( CardToCardService::class ) ) {
			self::response( false, 'آخرین نسخه گیت‌لند حرفه‌ای را نصب و فعال کنید.' );
		}

		return parent::permission_callback( $request );
	}
}