<?php


namespace Nabik\Gateland\Gateways;


use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class IDPayGateway extends BaseGateway implements InquiryFeature, ShaparakFeature {

	protected string $name = 'آی.دی پی';

	protected string $description = 'idpay.ir';

	protected string $url = 'https://l.nabik.net/idpay';

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
				'description' => 'کلید وب سرویس را از آدرس https://panel.idpay.ir/web-services دریافت کنید.',
			],
		];
	}
}