<?php

namespace Nabik\Gateland\Gateways;

use Exception;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Gateways\Features\FreeFeature;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;

class GatelandTestGateway extends BaseGateway implements FreeFeature {

	protected string $name = 'تست گیت‌لند';

	protected string $description = 'درگاه پرداخت تست';

	protected string $url = 'https://nabik.net';

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool|mixed
	 * @throws \Exception
	 */
	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$is_admin = current_user_can( 'manage_options' );

		$ip         = Helper::get_real_ip();
		$allowed_ip = explode( ',', $this->options['allowed_ip'] ?? '' );

		$allowed_ip[] = '127.0.0.1';

		if ( ! in_array( $ip, $allowed_ip ) && ! $is_admin ) {
			throw new Exception( sprintf( 'پرداخت تست صرفا از آی.پی (های) %s مجاز است.', esc_html( $this->options['allowed_ip'] ?? '' ) ) );
		}

		$transaction->update( [
			'gateway_au' => $transaction->id . rand( 100, 999 ),
		] );
	}

	public function inquiry( Transaction $transaction ): bool {
		$this->log( $transaction, 'inquiry', [
			'transaction' => $transaction->toArray(),
		] );

		$is_paid = intval( $_GET['is_paid'] ?? 0 );

		if ( $is_paid ) {

			$this->log( $transaction, 'verifySuccess' );

			$transaction->update( [
				'gateway_trans_id' => $transaction->gateway_au,
				'gateway_status'   => $is_paid,
				'status'           => StatusesEnum::STATUS_PAID,
				'card_number'      => sprintf( '603799******%d', rand( 1111, 9999 ) ),
				'paid_at'          => \Carbon\Carbon::now(),
			] );

			return true;
		}

		throw new InquiryException();
	}

	public function redirect( Transaction $transaction ) {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		if ( ob_get_length() ) {
			ob_clean();
		}

		include GATELAND_DIR . '/templates/pay/test.php';
		exit();
	}

	/**
	 * @return CurrenciesEnum[]
	 */
	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'توضیحات',
				'type'        => 'section',
				'key'         => 'description',
				'description' => 'این درگاه برای تست گیت‌لند می‌باشد. پس از اتمام فرآیند تست، این درگاه حتما غیرفعال شود.',
			],
			[
				'label'       => 'آی.پی‌های مجاز',
				'key'         => 'allowed_ip',
				'description' => 'درگاه پرداخت تست صرفا توسط مدیر سایت یا از آی.پی‌های بالا (جدا شده با کاما) قابل استفاده است. آی.پی فعلی شما: ' . Helper::get_real_ip(),
			],
		];
	}
}
