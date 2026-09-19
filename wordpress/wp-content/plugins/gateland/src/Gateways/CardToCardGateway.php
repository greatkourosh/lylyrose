<?php


namespace Nabik\Gateland\Gateways;


use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\CardToCardFeature;
use Nabik\Gateland\Gateways\Features\FreeFeature;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Models\Transaction;

class CardToCardGateway extends BaseGateway implements FreeFeature, InquiryFeature, CardToCardFeature {

	protected string $name = 'کارت به کارت';

	protected string $description = 'ارسال رسید و تایید دستی کارت به کارت';

	protected string $url = 'https://l.nabik.net/gateland-pro?utm_source=card-to-card';

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
		return [];
	}
}