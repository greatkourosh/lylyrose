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

class ShepaGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature {

	protected string $name = 'شپا';

	protected string $description = 'shepa.com';

	protected string $url = 'https://l.nabik.net/shepa';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'api'         => $this->options['api'],
			'amount'      => intval( $transaction->amount * 10 ),
			'callback'    => $transaction->gateway_callback,
			'description' => $transaction->description,
		];

		if ( $transaction->mobile ) {
			$parameters['mobile'] = str_replace( '+98', '0', $transaction->mobile );
		}

		if ( $transaction->email ) {
			$parameters['email'] = $transaction->email;
		}

		if ( $transaction->allowed_cards ) {
			$parameters['cardnumber'] = $transaction->allowed_cards[0];
		}

		$headers = [
			'Content-Type: application/json',
		];

		try {

			$url      = 'https://merchant.shepa.com/api/v1/token';
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

		if ( $response['success'] ) {

			$transaction->update( [
				'gateway_au' => $response['result']['token'],
				'meta'       => [
					'payment_url' => $response['result']['url'],
				],
			] );

			return;
		}

		throw new Exception( 'خطا در دریافت توکن! لطفا دوباره تلاش کنید.' );
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
			'api'    => $this->options['api'],
			'amount' => intval( $transaction->amount * 10 ),
			'token'  => $transaction->gateway_au,
		];

		$headers = [
			'Content-Type: application/json',
		];

		try {

			$url      = 'https://merchant.shepa.com/api/v1/verify';
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

		if ( $response['success'] ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $response['result']['transaction_id'],
				'gateway_status'   => $response['result'],
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $response['result']['card_pan'],
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

		return wp_redirect( $transaction->meta['payment_url'] );
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'کلید api',
				'key'         => 'api',
				'description' => 'این کلید ۳۶ کاراکتری را از my.shepa.com دریافت کنید.',
			],
		];
	}
}