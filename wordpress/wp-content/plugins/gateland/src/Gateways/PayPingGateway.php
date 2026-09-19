<?php


namespace Nabik\Gateland\Gateways;


use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class PayPingGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, ShaparakFeature {

	protected string $name = 'پی‌پینگ';

	protected string $description = 'payping.ir';

	protected string $url = 'https://l.nabik.net/payping';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$this->checkAmount( $transaction, 1000, 200000000 );

		$parameters = [
			'amount'      => intval( $transaction->amount ),
			'description' => $transaction->description,
			'returnUrl'   => $transaction->gateway_callback,
			'clientRefId' => strval( $transaction->id ),
		];

		if ( $transaction->mobile ) {
			$parameters['payerIdentity'] = $transaction->mobile;
		}

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $this->options['token'],
		];

		try {

			$url      = 'https://api.payping.ir/v3/pay';
			$response = $this->curl( $url, json_encode( $parameters ), $headers, [ CURLOPT_TIMEOUT => 10 ] );

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

		if ( isset( $response['paymentCode'] ) ) {

			$transaction->update( [
				'gateway_au' => $response['paymentCode'],
			] );

			return;
		}

		$errors = array_merge( ...$response['metaData']['errors'] );

		if ( isset( $errors['message'] ) ) {
			throw new Exception( 'خطا: ' . $errors['message'] );
		}

		throw new Exception( 'خطا در اتصال به درگاه! لطفا دوباره تلاش کنید.' );
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

		$data         = stripslashes( $_POST['data'] ?? '[]' );
		$data         = json_decode( $data, true );
		$paymentRefId = intval( $data['paymentRefId'] ?? 0 );

		if ( $paymentRefId == 0 ) {
			return $this->cancelled( $transaction, $paymentRefId );
		}

		$parameters = [
			'PaymentRefId' => $paymentRefId,
			'PaymentCode'  => $transaction->gateway_au,
			'Amount'       => intval( $transaction->amount ),
		];

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $this->options['token'],
		];

		try {

			$url      = 'https://api.payping.ir/v3/pay/verify';
			$response = $this->curl( $url, json_encode( $parameters ), $headers, [ CURLOPT_TIMEOUT => 10 ] );

			$this->log( $transaction, 'verifyRequest', [
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

			throw new VerifyException();
		}

		$cardNumber = $response['cardNumber'] ?? '';

		if ( empty( $cardNumber ) ) {
			$cardNumber = $response['metaData']['message']['CardNumber'] ?? '';
		}

		if ( empty( $cardNumber ) ) {
			throw new VerifyException();
		}

		$this->log( $transaction, 'verifySuccess' );

		$transaction->update( [
			'gateway_trans_id' => $paymentRefId,
			'gateway_status'   => 200,
			'status'           => StatusesEnum::STATUS_PAID,
			'card_number'      => $cardNumber,
			'paid_at'          => \Carbon\Carbon::now(),
		] );

		return true;
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		return wp_redirect( sprintf( 'https://api.payping.ir/v3/pay/start/%s', $transaction->gateway_au ) );
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'توکن درگاه پی‌پینگ',
				'key'         => 'token',
				'description' => 'توکن را از آدرس https://app.payping.ir دریافت کنید.',
			],
		];
	}
}