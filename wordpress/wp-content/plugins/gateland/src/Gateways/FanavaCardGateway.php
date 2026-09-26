<?php


namespace Nabik\Gateland\Gateways;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class FanavaCardGateway extends BaseGateway implements ShaparakFeature, InquiryFeature {

	protected string $name = 'فن‌آوا کارت';

	protected string $description = 'fanavacard.ir';

	protected string $url = 'https://l.nabik.net/fanavacard';

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
				'label' => 'نام کاربری',
				'key'   => 'username',
			],
			[
				'label' => 'کلمه عبور',
				'key'   => 'password',
			],
			[
				'label'       => 'MID',
				'key'         => 'MID',
				'description' => 'Merchant Configuration ID',
			],
		];
	}
}
