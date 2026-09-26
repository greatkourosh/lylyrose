<?php

namespace Nabik\Gateland\Services;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Models\Transaction;

class TransactionService {

	const VERIFY_TIME = 1;

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool
	 */
	public static function need_to_verify( Transaction $transaction ): bool {
		return empty( $transaction->verified_at ) && ! empty( $transaction->paid_at ) && ( $transaction->status === StatusesEnum::STATUS_PAID );
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool
	 */
	public static function need_to_inquiry( Transaction $transaction ): bool {

		if ( empty( $transaction->gateway ) ) {
			return false;
		}

		if ( ! empty( $transaction->paid_at ) ) {
			return false;
		}

		if ( $transaction->status != StatusesEnum::STATUS_PENDING ) {
			return false;
		}

		return $transaction->gateway->build() instanceof InquiryFeature;
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool
	 */
	public static function need_to_retry( Transaction $transaction ): bool {
		return ( ! empty( $transaction->paid_at ) && $transaction->status === StatusesEnum::STATUS_FAILED );
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function verify( Transaction $transaction ): void {

		if ( ! self::need_to_verify( $transaction ) ) {
			return;
		}

		$transaction->logs()->create( [
			'event' => 'Transaction::reVerify',
			'data'  => [
				'transaction' => $transaction->toArray(),
			],
		] );

		try {

			$response = wp_remote_get( $transaction->callback );

			if ( is_wp_error( $response ) ) {
				throw new Exception( $response->get_error_message() );
			}

		} catch ( Exception $exception ) {

			$transaction->logs()->create( [
				'event' => 'Transaction::reVerifyFailed',
				'data'  => [
					'transaction' => $transaction->toArray(),
					'error'       => $exception->getMessage(),
				],
			] );

			throw new Exception( $exception->getMessage() );
		}

	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function inquiry( Transaction $transaction ): void {

		if ( ! self::need_to_inquiry( $transaction ) ) {
			return;
		}

		$transaction->logs()->create( [
			'event' => 'Transaction::inquiry',
			'data'  => [
				'transaction' => $transaction->toArray(),
			],
		] );

		try {

			$inquiryStatus = $transaction->gateway->build()->inquiry( $transaction );

		} catch ( Exception $exception ) {

			$transaction->logs()->create( [
				'event' => 'Transaction::inquiryFailed',
				'data'  => [
					'transaction' => $transaction->toArray(),
					'error'       => $exception->getMessage(),
				],
			] );

			throw new Exception( $exception->getMessage() );
		}

		if ( $inquiryStatus === true ) {
			self::verify( $transaction );
		}

	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function retry( Transaction $transaction ): void {

		if ( ! self::need_to_retry( $transaction ) ) {
			return;
		}

		$transaction->logs()->create( [
			'event' => 'Transaction::reTry',
			'data'  => [
				'transaction' => $transaction->toArray(),
			],
		] );

		$transaction->update( [
			'status' => StatusesEnum::STATUS_PAID,
		] );

		self::verify( $transaction );
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function fix( Transaction $transaction ): void {

		self::retry( $transaction );

		self::inquiry( $transaction );

		self::verify( $transaction );

	}

	/**
	 * @param bool $action
	 *
	 * @return Collection
	 */
	public static function incorrect_transactions( bool $action = false ): Collection {

		return Transaction::query()
		                  ->where( function ( Builder $builder ) {
			                  $builder->where( function ( Builder $query ) {
				                  // Need to verify
				                  $query->whereNull( 'verified_at' )
				                        ->whereNotNull( 'paid_at' )
				                        ->where( 'paid_at', '<', Carbon::now()->subMinutes( self::VERIFY_TIME ) )
				                        ->where( 'status', StatusesEnum::STATUS_PAID );

			                  } )->orWhere( function ( Builder $query ) {
				                  // Need to retry
				                  $query->whereNotNull( 'paid_at' )
				                        ->where( 'status', StatusesEnum::STATUS_FAILED );

			                  } );
		                  } )
		                  ->when( $action, function ( Builder $builder ) {
			                  $builder->whereHas( 'logs', function ( Builder $query ) {
				                  $query->whereIn( 'event', [
					                  'Transaction::reVerify',
					                  'Transaction::reTry',
				                  ] );
			                  }, '<=', 5 );
		                  } )
		                  ->inRandomOrder()
		                  ->get();

	}

	/**
	 * @param int      $from_date
	 * @param int      $to_date
	 * @param int|null $gateway_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function overview( int $from_date, int $to_date, int $gateway_id = null ): array {

		$timezone = wp_timezone_string();

		$from_date = verta()
			->timestamp( $from_date )
			->timezone( $timezone )
			->startDay();

		$to_date = verta()
			->timestamp( $to_date )
			->timezone( $timezone )
			->endDay();

		$queries = self::builder_period( $from_date->clone(), $to_date->clone() );

		$from_date = $from_date->toCarbon()
		                       ->utc();

		$to_date = $to_date->toCarbon()
		                   ->utc();

		/** @var Transaction[]|Builder $transactions */
		$transactions = Transaction::query()
		                           ->with( 'gateway' )
		                           ->orderByDesc( 'created_at' )
		                           ->when( $gateway_id, function ( Builder $query ) use ( $gateway_id ) {
			                           $query->where( 'gateway_id', $gateway_id );
		                           } )
		                           ->whereBetween( 'created_at', [ $from_date, $to_date ] );

		$total_amount = $transactions->clone()
		                             ->where( 'status', StatusesEnum::STATUS_PAID )
		                             ->sum( 'amount' );

		$total_transactions = $transactions->count();
		$paid_transaction   = $transactions->clone()
		                                   ->where( 'status', StatusesEnum::STATUS_PAID )
		                                   ->count();
		$success_rate       = $total_transactions ? $paid_transaction / $total_transactions : 0;

		$average_payment_time = $transactions->clone()
		                                     ->whereNotNull( 'paid_at' )
		                                     ->selectRaw( 'AVG(TIMESTAMPDIFF(SECOND, created_at, paid_at)) as avg' )
		                                     ->reorder()
		                                     ->value( 'avg' );

		$interval             = CarbonInterval::seconds( round( floatval( $average_payment_time ) ) )->cascade();
		$average_payment_time = sprintf( '%02d:%02d', $interval->totalMinutes, $interval->seconds );

		$donut_chart = $transactions->selectRaw( 'status, count(*) as count' )
		                            ->groupBy( 'status' )
		                            ->get()
		                            ->map( function ( $item ) {
			                            return [
				                            'status' => $item->status,
				                            'value'  => $item->count,
			                            ];
		                            } )
		                            ->toArray();

		$bar_chart = [];

		foreach ( $queries as $query ) {

			$amount = Transaction::query()
			                     ->when( $gateway_id, function ( Builder $query ) use ( $gateway_id ) {
				                     $query->where( 'gateway_id', $gateway_id );
			                     } )
			                     ->where( 'status', StatusesEnum::STATUS_PAID )
			                     ->whereBetween( 'created_at', [ $query['begin'], $query['end'] ] )
			                     ->sum( 'amount' );

			$bar_chart[] = [
				'label' => $query['label'],
				'value' => intval( $amount ),
			];

		}

		return [
			'statistics'  => [
				'total_amount'         => CurrenciesEnum::tryFrom( 'IRT' )->price( $total_amount ),
				'total_transactions'   => $total_transactions,
				'average_payment_time' => $average_payment_time,
				'success_rate'         => number_format( $success_rate * 100, 1 ),
			],
			'donut_chart' => $donut_chart,
			'bar_chart'   => $bar_chart,
		];
	}

	/**
	 * @param Verta $from_date
	 * @param Verta $to_date
	 *
	 * @return array
	 */
	public static function builder_period( $from_date, $to_date ): array {
		$queries  = [];
		$timezone = wp_timezone_string();

		$begin_date = $from_date->clone();

		if ( $from_date->clone()->addYear()->lte( $to_date ) ) {
			// We should show yearly

			do {

				$queries[] = [
					'label' => $from_date->format( 'Y' ),
					'begin' => $from_date->timezone( $timezone )->startYear()->toCarbon()->utc()->format( 'Y-m-d H:i:s' ),
					'end'   => $from_date->timezone( $timezone )->endYear()->toCarbon()->utc()->format( 'Y-m-d H:i:s' ),
				];

				$from_date->addDay();

			} while ( $from_date->lte( $to_date ) );

		} elseif ( $from_date->clone()->addMonth()->lt( $to_date ) ) {
			// We should show monthly

			do {

				$queries[] = [
					'label' => $from_date->format( 'F Y' ),
					'begin' => $from_date->timezone( $timezone )->startMonth()->toCarbon()->utc()->format( 'Y-m-d H:i:s' ),
					'end'   => $from_date->timezone( $timezone )->endMonth()->toCarbon()->utc()->format( 'Y-m-d H:i:s' ),
				];

				$from_date->addDay();

			} while ( $from_date->lte( $to_date ) );

		} else {

			do {

				$queries[] = [
					'label' => $from_date->format( 'd F' ),
					'begin' => $from_date->timezone( $timezone )->startDay()->toCarbon()->utc()->format( 'Y-m-d H:i:s' ),
					'end'   => $from_date->timezone( $timezone )->endDay()->toCarbon()->utc()->format( 'Y-m-d H:i:s' ),
				];

				$from_date->addDay();

			} while ( $from_date->lte( $to_date ) );

		}

		if ( isset( $queries[0] ) ) {
			$queries[0]['begin'] = $begin_date->timezone( $timezone )->startDay()->toCarbon()->utc()->format( 'Y-m-d H:i:s' );
		}

		if ( count( $queries ) >= 2 ) {
			$queries[ count( $queries ) - 1 ]['end'] = $to_date->timezone( $timezone )->endDay()->toCarbon()->utc()->format( 'Y-m-d H:i:s' );
		}

		return $queries;
	}

}