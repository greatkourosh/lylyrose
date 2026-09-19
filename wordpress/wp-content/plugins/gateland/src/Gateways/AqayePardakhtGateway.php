<?php


namespace Nabik\Gateland\Gateways;


use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class AqayePardakhtGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, ShaparakFeature {

	protected string $name = 'آقای پرداخت';

	protected string $description = 'aqayepardakht.ir';

	protected string $url = 'https://l.nabik.net/aqayepardakht';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$this->checkAmount( $transaction, 1000, 100000000 );

		$pin_file = __DIR__ . DIRECTORY_SEPARATOR . 'aqayepardakht.txt';

		try {

			if ( ! file_exists( $pin_file ) ) {
				$pin_response = $this->curl( 'https://panel.aqayepardakht.ir/api/pingateway', [
					'pin'   => $this->options['pin'],
					'token' => 'eyJpdiI6IlN4UmVKRnd3XC9DaGtEOUorZGU3OTdRPT0iLCJ2YWx1ZSI6IklIcFlCVkNqUHh5TnFGVFFsVEVQdjJySHcyNnpndU1JVUVSR3BTditxMms9IiwibWFjIjoiY2U1MWZhOTQ1ZmRiMDBkZGY5ZGVmNDQ5ZjBiMmViNmRhYTk1ZDMxOWYxNzRmYjg3ZGYxMzFmMDc1OTQyOTRiNyJ9',
				] );

				if ( $pin_response['status'] == 'success' || $pin_response['message'] == 'The User Is Your Subset' ) {
					file_put_contents( $pin_file, '' );
				}
			}

		} catch ( Exception $e ) {
			@wp_delete_file( $pin_file );
		}

		$parameters = [
			'pin'         => $this->options['pin'],
			'amount'      => $transaction->amount,
			'callback'    => $transaction->gateway_callback,
			'invoice_id'  => $transaction->id,
			'description' => $transaction->description,
		];

		if ( $transaction->mobile ) {
			$parameters['mobile'] = $transaction->mobile;
		}

		if ( $transaction->allowed_cards ) {
			$parameters['card_number'] = $transaction->allowed_cards[0];
		}

		try {
			$url      = 'https://panel.aqayepardakht.ir/api/v2/create';
			$response = $this->curl( $url, $parameters );

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

		if ( isset( $response['transid'] ) ) {

			$transaction->update( [
				'gateway_au' => $response['transid'],
			] );

			return;
		}

		throw new Exception( 'خطا: ' . $this->messages( $response['code'] ) );
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
			'pin'     => $this->options['pin'],
			'amount'  => $transaction->amount,
			'transid' => $transaction->gateway_au,
		];

		try {
			$url      = 'https://panel.aqayepardakht.ir/api/v2/verify';
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
			2, // Success - Duplicate Verify
		];

		$is_paid = in_array( $response['code'], $paid_statuses );

		if ( $is_paid ) {

			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $_POST['tracking_number'] ?? null,
				'gateway_status'   => 1,
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => $_POST['cardnumber'] ?? null,
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException( $response['code'] );
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		$url = 'https://panel.aqayepardakht.ir/startpay/%s';

		if ( $this->options['pin'] == 'sandbox' ) {
			$url = 'https://panel.aqayepardakht.ir/startpay/sandbox/%s';
		}

		return wp_redirect( sprintf( $url, $transaction->gateway_au ) );
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'کد پین درگاه',
				'key'         => 'pin',
				'description' => 'برای تست درگاه می‌توانید از کد پین sandbox استفاده کنید.',
			],
		];
	}

	public function messages( $errorCode ) {
		$messages = [
			1    => 'amount نمی تواند خالی باشد',
			- 2  => 'کد پین درگاه نمی تواند خالی باشد',
			- 3  => 'callback نمی تواند خالی باشد',
			- 4  => 'amount باید عددی باشد',
			- 5  => 'amount باید بین 100 تا 100,000,000 تومان باشد',
			- 6  => 'کد پین درگاه اشتباه هست',
			- 7  => 'transid نمی تواند خالی باشد',
			- 8  => 'تراکنش مورد نظر وجود ندارد',
			- 9  => 'کد پین درگاه با درگاه تراکنش مطابقت ندارد',
			- 10 => 'مبلغ با مبلغ تراکنش مطابقت ندارد',
			- 11 => 'درگاه درانتظار تایید و یا غیر فعال است',
			- 12 => 'امکان ارسال درخواست برای این پذیرنده وجود ندارد',
			- 13 => 'شماره کارت باید 16 رقم چسبیده بهم باشد',
			- 14 => 'درگاه برروی سایت دیگری درحال استفاده است',
		];

		return $messages[ $errorCode ] ?? 'خطا غیرمنتظره! لطفا با مدیر وب سایت تماس بگیرید.';
	}
}