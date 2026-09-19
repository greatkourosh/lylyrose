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

class VandarGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature {

	protected string $name = 'وندار';

	protected string $description = 'vandar.io';

	protected string $url = 'https://l.nabik.net/vandar';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'api_key'      => $this->options['api_key'],
			'amount'       => intval( $transaction->amount * 10 ),
			'callback_url' => $transaction->gateway_callback,
			'factorNumber' => $transaction->id,
			'description'  => $transaction->description,
		];

		if ( $transaction->mobile ) {
			$parameters['mobile_number'] = str_replace( '+98', '0', $transaction->mobile );
		}

		if ( $transaction->national_code ) {
			$parameters['national_code'] = $transaction->national_code;
		}

		if ( $transaction->allowed_cards ) {
			$parameters['valid_card_number'] = $transaction->allowed_cards[0];
		}

		$port = $this->options['port'] ?? '';

		if ( in_array( $port, [ 'SAMAN', 'BEHPARDAKHT' ] ) ) {
			$parameters['port'] = $port;
		}

		$headers = [
			'Content-Type: application/json',
		];

		try {

			$url      = 'https://ipg.vandar.io/api/v3/send';
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

		if ( isset( $response['token'] ) ) {

			$transaction->update( [
				'gateway_au' => $response['token'],
			] );

			return;
		}

		if ( isset( $response['errors'] ) ) {
			throw new Exception( 'خطا: ' . implode( ' | ', $response['errors'] ) );
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
			'api_key' => $this->options['api_key'],
			'token'   => $transaction->gateway_au,
		];

		$headers = [
			'Content-Type: application/json',
		];

		try {

			$url      = 'https://ipg.vandar.io/api/v3/verify';
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

		$paid_statuses = [
			1, // Success - First verify
			2, // Success - Duplicate Verify
		];

		if ( in_array( $response['status'], $paid_statuses ) ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $response['transId'],
				'gateway_status'   => $response['status'],
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $response['cardNumber'],
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

		return wp_redirect( sprintf( 'https://ipg.vandar.io/v3/%s', $transaction->gateway_au ) );
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'کلید وب سرویس',
				'key'         => 'api_key',
				'description' => 'این کلید از طریق پشتیبانی وندار در اختیار شما قرار می‌گیرد.',
			],
			[
				'label'       => 'پورت',
				'type'        => 'select',
				'key'         => 'port',
				'placeholder' => 'پورت مورد نظر را انتخاب کنید.',
				'options'     => [
					''            => 'پیشفرض',
					'SAMAN'       => 'سامان',
					'BEHPARDAKHT' => 'به‌پرداخت',
				],
			],
		];
	}
}