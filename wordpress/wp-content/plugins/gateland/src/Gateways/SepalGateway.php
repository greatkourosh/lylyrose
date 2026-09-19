<?php


namespace Nabik\Gateland\Gateways;


use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class SepalGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature {

	protected string $name = 'سپال';

	protected string $description = 'sepal.ir';

	protected string $url = 'https://l.nabik.net/sepal';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'apiKey'        => $this->options['api_key'],
			'invoiceNumber' => $transaction->id,
			'amount'        => intval( $transaction->amount * 10 ),
			'callbackUrl'   => $transaction->gateway_callback,
			'description'   => $transaction->description,
			'affiliateCode' => 'S10757',
		];

		if ( $transaction->mobile ) {
			$parameters['payerMobile'] = str_replace( '+98', '0', $transaction->mobile );
		}

		$headers = [
			'Content-Type: application/json',
		];

		try {

			// CURLOPT_SSL_VERIFYPEER false
			$url      = sprintf( '%s/api/request.json', $this->get_domain() );
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

		if ( isset( $response['paymentNumber'] ) && $response['paymentNumber'] ) {

			$transaction->update( [
				'gateway_au' => strval( $response['paymentNumber'] ),
			] );

			return;
		}

		if ( isset( $response['message'] ) && $response['message'] ) {
			throw new Exception( 'خطا: ' . $response['message'] );
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

		$parameters = [
			'apiKey'        => $this->options['api_key'],
			'paymentNumber' => strval( $transaction->gateway_au ),
		];

		$headers = [
			'Content-Type: application/json',
		];

		try {

			$url      = sprintf( '%s/api/verify.json', $this->get_domain() );
			$response = $this->curl( $url, json_encode( $parameters ), $headers );

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

		if ( $response['status'] == 1 ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_status' => $response['status'],
				'status'         => StatusesEnum::STATUS_PAID,
				'card_number'    => $response['cardNumber'],
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

		return wp_redirect( sprintf( '%s/payment/%s', $this->get_domain(), $transaction->gateway_au ) );
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label' => 'کلید وب سرویس',
				'key'   => 'api_key',
			],
			[
				'label'       => 'هاست خارج از ایران',
				'key'         => 'non-iran-host',
				'type'        => 'checkbox',
				'description' => 'در صورتی که هاست میزبانی شما خارج از ایران است، جهت اتصال بهتر تیک بزنید.',
			],
		];
	}

	private function get_domain(): string {
		return ( $this->options['non-iran-host'] ?? false ) ? 'https://3pal.ir' : 'https://sepal.ir';
	}
}