<?php


namespace Nabik\Gateland\Gateways;


use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class PanapalGateway extends BaseGateway implements ShaparakFeature {

	protected string $name = 'پاناپال';

	protected string $description = 'panapal.ir';

	protected string $url = 'https://l.nabik.net/panapal';

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
				'label'       => 'مرچنت آی.دی',
				'key'         => 'merchant_id',
				'description' => 'مرچنت آی.دی درگاه پاناپال',
			],
		];
	}
}