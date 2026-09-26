<?php


namespace Nabik\Gateland\Gateways;


use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class SadadGateway extends BaseGateway implements InquiryFeature, ShaparakFeature {

	protected string $name = 'بانک ملی';

	protected string $description = 'داده ورزی سداد';

	protected string $url = 'https://l.nabik.net/sadad';

	public function request( Transaction $transaction ): void {
		throw new \Exception( sprintf( "جهت استفاده از درگاه «%s» به نسخه حرفه‌ای ارتقا دهید.", esc_attr( $this->name ) ) );
	}

	public function inquiry( Transaction $transaction ): bool {
		return false;
	}

	public function redirect( Transaction $transaction ) {
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label' => 'شماره پذیرنده',
				'key'   => 'merchant_id',
			],
			[
				'label' => 'شماره ترمینال',
				'key'   => 'terminal_id',
			],
			[
				'label' => 'کلید تراکنش',
				'key'   => 'key',
			],
			[
				'label' => 'شناسه پرداخت (اختیاری)',
				'key'   => 'payment_identity',
			],
		];
	}

	// Helper functions

	private function encrypt_pkcs7( $str, $key ): string {
		$key        = base64_decode( $key );
		$ciphertext = OpenSSL_encrypt( $str, "DES-EDE3", $key, OPENSSL_RAW_DATA );

		return base64_encode( $ciphertext );
	}

	/**
	 * @throws Exception
	 */
	public function sadadCurl( string $url, string $data ): array {
		$curl = curl_init( $url );

		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_AUTOREFERER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 8 );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: ' . strlen( $data ),
		] );

		$response = curl_exec( $curl );

		$error = curl_error( $curl );

		if ( $error ) {
			throw new Exception( $error );
		}

		return json_decode( $response, true );
	}
}