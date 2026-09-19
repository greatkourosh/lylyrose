<?php


namespace Nabik\Gateland\Gateways;

use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Models\Transaction;

class ParspalGateway extends BaseGateway implements ShaparakFeature {

	protected string $name = 'پارس‌پال';

	protected string $description = 'parspal.com';

	protected string $url = 'https://l.nabik.net/parspal';

	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 * @throws Exception
	 */
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
				'description' => 'جهت اتصال به محیط تست از کلید 00000000aaaabbbbcccc000000000000 استفاده کنید.',
			],
		];
	}
}
