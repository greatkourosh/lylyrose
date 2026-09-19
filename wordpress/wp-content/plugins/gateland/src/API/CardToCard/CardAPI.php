<?php

namespace Nabik\Gateland\API\CardToCard;

use Nabik\Gateland\API\RestAPI;
use Nabik\Gateland\Models\Card;
use Nabik\GatelandPro\Services\CardToCardService;
use WP_REST_Request;

class CardAPI extends RestAPI {

	public function register_routes() {

		register_rest_route( 'gateland/card-to-card/card', 'index', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'index' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/card', 'add', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'add' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/card', 'update', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'update' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/card', 'bulk-update', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'bulk_update' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/card', 'change-status', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'change_status' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );


		register_rest_route( 'gateland/card-to-card/card', 'set-failover', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'set_failover' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( 'gateland/card-to-card/card', 'delete', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

	}

	public function index( WP_REST_Request $request ) {
		self::response( true, null, [
			'cards' => $this->cards(),
		] );
	}

	public function add( WP_REST_Request $request ) {

		$name        = $request->get_param( 'name' );
		$card_number = $request->get_param( 'card_number' );

		if ( empty( $name ) || empty( $card_number ) ) {
			self::response( false, 'نام یا شماره کارت نمی‌تواند خالی باشد.' );
		}

		if ( ! Card::isValidCardNumber( $card_number ) ) {
			self::response( false, 'شماره کارت وارد شده معتیر نمی‌باشد.' );
		}

		$is_duplicate_card_number = Card::query()
		                                ->where( 'card_number', $card_number )
		                                ->first();

		if ( $is_duplicate_card_number ) {
			self::response( false, 'شماره کارت تکراری می‌باشد.' );
		}

		/** @var Card $card */
		$card = Card::create( [
			'name'        => $name,
			'card_number' => $card_number,
			'status'      => 'active',
			'is_failover' => Card::all()->count() === 0,
		] );

		self::response( true, null, [
			'card_id' => $card->id,
			'cards'   => $this->cards(),
		] );
	}

	public function update( WP_REST_Request $request ) {

		$card_id = $request->get_param( 'card_id' );
		$name    = $request->get_param( 'name' );

		if ( empty( $name ) ) {
			self::response( false, 'نام نمی‌تواند خالی باشد.' );
		}

		try {

			/** @var Card $card */
			$card = Card::query()->findOrFail( $card_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		$card->name = $name;
		$card->save();

		self::response( true, 'کارت با موفقیت بروزرسانی شد.', [
			'cards' => $this->cards(),
		] );
	}

	public function bulk_update( WP_REST_Request $request ) {

		$cards = $request->get_param( 'cards' );

		if ( empty( $cards ) || ! is_array( $cards ) ) {
			self::response( false, 'ورودی کارت‌ها معتبر نمی‌باشد.' );
		}

		foreach ( $cards as $card_id => ['max_quantity' => $max_quantity, 'max_amount' => $max_amount] ) {

			try {

				/** @var Card $card */
				$card = Card::query()->findOrFail( $card_id );

			} catch ( \Exception $e ) {
				self::response( false, $e->getMessage() );
			}

			if ( empty( $max_quantity ) ) {
				$max_quantity = null;
			}

			if ( empty( $max_amount ) ) {
				$max_amount = null;
			}

			$card->max_quantity = $max_quantity;
			$card->max_amount   = $max_amount;
			$card->save();
		}

		self::response( true, 'کارت‌ها با موفقیت بروزرسانی شدند.', [
			'cards' => $this->cards(),
		] );
	}

	public function change_status( WP_REST_Request $request ) {

		$card_id = $request->get_param( 'card_id' );
		$status  = $request->get_param( 'status' );

		if ( ! in_array( $status, [ 'active', 'inactive' ] ) ) {
			self::response( false, 'وضعیت وارد شده معتبر نمی‌باشد.' );
		}

		try {

			/** @var Card $card */
			$card = Card::query()->findOrFail( $card_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( $card->is_failover && $status === 'inactive' ) {
			self::response( false, 'کارت پشتیبان نمی‌تواند غیرفعال شود.' );
		}

		$card->status = $status;
		$card->save();

		self::response( true, 'وضعیت با موفقیت ذخیره شد.', [
			'cards' => $this->cards(),
		] );
	}

	public function set_failover( WP_REST_Request $request ) {

		$card_id = $request->get_param( 'card_id' );

		try {

			/** @var Card $card */
			$card = Card::query()->findOrFail( $card_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		Card::query()->update( [
			'is_failover' => false,
		] );

		$card->refresh()->update( [
			'status'      => 'active',
			'is_failover' => true,
		] );

		self::response( true, 'کارت پشتیبان با موفقیت تنظیم شد.', [
			'cards' => $this->cards(),
		] );
	}

	public function delete( WP_REST_Request $request ) {

		$card_id = $request->get_param( 'card_id' );

		try {

			/** @var Card $card */
			$card = Card::query()->findOrFail( $card_id );

		} catch ( \Exception $e ) {
			self::response( false, $e->getMessage() );
		}

		if ( $card->receipts()->count() ) {
			self::response( false, 'برای این شماره کارت رسید ثبت شده است و صرفا می‌توانید آن را غیرفعال کنید.' );
		}

		// @todo is_failover? use random active/inactive card

		$card->delete();

		self::response( true, 'کارت با موفقیت حذف شد.', [
			'cards' => $this->cards(),
		] );
	}

	public function cards(): array {
		return Card::query()
		           ->orderByDesc( 'id' )
		           ->get()
		           ->map( [ $this, 'resource' ] )
		           ->toArray();
	}

	public function resource( Card $card ): array {
		return [
			'id'           => $card->id,
			'name'         => $card->name,
			'card_number'  => $card->card_number,
			'max_quantity' => $card->max_quantity,
			'max_amount'   => $card->max_amount,
			'is_failover'  => $card->is_failover,
			'status'       => $card->status,
		];
	}

	public function permission_callback( WP_REST_Request $request ): bool {

		if ( ! class_exists( CardToCardService::class ) ) {
			self::response( false, 'آخرین نسخه گیت‌لند حرفه‌ای را نصب و فعال کنید.' );
		}

		return parent::permission_callback( $request );
	}
}