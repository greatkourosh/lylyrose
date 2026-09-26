<?php


namespace Nabik\Gateland\Gateways;


use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\BNPLFeature;
use Nabik\Gateland\Gateways\Features\RefundFeature;
use Nabik\Gateland\Models\Transaction;

class DigiPayGateway extends BaseGateway implements BNPLFeature, RefundFeature {

	protected string $name = 'دیجی پی';

	protected string $description = 'mydigipay.com';

	protected string $url = 'https://l.nabik.net/digipay';

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

	public function refund( Transaction $transaction, int $amount, string $description ) {
		throw new \Exception( sprintf( "جهت استفاده از عودت وجه درگاه «%s» به نسخه حرفه‌ای ارتقا دهید.", esc_attr( $this->name ) ) );
	}
}