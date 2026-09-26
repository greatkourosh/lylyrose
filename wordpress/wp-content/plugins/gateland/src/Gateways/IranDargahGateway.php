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

class IranDargahGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature {

	protected string $name = 'ایران درگاه';

	protected string $description = 'irandargah.com';

	protected string $url = 'https://l.nabik.net/irandargah';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'amount'         => intval( $transaction->amount * 10 ),
			'order_id'       => $transaction->id,
			'callback_url'   => $transaction->gateway_callback,
			'description'    => $transaction->description,
			'action'         => 'GET',
			'affiliate_code' => 'GP0FWZL5',
			'direct_verify'  => true,
		];

		if ( $transaction->mobile ) {
			$parameters['mobile'] = $transaction->mobile;
		}

		if ( $transaction->allowed_cards ) {
			$parameters['card_number'] = $transaction->allowed_cards[0];
		}

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $this->options['token'],
			'Idempotency-Key: ' . $transaction->id,
		];

		try {

			$response = $this->curl( 'https://ipg.irandargah.com/v2/payments', $parameters, $headers );

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

		if ( isset( $response['data']['transaction']['authority'] ) ) {

			$transaction->update( [
				'gateway_au' => $response['data']['transaction']['authority'],
			] );

			return;
		}

		if ( isset( $response['message'], $response['status_code'] ) ) {
			throw new Exception( sprintf( 'خطا %s: %s', $response['status_code'], $response['message'] ) );
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
			'authority' => $transaction->gateway_au,
			'amount'    => intval( $transaction->amount * 10 ),
			'order_id'  => $transaction->id,
		];

		$headers = [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $this->options['token'],
			'Idempotency-Key: verify-' . $transaction->gateway_au,
		];

		try {

			$response = $this->curl( 'https://ipg.irandargah.com/v2/verifications', $parameters, $headers );

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
			201, // Success - First verify
			100, // Success - Duplicate Verify
		];

		$is_paid = in_array( $response['status_code'], $paid_statuses );

		if ( $is_paid ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $response['data']['verification']['ref_id'],
				'gateway_status'   => $response['status_code'],
				'status'           => StatusesEnum::STATUS_PAID,
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $response['status'] ?? '' );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		return wp_redirect( 'https://ipg.irandargah.com/startpay/' . $transaction->gateway_au );
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'توکن',
				'key'         => 'token',
				'description' => 'توکن با عبارت idg_live_ شروع می شود.',
			],
		];
	}
}