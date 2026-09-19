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

class SizPayGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature, MatchCardWithMobile {

	protected string $name = 'سیزپی';

	protected string $description = 'sizpay.ir';

	protected string $url = 'https://l.nabik.net/sizpay';

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

		$this->checkAmount( $transaction, 1_000, 50_000_000 );

		$parameters = [
			'sizPayKey'              => $this->options['key'],
			'Amount'                 => intval( $transaction->amount * 10 ),
			'ReturnURL'              => $transaction->gateway_callback,
			'OrderID'                => $transaction->id,
			'InvoiceNo'              => $transaction->id,
			'Nabik\GatelandExtraInf' => [
				'Descr' => $transaction->description,
			],
		];

		if ( $transaction->mobile ) {
			$parameters['Nabik\GatelandExtraInf']['PayerMobile'] = $transaction->mobile;
		}

		$headers = [
			'Content-Type: application/json',
		];

		try {

			$url      = 'https://rt.sizpay.ir/api/PaymentSimple/GetTokenSimple';
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

		if ( in_array( $response['ResCod'], [ '0', '00' ] ) ) {

			$transaction->update( [
				'gateway_au' => $response['Token'],
			] );

			return;
		}

		throw new Exception( $response['Message'] );
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
			'sizPayKey' => $this->options['key'],
			'Token'     => $transaction->gateway_au,
		];

		$headers = [
			'Content-Type: application/json',
		];

		try {
			$url      = 'https://rt.sizpay.ir/api/PaymentSimple/ConfirmSimple';
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

		if ( in_array( $response['ResCod'], [ '0', '00' ] ) ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $response['RefNo'],
				'gateway_status'   => $response['ResCod'],
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $response['CardNo'],
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $response['ResCod'] );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		$url = sprintf( 'https://rt.sizpay.ir/Route/Payment/?token=%s', $transaction->gateway_au );

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
				'label'       => 'کلید سیزپی',
				'key'         => 'key',
				'description' => 'کلید اصلی سیزپی را وارد نمایید.',
			],
		];
	}
}
