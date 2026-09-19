<?php


namespace Nabik\Gateland\Gateways;

use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\CardToCardFeature;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Models\Transaction;

class NeoGateGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, CardToCardFeature {

	protected string $name = 'نئوگیت';

	protected string $description = 'neogate.ir - تایید خودکار کارت به کارت';

	protected string $url = 'https://l.nabik.net/neogate';

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
			'amount'          => intval( $transaction->amount * 10 ),
			'merchant'        => $this->options['merchant'],
			'order_id'        => $transaction->id,
			'mobile'          => $transaction->mobile,
			'callback'        => $transaction->gateway_callback,
			'description'     => $transaction->description,
			'expiration_time' => $this->options['expiration_time'] ?? null,
		];

		$headers = [
			'Accept: application/json',
		];

		try {

			$url      = 'https://app.neogate.ir/api/v1/payment/request';
			$response = $this->curl( $url, $parameters, $headers );

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

		if ( isset( $response['data']['token'] ) ) {

			$transaction->update( [
				'gateway_au' => $response['data']['token'],
			] );

			return;
		}

		throw new Exception( $response['message'] );
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
			'merchant' => $this->options['merchant'],
			'token'    => $transaction->gateway_au,
		];

		$headers = [
			'Accept: application/json',
		];

		try {
			$url      = 'https://app.neogate.ir/api/v1/payment/verify';
			$response = $this->curl( $url, $parameters, $headers );

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

		$status = $response['data']['status'];

		if ( $status == 'pending' ) {
			throw new VerifyException( 'تراکنش کارت به کارت تعیین وضعیت نشده است.' );
		}

		if ( $status == 'paid' ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $response['data']['ref_number'],
				'gateway_status'   => $status,
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $response['data']['card_number'],
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $status );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		$url = sprintf( 'https://app.neogate.ir/pay/%s', $transaction->gateway_au );

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
				'label'       => 'مرچنت',
				'key'         => 'merchant',
				'description' => 'مرچنت از منو پذیرنده ها در app.neogate.ir قابل دریافت است.',
			],
			[
				'label'       => 'مهلت کارت به کارت (دقیقه)',
				'key'         => 'expiration_time',
				'description' => 'پیشفرض ۳۰ دقیقه است.',
			],
		];
	}
}
