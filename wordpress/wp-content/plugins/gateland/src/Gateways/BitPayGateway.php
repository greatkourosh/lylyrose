<?php


namespace Nabik\Gateland\Gateways;


use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class BitPayGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, ShaparakFeature {

	protected string $name = 'بیت‌پی';

	protected string $description = 'bitpay.ir';

	protected string $url = 'https://l.nabik.net/bitpay';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'api'         => $this->options['api'],
			'amount'      => intval( $transaction->amount * 10 ),
			'redirect'    => urlencode( $transaction->gateway_callback ),
			'description' => $transaction->description,
			'factorId'    => $transaction->id,

		];

		try {

			$url      = 'https://bitpay.ir/payment/gateway-send';
			$response = $this->BitPayCurl( $url, $parameters );

			$this->log( $transaction, 'paymentRequest', [
				'parameters' => $parameters,
				'response'   => $response,
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'error'      => $e->getMessage(),
			] );

			throw new Exception( 'خطا در اتصال به درگاه! لطفا دوباره تلاش کنید.' );
		}

		if ( $response > 0 ) {

			$transaction->update( [
				'gateway_au' => $response,
			] );

			return;
		}

		throw new Exception( $this->messages( $response ) );
	}

	public function inquiry( Transaction $transaction ): bool {
		$this->log( $transaction, 'inquiry', [
			'transaction' => $transaction->toArray(),
		] );

		$trans_id = filter_input( INPUT_GET, 'trans_id', FILTER_SANITIZE_NUMBER_INT );

		if ( empty( $trans_id ) ) {
			return $this->cancelled( $transaction );
		}

		$parameters = [
			'trans_id' => $trans_id,
			'id_get'   => $transaction->gateway_au,
			'api'      => $this->options['api'],
			'json'     => 1,
		];

		try {

			$url      = 'https://bitpay.ir/payment/gateway-result-second';
			$response = $this->curl( $url, $parameters );

			$this->log( $transaction, 'verifyRequest', [
				'parameters' => $parameters,
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
			1, // Success - First verify
			11, // Success - Duplicate Verify
		];

		$is_paid = in_array( $response['status'], $paid_statuses );

		if ( $is_paid ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $trans_id,
				'gateway_status'   => $response['status'],
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $response['cardNum'],
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $response['status'] );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		return wp_redirect( sprintf( 'https://bitpay.ir/payment/gateway-%s-get', $transaction->gateway_au ) );
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'API',
				'key'         => 'api',
				'description' => 'کلید API را از آدرس https://bitpay.ir/user/manageGateway دریافت کنید.',
			],
		];
	}

	public function messages( $errorCode ): string {
		$messages = [
			- 1 => 'تنظیمات درگاه بیت‌پی به درستی انجام نشده است.',
			- 2 => 'مبلغ داده عددی نمی‌باشد یا کمتر از ۱۰۰۰ ریال است.',
			- 3 => 'آدرس بازگشت از درگاه معتبر نمی‌باشد.',
			- 4 => 'درگاه یافت نشد یا در انتظار تایید است.',
			- 5 => 'خطا در اتصال به درگاه، لطفا مجددا بررسی کنید.',
		];

		return $messages[ $errorCode ] ?? 'خطا غیرمنتظره! لطفا با مدیر وب سایت تماس بگیرید.';
	}

	/**
	 * @throws Exception
	 */
	public function BitPayCurl( string $url, array $data ): int {
		$curl = curl_init( $url );

		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 8 );


		$response = curl_exec( $curl );

		$error = curl_error( $curl );

		if ( $error ) {
			throw new Exception( $error );
		}

		if ( empty( $response ) ) {
			throw new Exception( 'پاسخی از درگاه پرداخت دریافت نشد.' );
		}

		return $response;
	}
}