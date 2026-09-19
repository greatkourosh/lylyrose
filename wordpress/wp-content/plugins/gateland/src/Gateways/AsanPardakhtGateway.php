<?php


namespace Nabik\Gateland\Gateways;

use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\MatchCardWithMobile;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class AsanPardakhtGateway extends BaseGateway implements \Nabik\Gateland\Gateways\Features\FreeFeature, InquiryFeature, ShaparakFeature, MatchCardWithMobile {

	protected string $name = 'آسان پرداخت';

	protected string $description = 'asanpardakht.ir';

	protected string $url = 'https://l.nabik.net/asanpardakht';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'merchantConfigurationId' => $this->options['merchant'],
			'serviceTypeId'           => 1,
			'localInvoiceId'          => $transaction->id,
			'amountInRials'           => intval( $transaction->amount * 10 ),
			'localDate'               => \Carbon\Carbon::now()->format( 'Ymd His' ),
			'additionalData'          => [],
			'callbackURL'             => $transaction->gateway_callback,
			'paymentId'               => 0,
			'settlementPortions'      => [],
		];

		if ( $transaction->mobile ) {
			$parameters['mobileNumber'] = str_replace( '+98', '0', $transaction->mobile );

			if ( $this->options['matchCardWithMobile'] ) {
				$parameters['additionalData']['MatchCardNumberMobileNumber'] = true;
			}
		}

		$parameters['additionalData'] = json_encode( $parameters['additionalData'] );

		try {

			$url      = 'https://ipgrest.asanpardakht.ir/v1/Token';
			$response = $this->AsanPardakhtCurl( $url, $parameters );


			$this->log( $transaction, 'paymentRequest', [
				'parameters' => $parameters,
				'headers'    => $this->getHeaders(),
				'response'   => $response,
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'headers'    => $this->getHeaders(),
				'error'      => $e->getMessage(),
			] );

			throw new Exception( $e->getMessage() );
		}

		if ( ! empty( $response ) ) {

			$transaction->update( [
				'gateway_au' => $response,
			] );

			return;
		}

		throw new Exception( 'خطا در اتصال به درگاه! لطفا دوباره تلاش کنید.' );
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool
	 * @throws VerifyException|InquiryException
	 */
	public function inquiry( Transaction $transaction ): bool {
		$this->log( $transaction, 'inquiry', [
			'transaction' => $transaction->toArray(),
		] );

		$parameters = [
			'merchantConfigurationId' => $this->options['merchant'],
			'LocalInvoiceId'          => $transaction->id,
		];

		try {
			$transResult = $this->getTransResult( $parameters );

			$this->log( $transaction, 'TransResultRequest', [
				'parameters' => $parameters,
				'headers'    => $this->getHeaders(),
				'response'   => $transResult,
			] );

			$payGateTranID = $transResult['payGateTranID'];

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'headers'    => $this->getHeaders(),
				'error'      => $e->getMessage(),
			] );

			if ( $e->getCode() != 571 ) {
				throw new InquiryException( $e->getCode() );
			}

			throw new VerifyException( $e->getMessage() );
		}

		$verifyParameters = [
			'merchantConfigurationId' => $this->options['merchant'],
			'payGateTranId'           => $payGateTranID,
		];

		try {
			$url = 'https://ipgrest.asanpardakht.ir/v1/Verify';
			$this->AsanPardakhtCurl( $url, $verifyParameters );

			$this->log( $transaction, 'verifyRequest', [
				'parameters' => $parameters,
				'headers'    => $this->getHeaders(),
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'headers'    => $this->getHeaders(),
				'error'      => $e->getMessage(),
			] );

			throw new VerifyException( $e->getMessage() );
		}

		try {

			$url = 'https://ipgrest.asanpardakht.ir/v1/Settlement';
			$this->AsanPardakhtCurl( $url, $verifyParameters );

			$this->log( $transaction, 'SettlementRequest', [
				'parameters' => $parameters,
				'headers'    => $this->getHeaders(),
			] );

		} catch ( Exception $e ) {

			$this->log( $transaction, 'requestFailed', [
				'parameters' => $parameters,
				'headers'    => $this->getHeaders(),
				'error'      => $e->getMessage(),
			] );

			// If settlement failed we don't need to do anything. AsanPardakht will take care of it in 3 hours.
		}

		$this->log( $transaction, 'verifySuccess' );

		$transaction->update( [
			'gateway_trans_id' => $transResult['rrn'],
			'gateway_status'   => $transResult['serviceStatusCode'] ?? null,
			'status'           => StatusesEnum::STATUS_PAID,
			'card_number'      => $transResult['cardNumber'],
			'paid_at'          => \Carbon\Carbon::now(),
		] );

		return true;
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		?>
		<form name='payment' action='https://asan.shaparak.ir' method='POST'>
			<input type='hidden' name='RefId' value='<?php echo $transaction->gateway_au; ?>'>
			<?php if ( ! empty( $transaction->mobile ) ): ?>
				<input type='hidden' name='mobileap' value='<?php echo $transaction->mobile; ?>'>
			<?php endif; ?>
		</form>
		<script type='text/javascript'>
            window.onload = formSubmit;

            function formSubmit() {
                document.forms[0].submit();
            }
		</script>
		<?php
	}

	/**
	 * @return string[]
	 */
	private function getHeaders(): array {
		return [
			'Accept: application/json',
			'usr: ' . $this->options['username'],
			'pwd: ' . $this->options['password'],
			'Content-Type: application/json',
		];
	}

	/**
	 * @param array $parameters
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getTransResult( array $parameters ): array {
		$url  = 'https://ipgrest.asanpardakht.ir/v1/TranResult?' . http_build_query( $parameters );
		$curl = curl_init();

		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $this->getHeaders() );

		$response = curl_exec( $curl );
		$error    = curl_error( $curl );

		if ( $error ) {
			throw new Exception( $error );
		}

		if ( empty( $response ) ) {
			throw new Exception( 'تراکنشی یافت نشد.' );
		}

		$statusCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

		if ( $statusCode != 200 ) {
			throw new Exception( sprintf( "خطا «%s» در زمان تایید تراکنش رخ داده است.", $statusCode ), $statusCode );
		}

		return json_decode( $response, true );
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
				'label' => 'نام کاربری',
				'key'   => 'username',
			],
			[
				'label' => 'رمز عبور',
				'key'   => 'password',
			],
			[
				'label' => 'کد پیکربندی',
				'key'   => 'merchant',
			],
			[
				'label'       => 'تطبیق موبایل با کارت',
				'key'         => 'matchCardWithMobile',
				'type'        => 'checkbox',
				'description' => 'در صورت تمایل به تطبیق مالک شماره موبایل و مالک شماره کارت تیک بزنید. راهنمای فعالسازی: https://t.me/nabik_net/296',
			],
		];
	}

	/**
	 * @throws Exception
	 */
	public function AsanPardakhtCurl( string $url, ?array $data = null ) {
		$curl = curl_init( $url );

		curl_setopt( $curl, CURLOPT_POST, 1 );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( $data ) );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 8 );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $this->getHeaders() );

		$response = curl_exec( $curl );

		$error = curl_error( $curl );

		if ( $error ) {
			throw new Exception( $error );
		}

		$statusCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

		if ( ! in_array( $statusCode, [ 200, 472 ] ) ) {
			throw new Exception( sprintf( "خطا «%s» در زمان اتصال به درگاه رخ داده است.", $statusCode ) );
		}

		return json_decode( $response, true );
	}
}
