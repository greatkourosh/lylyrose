<?php


namespace Nabik\Gateland\Gateways;

use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\MatchCardWithMobile;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class JibitGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature, MatchCardWithMobile {

	protected string $name = 'جیبیت';

	protected string $description = 'jibit.ir';

	protected string $url = 'https://l.nabik.net/jibit';

	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 * @throws Exception
	 */
	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'amount'                => intval( $transaction->amount * 10 ),
			"currency"              => "IRR",
			'clientReferenceNumber' => $transaction->id,
			'description'           => $transaction->description ?? '',
			'callbackUrl'           => $transaction->gateway_callback,
		];

		if ( $transaction->mobile ) {
			$parameters['payerMobileNumber'] = str_replace( '+98', '0', $transaction->mobile );

			if ( $this->options['matchCardWithMobile'] ) {
				$parameters['checkPayerMobileNumber'] = true;
			}
		}

		if ( $transaction->allowed_cards ) {
			$parameters['payerCardNumbers'] = $transaction->allowed_cards;
		}

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->getToken( $transaction ),
		];

		try {

			$url      = 'https://napi.jibit.ir/ppg/v3/purchases';
			$response = $this->curl( $url, json_encode( $parameters ), $headers );

			$this->log( $transaction, 'paymentRequest', [
				'parameters' => $parameters,
				'headers'    => $headers,
				'response'   => $response,
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'headers'    => $headers,
				'error'      => $e->getMessage(),
			] );

			throw new Exception( 'خطا در اتصال به درگاه! لطفا دوباره تلاش کنید.' );
		}

		if ( isset( $response['purchaseIdStr'] ) ) {

			$transaction->update( [
				'gateway_au' => $response['purchaseIdStr'],
			] );

			return;
		}

		if ( isset( $response['errors'][0]['message'] ) ) {
			throw new Exception( $response['errors'][0]['message'] );
		}

		throw new Exception( 'خطا در ایجاد تراکنش! لطفا دوباره تلاش کنید.' );
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool
	 * @throws InquiryException
	 * @throws VerifyException
	 */
	public function inquiry( Transaction $transaction ): bool {
		$this->log( $transaction, 'inquiry', [
			'transaction' => $transaction->toArray(),
		] );

		try {

			$headers = [
				'Authorization: Bearer ' . $this->getToken( $transaction ),
			];

		} catch ( Exception $e ) {
			throw new VerifyException( $e->getMessage() );
		}

		try {
			$url      = sprintf( 'https://napi.jibit.ir/ppg/v3/purchases/%s/verify', $transaction->gateway_au );
			$response = $this->curl( $url, [], $headers );

			$this->log( $transaction, 'verifyRequest', [
				'url'      => $url,
				'headers'  => $headers,
				'response' => $response,
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'url'     => $url,
				'headers' => $headers,
				'error'   => $e->getMessage(),
			] );

			throw new VerifyException();
		}

		$paid_statuses = [
			'SUCCESSFUL', // Success - First verify
			'ALREADY_VERIFIED', // Success - Duplicate Verify
		];

		$is_paid = in_array( $response['status'], $paid_statuses );

		if ( $is_paid ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_status' => $response['status'],
				'status'         => StatusesEnum::STATUS_PAID,
				'card_number'    => null,
				'paid_at'        => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $response['status'] );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		$url = sprintf( 'https://napi.jibit.ir/ppg/v3/purchases/%s/payments', $transaction->gateway_au );

		return wp_redirect( $url );
	}

	/**
	 * @return CurrenciesEnum[]
	 */
	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label' => 'کلید API',
				'key'   => 'apiKey',
			],
			[
				'label' => 'کلید Secret',
				'key'   => 'secretKey',
			],
			[
				'label'       => 'تطبیق موبایل با کارت',
				'key'         => 'matchCardWithMobile',
				'type'        => 'checkbox',
				'description' => 'در صورت تمایل به تطبیق مالک شماره موبایل و مالک شماره کارت تیک بزنید.',
			],
		];
	}

	/**
	 * @throws Exception
	 */
	public function getToken( Transaction $transaction ): string {
		$parameters = [
			'username' => $this->options['apiKey'],
			'password' => $this->options['secretKey'],
		];

		try {

			$url      = 'https://napi.jibit.ir/ppg/v3/tokens';
			$response = $this->curl( $url, $parameters );

			$this->log( $transaction, 'TokenRequest', [
				'parameters' => $parameters,
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'error'      => $e->getMessage(),
			] );

			throw new Exception( 'خطا در دریافت اکسس توکن! لطفا دوباره تلاش کنید.' );
		}

		if ( isset( $response['accessToken'] ) ) {
			return $response['accessToken'];
		}

		if ( isset( $response['errors'][0]['message'] ) ) {
			throw new Exception( $response['errors'][0]['message'] );
		}

		throw new Exception( 'اکسس توکن صادر نشده است.' );
	}
}
