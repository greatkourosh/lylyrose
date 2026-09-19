<?php

namespace Nabik\Gateland\API\CardToCard;

use Nabik\Gateland\API\RestAPI;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Helpers\SQID;
use Nabik\Gateland\Models\Card;
use Nabik\Gateland\Models\Receipt;
use Nabik\Gateland\Models\Transaction;
use Nabik\GatelandPro\Services\CardToCardService;
use WP_REST_Request;

class TransactionAPI extends RestAPI {

	public function register_routes() {

		register_rest_route( 'gateland/card-to-card/transaction', 'view', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'view' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/transaction', 'upload-receipt', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'upload_receipt' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/transaction', 'delete-receipt', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_receipt' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

	}

	public function view( WP_REST_Request $request ) {

		/** @var Transaction $transaction */
		$transaction = $request->get_param( 'transaction' );

		try {
			$gateway = CardToCardService::get_gateway();
		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		/** @var Card $card */
		$card = Card::query()->find( $transaction->meta['card_id'] );

		/** @var Receipt[] $receipts */
		$receipts = Receipt::query()
		                   ->where( 'transaction_id', $transaction->id )
		                   ->orderByDesc( 'created_at' )
		                   ->get()
		                   ->map( [ $this, 'resource' ] )
		                   ->toArray();

		self::response( true, null, [
			'id'                => $transaction->id,
			'card'              => [
				'name'   => $card->name,
				'number' => $card->card_number,
			],
			'bank'              => [
				'name' => $card->bank_name,
				'logo' => GATELAND_URL . '/assets/images/gateways/CardToCard.png',
			],
			'receipts'          => $receipts,
			'status'            => $transaction->status,
			'amount'            => $transaction->amount,
			'site_name'         => get_bloginfo( 'name' ),
			'currency'          => CurrenciesEnum::tryFrom( $transaction->currency )->symbol(),
			'order_id'          => $transaction->order_id,
			'created_at'        => Helper::date( $transaction->created_at, 'Y/m/d H:i' ),
			'remain_time'       => $transaction->created_at->addMinutes( $gateway->options['expire_time'] )->diffInSeconds(),
			'max_file_size'     => $gateway->options['max_file_size'],
			'max_receipt_count' => $gateway->options['max_receipt_count'],
		] );
	}

	public function upload_receipt( WP_REST_Request $request ) {

		/** @var Transaction $transaction */
		$transaction     = $request->get_param( 'transaction' );
		$card_number     = $request->get_param( 'card_number' );
		$tracking_number = $request->get_param( 'tracking_number' );
		$amount          = intval( $request->get_param( 'amount' ) );

		if ( $transaction->status != 'pending' ) {
			self::response( false, 'تراکنش قبلا پردازش شده است.' );
		}

		$card_number = str_replace( [
			'IR',
			'-',
			' ',
			'_',
		], '', Helper::en_num( $card_number ) );

		if ( ! Card::isValidCardNumber( $card_number ) && ! Card::isValidIBAN( $card_number ) ) {
			self::response( false, 'شماره کارت یا شبا وارد شده معتیر نمی‌باشد.' );
		}

		if ( empty( $tracking_number ) ) {
			self::response( false, 'شماره پیگیری نمی‌تواند خالی باشد.' );
		}

		if ( empty( $amount ) ) {
			self::response( false, 'مبلغ نمی‌تواند خالی باشد.' );
		}

		if ( $amount > $transaction->amount * 10 ) {
			self::response( false, 'مبلغ نمی‌تواند از مبلغ تراکنش بیشتر باشد.' );
		}

		/** @var Card $card */
		$card = Card::query()->find( $transaction->meta['card_id'] );

		if ( $card->card_number == $card_number ) {
			self::response( false, 'شماره کارت مبدا و مقصد نمی‌تواند یکسان باشد.' );
		}

		$receipt_count = Receipt::query()
		                        ->where( 'transaction_id', $transaction->id )
		                        ->count();

		try {
			$gateway = CardToCardService::get_gateway();
		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( $receipt_count >= $gateway->options['max_receipt_count'] ) {
			self::response( false, sprintf( 'شما حداکثر می‌توانید %s رسید ارسال کنید.', $gateway->options['max_receipt_count'] ) );
		}

		try {
			$attachment_id = CardToCardService::upload_receipt( $transaction );
		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		/** @var Receipt $receipt */
		$receipt = Receipt::create( [
			'transaction_id'  => $transaction->id,
			'card_id'         => $card->id,
			'attachment_id'   => $attachment_id,
			'card_number'     => $card_number,
			'tracking_number' => $tracking_number,
			'amount'          => $amount,
			'status'          => 'pending',
		] );

		$remain_amount = $transaction->amount - Receipt::query()
		                                               ->where( 'transaction_id', $transaction->id )
		                                               ->whereIn( 'status', [ 'pending', 'accepted' ] )
		                                               ->sum( 'amount' );

		self::response( true, 'رسید با موفقیت بارگذاری شد.', [
			'receipt'       => $this->resource( $receipt ),
			'remain_amount' => max( $remain_amount, 0 ),
		] );
	}

	public function delete_receipt( WP_REST_Request $request ) {

		/** @var Transaction $transaction */
		$transaction = $request->get_param( 'transaction' );
		$receipt_id  = $request->get_param( 'receipt_id' );

		try {

			/** @var Receipt $receipt */
			$receipt = Receipt::query()->findOrFail( $receipt_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( $receipt->transaction_id != $transaction->id ) {
			self::response( false, 'رسید متعلق به تراکنش نمی‌باشد.' );
		}

		if ( $receipt->status != 'pending' ) {
			self::response( false, 'این رسید بررسی شده است و قابل حذف نیست.' );
		}

		wp_delete_attachment( $receipt->attachment_id, true );

		$receipt->delete();

		/** @var Receipt[] $receipts */
		$receipts = Receipt::query()
		                   ->where( 'transaction_id', $transaction->id )
		                   ->orderByDesc( 'created_at' )
		                   ->get()
		                   ->map( [ $this, 'resource' ] )
		                   ->toArray();

		self::response( true, 'رسید با موفقیت حذف شد.', [
			'receipts' => $receipts,
		] );
	}

	public function resource( Receipt $receipt ): array {
		return [
			'attachment_url'  => $receipt->attachment_url,
			'id'              => $receipt->id,
			'tracking_number' => $receipt->tracking_number,
			'amount'          => $receipt->amount,
			'accepted_amount' => $receipt->accepted_amount,
			'created_at'      => Helper::date( $receipt->created_at, 'Y/m/d H:i' ),
			'status'          => $receipt->status,
		];
	}

	public function permission_callback( WP_REST_Request $request ): bool {

		if ( ! class_exists( CardToCardService::class ) ) {
			self::response( false, 'آخرین نسخه گیت‌لند حرفه‌ای را نصب و فعال کنید.' );
		}

		$transaction_token = strval( $request->get_param( 'transaction_token' ) );

		try {

			/** @var Transaction $transaction */
			$transaction = Transaction::query()->findOrFail( SQID::decode( $transaction_token ) );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( $transaction->token != $transaction_token ) {
			self::response( false, 'توکن تراکنش معتبر نمی‌باشد.' );
		}

		$card_id = $transaction->meta['card_id'] ?? null;

		if ( $transaction->gateway_au != 'CardToCard' || empty( $card_id ) ) {
			self::response( false, 'درگاه این تراکنش کارت به کارت نیست.' );
		}

		if ( $transaction->status != 'pending' ) {
			self::response( false, 'این تراکنش قبلا پردازش شده است.' );
		}

		$request->set_param( 'transaction', $transaction );

		return true;
	}
}