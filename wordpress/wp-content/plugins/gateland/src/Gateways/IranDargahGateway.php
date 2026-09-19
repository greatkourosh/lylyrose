<?php


namespace Nabik\Gateland\Gateways;


use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class IranDargahGateway extends BaseGateway implements InquiryFeature, ShaparakFeature {

	protected string $name = 'ایران درگاه';

	protected string $description = 'irandargah.com';

	protected string $url = 'https://l.nabik.net/irandargah';

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
				'label'       => 'مرچنت کد',
				'key'         => 'merchant',
				'description' => 'برای تست درگاه می توانید از مرچنت کد TEST استفاده کنید.',
			],
		];
	}
}