<?php

namespace Nabik\Gateland\Gateways;

use Exception;
use Illuminate\Database\QueryException;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Models\Transaction;

abstract class BaseGateway {

	protected string $name;

	protected string $description;

	protected string $url;

	public array $options = [];

	/**
	 * @throws Exception
	 */
	public function __construct() {
		if ( empty( $this->name ) || empty( $this->description ) || empty( $this->url ) ) {
			throw new Exception( sprintf( 'Class %s was not initiate properties.', get_called_class() ) );
		}
	}

	public function name(): string {
		$title = $this->options['title'] ?? '';

		if ( ! empty( $title ) ) {
			return $this->name . ' - ' . $title;
		}

		return $this->name;
	}

	public function description(): string {
		return $this->description;
	}

	public function icon(): string {
		return GATELAND_URL . ( sprintf( 'assets/images/gateways/%s.png', $this->slug() ) );
	}

	public function url(): string {
		return $this->url;
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	abstract public function request( Transaction $transaction ): void;

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool
	 *
	 * @throws VerifyException
	 * @throws InquiryException
	 */
	abstract public function inquiry( Transaction $transaction ): bool;

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool
	 *
	 * @throws VerifyException
	 */
	public function verify( Transaction $transaction ): bool {
		$this->log( $transaction, 'verify', [
			'transaction' => $transaction->toArray(),
		] );

		try {
			$this->inquiry( $transaction );

			return true;
		} catch ( InquiryException $e ) {
			$this->log( $transaction, 'verifyFailed' );

			$transaction->update( [
				'gateway_status' => $e->getMessage(),
				'status'         => StatusesEnum::STATUS_FAILED,
			] );

			return false;
		} catch ( QueryException $e ) {
			throw new VerifyException( 'خطای پایگاه‌داده در زمان تایید تراکنش رخ داده است.' );
		}
	}

	public function cancelled( Transaction $transaction, $status = null ): bool {
		$this->log( $transaction, 'verifyCancelled', [
			'status' => $status,
		] );

		$transaction->update( [
			'gateway_status' => $status,
			'status'         => StatusesEnum::STATUS_FAILED,
		] );

		return false;
	}

	abstract public function redirect( Transaction $transaction );

	/**
	 * @return CurrenciesEnum[]
	 */
	abstract public function currencies(): array;

	abstract public function options(): array;

	/**
	 * @throws Exception
	 */
	public function curl( string $url, $data = null, array $headers = [], array $curl_opt = [] ): array {
		$curl = curl_init( $url );

		if ( empty( $headers ) ) {
			$headers[] = 'Accept: application/json';
		}

		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 8 );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );

		curl_setopt_array( $curl, apply_filters( 'nabik/gateland/curl_options', $curl_opt, $url, $data, $headers ) );

		$response  = curl_exec( $curl );
		$error     = curl_error( $curl );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

		if ( $error ) {
			throw new Exception( $error );
		}

		if ( empty( $response ) ) {
			throw new Exception( sprintf( 'کد %s: پاسخی از درگاه پرداخت دریافت نشد.', $http_code ) );
		}

		try {

			return json_decode( $response, true, 512, JSON_THROW_ON_ERROR );

		} catch ( Exception $exception ) {

			error_log( print_r( [
				'Nabik - Invalid response json',
				get_called_class(),
				$url,
				$response,
			], true ) );

			throw new Exception( sprintf( 'کد %s: پاسخ دریافت شده معتبر نمی‌باشد.', $http_code ) );
		}
	}

	public function slug(): string {
		return str_replace(
			[
				__NAMESPACE__,
				'Nabik\GatelandPro\Gateways',
				'/',
				'\\',
				'Gateway',
			],
			'',
			get_called_class()
		);
	}

	public function event( string $event ): string {
		return $this->slug() . '::' . $event;
	}

	public function log( Transaction $transaction, string $event, ?array $data = null ): void {

		$transaction->logs()->create( [
			'event' => $this->event( $event ),
			'data'  => $data,
		] );

	}

	/**
	 * @throws Exception
	 */
	public function checkAmount( Transaction $transaction, int $from, int $to ): void {

		if ( $transaction->amount < $from || $transaction->amount > $to ) {

			throw new Exception( sprintf( 'مبلغ تراکنش باید بین %s تا %s %s باشد.',
				number_format( $from ),
				number_format( $to ),
				\Nabik\Gateland\Enums\Transaction\CurrenciesEnum::tryFrom( $transaction->currency )->symbol()
			) );

		}

	}
}