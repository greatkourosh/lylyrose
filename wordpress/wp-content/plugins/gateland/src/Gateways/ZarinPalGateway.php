<?php


namespace Nabik\Gateland\Gateways;


use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\RefundFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class ZarinPalGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, RefundFeature, ShaparakFeature {

	protected string $name = 'زرین‌پال';

	protected string $description = 'zarinpal.com';

	protected string $url = 'https://l.nabik.net/zarinpal';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$this->checkAmount( $transaction, 1_100, 400_000_000 );

		$parameters = [
			'merchant_id'  => $this->options['merchant_id'],
			'amount'       => intval( $transaction->amount ),
			'currency'     => 'IRT',
			'description'  => $transaction->description,
			'callback_url' => $transaction->gateway_callback,
			'referrer_id'  => '0MXGrZ',
			'metadata'     => [
				'order_id' => strval( $transaction->id ),
			],
		];

		if ( $transaction->mobile ) {
			$parameters['metadata']['mobile'] = str_replace( '+98', '0', $transaction->mobile );
		}

		$headers = [
			'Content-Type: application/json',
		];

		try {

			$url      = 'https://api.zarinpal.com/pg/v4/payment/request.json';
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

		if ( isset( $response['data']['authority'] ) ) {

			$transaction->update( [
				'gateway_au' => $response['data']['authority'],
			] );

			return;
		}

		if ( isset( $response['errors']['message'] ) ) {
			throw new Exception( $response['errors']['message'] );
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
			'merchant_id' => $this->options['merchant_id'],
			'authority'   => $transaction->gateway_au,
			'amount'      => intval( $transaction->amount ),
		];

		$headers = [
			'Content-Type: application/json',
		];

		try {
			$url      = 'https://api.zarinpal.com/pg/v4/payment/verify.json';
			$response = $this->curl( $url, json_encode( $parameters ), $headers );

			$this->log( $transaction, 'verifyRequest', [
				'parameters' => $parameters,
				'headers'    => $headers,
				'response'   => $response,
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'error'      => $e->getMessage(),
			] );

			throw new VerifyException();
		}

		$paid_statuses = [
			100, // Success - First verify
			101, // Success - Duplicate Verify
		];

		$is_paid = in_array( $response['data']['code'] ?? 0, $paid_statuses );

		if ( $is_paid ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $response['data']['ref_id'],
				'gateway_status'   => 1,
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $response['data']['card_pan'],
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $response['errors']['code'] );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		$url = "https://www.zarinpal.com/pg/StartPay/%s/";

		return wp_redirect( sprintf( $url, $transaction->gateway_au ) );
	}

	public function refund( Transaction $transaction, int $amount, string $description ) {
		$this->log( $transaction, 'refund', [
			'transaction' => $transaction->toArray(),
			'amount'      => $amount,
			'description' => $description,
		] );

		if ( $transaction->amount != $amount ) {
			throw new Exception( 'زرین‌پال صرفا از عودت وجه مبلغ کامل تراکنش پشتیبانی می‌کند.' );
		}

		$parameters = [
			'merchant_id' => $this->options['merchant_id'],
			'authority'   => $transaction->gateway_au,
		];

		$headers = [
			'Content-Type: application/json',
		];

		try {
			$url      = 'https://payment.zarinpal.com/pg/v4/payment/reverse.json';
			$response = $this->curl( $url, json_encode( $parameters ), $headers );

			$this->log( $transaction, 'refundRequest', [
				'parameters' => $parameters,
				'headers'    => $headers,
				'response'   => $response,
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'error'      => $e->getMessage(),
			] );

			throw new Exception( 'خطایی در زمان اتصال به درگاه زرین‌پال رخ داده است.' );
		}

		if ( isset( $response['errors']['message'] ) ) {
			throw new Exception( $response['errors']['message'] );
		}

		$this->log( $transaction, 'refundSuccess' );

		return $transaction->gateway_trans_id;
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label' => 'کلید پذیرنده',
				'key'   => 'merchant_id',
			],
		];
	}
}