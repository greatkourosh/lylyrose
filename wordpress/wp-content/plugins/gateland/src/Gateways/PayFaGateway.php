<?php


namespace Nabik\Gateland\Gateways;


use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class PayFaGateway extends BaseGateway implements InquiryFeature, ShaparakFeature {

	protected string $name = 'پی‌فا';

	protected string $description = 'payfa.com';

	protected string $url = 'https://l.nabik.net/payfa';

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
				'label'       => 'کلید وب سرویس',
				'key'         => 'api_key',
				'description' => 'کلید وب سرویس را از حساب کاربری پی‌فا دریافت کنید.',
			],
		];
	}
}