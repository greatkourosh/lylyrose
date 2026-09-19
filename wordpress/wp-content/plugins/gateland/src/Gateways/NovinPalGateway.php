<?php


namespace Nabik\Gateland\Gateways;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class NovinPalGateway extends BaseGateway implements InquiryFeature, ShaparakFeature {

	protected string $name = 'نوین‌پال';

	protected string $description = 'novinpal.ir';

	protected string $url = 'https://l.nabik.net/novinpal';

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
				'label'       => 'کلید API',
				'key'         => 'api_key',
				'description' => 'کلید API را از آدرس https://panel.novinpal.ir دریافت کنید.',
			],
		];
	}
}