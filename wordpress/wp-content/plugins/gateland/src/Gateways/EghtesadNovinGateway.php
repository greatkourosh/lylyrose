<?php


namespace Nabik\Gateland\Gateways;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class EghtesadNovinGateway extends BaseGateway implements InquiryFeature, ShaparakFeature {

	protected string $name = 'اقتصاد نوین';

	protected string $description = 'پرداخت نوین';

	protected string $url = 'https://l.nabik.net/pna';

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
				'label' => 'شماره ترمینال',
				'key'   => 'terminal',
			],
			[
				'label' => 'نام کاربری',
				'key'   => 'username',
			],
			[
				'label' => 'کلمه‌عبور',
				'key'   => 'password',
			],
		];
	}
}