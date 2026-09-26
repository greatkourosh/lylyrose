<?php


namespace Nabik\Gateland\Gateways;


use Carbon\Carbon;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Exceptions\InquiryException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\CardToCardFeature;
use Nabik\Gateland\Gateways\Features\FreeFeature;
use Nabik\Gateland\Gateways\Features\InquiryFeature;
use Nabik\Gateland\Models\Card;
use Nabik\Gateland\Models\Receipt;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

class CardToCardGateway extends BaseGateway implements FreeFeature, InquiryFeature, CardToCardFeature {

	protected string $name = 'کارت به کارت';

	protected string $description = 'ارسال رسید و تایید دستی کارت به کارت';

	protected string $url = 'https://l.nabik.net/gateland-pro?utm_source=card-to-card';

	public function request( Transaction $transaction ): void {
		$this->log( $transaction, 'request', [
			'transaction' => $transaction->toArray(),
		] );

		$cards = Card::query()
		             ->where( 'status', 'active' )
		             ->with( [
			             'receipts' => function ( $query ) {
				             $query->where( 'status', 'accepted' )
				                   ->where( 'created_at', '>=', Carbon::now()->subDays( 30 ) );
			             },
		             ] )
		             ->get()
		             ->filter( function ( $card ) use ( $transaction ) {

			             if ( $card->max_quantity && $card->receipts->count() >= $card->max_quantity ) {
				             return false;
			             }

			             if ( $card->max_amount ) {
				             return $card->receipts->sum( 'accepted_amount' ) <= $card->max_amount - $transaction->amount;
			             }

			             return true;
		             } );

		if ( $cards->count() ) {
			$card = $cards->random();
		} else {
			throw new \Exception( 'لطفا از منو گیت‌لند > کارت‌ها، یک شماره کارت فعال اضافه کنید.' );
		}

		if ( is_null( $card ) ) {
			/** @var Card $card */
			$card = Card::query()
			            ->where( 'is_failover', true )
			            ->first();
		}

		if ( is_null( $card ) ) {
			throw new \Exception( 'هیچ شماره کارتی در گیت‌لند تعریف نشده است.' );
		}

		$transaction->update( [
			'gateway_au' => 'CardToCard',
			'meta'       => [
				'card_id' => $card->id,
			],
		] );
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return bool
	 * @throws InquiryException
	 * @throws VerifyException
	 * @throws \Throwable
	 */
	public function inquiry( Transaction $transaction ): bool {
		$this->log( $transaction, 'inquiry', [
			'transaction' => $transaction->toArray(),
		] );

		$accepted_amount = Receipt::query()
		                          ->where( 'transaction_id', $transaction->id )
		                          ->where( 'status', 'accepted' )
		                          ->sum( 'accepted_amount' );

		if ( $accepted_amount >= $transaction->amount ) {

			$this->log( $transaction, 'inquirySuccess' );

			$transaction->update( [
				'status'  => StatusesEnum::STATUS_PAID,
				'paid_at' => Carbon::now(),
			] );

			return true;
		}

		return false;
	}

	public function redirect( Transaction $transaction ): void {
		$this->log( $transaction, 'redirect', [
			'transaction' => $transaction->toArray(),
		] );

		if ( ob_get_length() ) {
			ob_clean();
		}

		if ( $transaction->created_at->addMinutes( (int) $this->options['expire_time'] )->isPast() ) {
			Pay::showErrorPage( 'تراکنش منقضی شده است. در صورت ارسال رسید، منتظر بررسی آن بمانید.' );
		}

		include GATELAND_DIR . '/templates/pay/card-to-card.php';
		exit();
	}

	public function currencies(): array {
		return [
			CurrenciesEnum::IRT,
		];
	}

	public function options(): array {
		return [
			[
				'label'       => 'مهلت ارسال رسید (دقیقه)',
				'key'         => 'expire_time',
				'type'        => 'number',
				'default'     => 60,
				'description' => 'اگر از ووکامرس استفاده می‌کنید، این مقدار نباید بیشتر از مقدار نگهداری موجودی، در مسیر ووکامرس > پیکربندی > محصولات > انبار > نگهداری موجودی (دقیقه) باشد.',
			],
			[
				'label'   => 'حداکثر حجم تصویر رسید (مگابایت)',
				'key'     => 'max_file_size',
				'type'    => 'number',
				'default' => 4,
			],
			[
				'label'   => 'حداکثر تعداد تصویر رسید',
				'key'     => 'max_receipt_count',
				'type'    => 'number',
				'default' => 4,
			],
		];
	}

}