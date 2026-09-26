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

class PayStarGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature {

	protected string $name = 'پی‌استار';

	protected string $description = 'paystar.ir';

	protected string $url = 'https://l.nabik.net/paystar';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$sign = sprintf(
			'%s#%s#%s',
			intval( $transaction->amount * 10 ),
			$transaction->id,
			$transaction->gateway_callback
		);

		$parameters = [
			'amount'          => intval( $transaction->amount * 10 ),
			'order_id'        => $transaction->id,
			'callback'        => $transaction->gateway_callback,
			'sign'            => hash_hmac( 'sha512', $sign, $this->options['encryption_key'] ),
			'description'     => $transaction->description,
			'callback_method' => 1,
			'referer_id'      => 'jkn5y',
		];

		if ( $transaction->mobile ) {
			$parameters['phone'] = str_replace( '+98', '0', $transaction->mobile );
		}

		if ( $transaction->national_code ) {
			$parameters['national_code'] = $transaction->national_code;
		}

		if ( $transaction->allowed_cards ) {
			$parameters['card_number'] = $transaction->allowed_cards[0];
		}

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->options['gateway_id'],
		];

		try {

			$url      = sprintf( 'https://core.paystar.%s/api/pardakht/create', $this->get_tld() );
			$response = $this->curl( $url, json_encode( $parameters ), $headers, [ CURLOPT_TIMEOUT => 10 ] );

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

		if ( $response['status'] == 1 ) {

			$transaction->update( [
				'gateway_au'       => $response['data']['token'],
				'gateway_trans_id' => $response['data']['ref_num'],
			] );

			return;
		}

		if ( isset( $response['message'] ) ) {
			throw new Exception( 'خطا ' . $response['status'] . ': ' . $response['message'] );
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

		$status = sanitize_text_field( $_GET['status'] ?? null );

		if ( $status == - 98 ) {
			return $this->cancelled( $transaction, $status );
		}

		$parameters = [
			'ref_num' => $transaction->gateway_trans_id,
			'amount'  => intval( $transaction->amount * 10 ),
		];

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->options['gateway_id'],
		];

		try {

			$url      = sprintf( 'https://core.paystar.%s/api/pardakht/verify', $this->get_tld() );
			$response = $this->curl( $url, json_encode( $parameters ), $headers, [ CURLOPT_TIMEOUT => 10 ] );

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
			- 6, // Success - Duplicate Verify
		];

		$is_paid = in_array( $response['status'], $paid_statuses );

		if ( $is_paid ) {
			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_status' => $response['status'],
				'status'         => StatusesEnum::STATUS_PAID,
				'card_number'    => $response['data']['card_number'] ?? null,
				'paid_at'        => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( sprintf( 'خطا %s در تایید تراکنش رخ داده است.', $response['status'] ) );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		return wp_redirect(
			sprintf( 'https://core.paystar.%s/api/pardakht/payment/?token=%s', $this->get_tld(),
				$transaction->gateway_au )
		);
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'شناسه درگاه',
				'key'         => 'gateway_id',
				'type'        => 'text',
				'description' => 'شناسه درگاه را از آدرس https://my.paystar.ir دریافت کنید.',
			],
			[
				'label' => 'کلید رمزنگاری',
				'key'   => 'encryption_key',
				'type'  => 'textarea',
			],
			[
				'label'       => 'هاست خارج از ایران',
				'key'         => 'non-iran-host',
				'type'        => 'checkbox',
				'description' => 'در صورتی که هاست میزبانی شما خارج از ایران است، جهت اتصال بهتر تیک بزنید.',
			],
		];
	}

	private function get_tld(): string {
		return ( $this->options['non-iran-host'] ?? false ) ? 'click' : 'ir';
	}
}