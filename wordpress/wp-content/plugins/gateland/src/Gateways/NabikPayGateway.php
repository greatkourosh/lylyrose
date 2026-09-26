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

class NabikPayGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature {

	protected string $name = 'نابیک‌پی';

	protected string $description = 'اتصال به نابیک‌پی';

	protected string $url = 'https://l.nabik.net/nabik-pay';

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'amount'      => intval( $transaction->amount ),
			'currency'    => 'IRT',
			'merchant'    => $this->options['merchant'],
			'order_id'    => $transaction->id,
			'callback'    => $transaction->gateway_callback,
			'description' => $transaction->description ?? '',
		];

		if ( $transaction->mobile ) {
			$parameters['mobile'] = $transaction->mobile;
		}

		if ( $transaction->allowed_cards ) {
			$parameters['allowed_cards'] = $transaction->allowed_cards;
		}

		if ( $transaction->national_code ) {
			$parameters['national_code'] = $transaction->national_code;
		}

		$url = trim( $this->options['url'], '/' );

		try {
			$response = $this->curl( $url . '/request', $parameters );
			$this->log( $transaction, 'paymentRequest', [
				'transaction' => $transaction->toArray(),
				'parameters'  => $parameters,
				'response'    => $response,
			] );
		} catch ( Exception $e ) {
			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'error'      => $e->getMessage(),
			] );

			throw new Exception( 'خطا در اتصال به درگاه! لطفا دوباره تلاش کنید.' );
		}

		if ( ! $response['success'] ) {
			throw new Exception( $response['message'] );
		}

		$transaction->update( [
			'gateway_au' => $response['data']['authority'],
		] );

		return;
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
			'merchant'  => $this->options['merchant'],
			'authority' => $transaction->gateway_au,
		];

		$url = trim( $this->options['url'], '/' );

		try {
			$response = $this->curl( $url . '/verify', $parameters );

			$this->log( $transaction, 'verifyRequest', [
				'transaction' => $transaction->toArray(),
				'parameters'  => $parameters,
				'response'    => $response,
			] );
		} catch ( Exception $e ) {
			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'error'      => $e->getMessage(),
			] );

			throw new VerifyException();
		}

		$is_paid = $response['data']['status'] == 'paid';

		if ( $is_paid ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $response['data']['trans_id'],
				'gateway_status'   => $response['data']['gateway_status'],
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $response['data']['card_number'],
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $response['data']['status'] );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		$url = trim( $this->options['url'], '/' );

		return wp_redirect( sprintf( '%s/pay/%s', $url, $transaction->gateway_au ) );
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
				'label'       => 'آدرس نابیک‌پی',
				'key'         => 'url',
				'description' => 'برای اتصال به یک نابیک‌پی دیگر، آدرس آن را وارد کنید.',
			],
			[
				'label'       => 'کلید مرچنت',
				'key'         => 'merchant',
				'description' => 'کلید مرچنت را داخل نابیک‌پی مقصد، از منو پذیرنده‌ها ایجاد کنید.',
			],
		];
	}
}
