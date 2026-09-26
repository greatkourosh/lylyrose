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

class PasargadGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature {

	protected string $name = 'بانک پاسارگاد';

	protected string $description = 'پرداخت الکترونیک پاسارگاد - pep';

	protected string $url = 'https://l.nabik.net/pep';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'invoice'        => strval( $transaction->id ),
			'invoiceDate'    => $transaction->created_at->format( 'Y-m-d' ),
			'amount'         => intval( $transaction->amount * 10 ),
			'callbackApi'    => $transaction->gateway_callback,
			'serviceCode'    => 8,
			'serviceType'    => 'PURCHASE',
			'terminalNumber' => $this->options['terminal'],
			'description'    => $transaction->description,
		];

		if ( preg_match( '/^09\d{9}$/', $transaction->mobile ) ) {
			$parameters['mobileNumber'] = $transaction->mobile;
		}

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->getToken( $transaction ),
		];

		try {

			$url      = $this->getBaseURL() . '/api/payment/purchase';
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

		if ( $response['resultCode'] == 0 ) {

			$transaction->update( [
				'gateway_au' => $response['data']['urlId'],
			] );

			return;
		}

		throw new Exception( $response['resultMsg'] );
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
			'invoice' => strval( $transaction->id ),
			'urlId'   => $transaction->gateway_au,
		];

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->getToken( $transaction ),
		];

		try {

			$url      = $this->getBaseURL() . '/api/payment/confirm-transactions';
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
			0,     // Success - First verify
			13029, // Success - Duplicate Verify
			13046, // Success - Already settled
		];

		$is_paid = in_array( $response['resultCode'], $paid_statuses );

		if ( $is_paid ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $response['data']['referenceNumber'] ?? null,
				'gateway_status'   => $response['resultCode'],
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $response['data']['maskedCardNumber'] ?? null,
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $response['resultCode'] );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		return wp_redirect( sprintf( $this->getBaseURL() . '/%s', $transaction->gateway_au ) );
	}

	public function getBaseURL(): string {
		return $this->options['base_url'] ?? 'https://pep.shaparak.ir/dorsa2';
	}


	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label' => 'نام کاربری',
				'key'   => 'username',
			],
			[
				'label' => 'کلمه عبور',
				'key'   => 'password',
			],
			[
				'label' => 'شماره ترمینال',
				'key'   => 'terminal',
			],
			[
				'label'   => 'آدرس پایه',
				'key'     => 'base_url',
				'type'    => 'select',
				'options' => [
					'https://pep.shaparak.ir/dorsa2' => 'https://pep.shaparak.ir/dorsa2',
					'https://pep.shaparak.ir/dorsa1' => 'https://pep.shaparak.ir/dorsa1',
				],
			],

		];
	}

	/**
	 * @throws Exception
	 */
	public function getToken( Transaction $transaction ) {
		$parameters = [
			'username' => $this->options['username'],
			'password' => $this->options['password'],
		];

		$headers = [
			'Content-Type: application/json',
		];

		try {

			$url      = $this->getBaseURL() . '/token/getToken';
			$response = $this->curl( $url, json_encode( $parameters ), $headers );

			$this->log( $transaction, 'tokenRequest', [
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

			throw new Exception( 'خطا در زمان دریافت توکن! لطفا دوباره تلاش کنید.' );
		}

		if ( isset( $response['token'] ) ) {
			return $response['token'];
		}

		throw new Exception( 'توکن دریافت شده معتبر نمی‌باشد.' );
	}

	/**
	 * @return string
	 */
	public function icon(): string {
		return GATELAND_URL . ( 'assets/images/gateways/Pasargad.png' );
	}
}