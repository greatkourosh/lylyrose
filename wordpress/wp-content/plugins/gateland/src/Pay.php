<?php

namespace Nabik\Gateland;

use MaxMind\Db\Reader;
use Nabik\Gateland\Enums\Gateway\StatusesEnum;
use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum as TransactionStatusesEnum;
use Nabik\Gateland\Exceptions\ValidationErrorException;
use Nabik\Gateland\Exceptions\VerifyException;
use Nabik\Gateland\Gateways\Features\ShaparakFeature;
use Nabik\Gateland\Helpers\SQID;
use Nabik\Gateland\Models\Gateway;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Services\GatewayService;
use Rakit\Validation\RuleNotFoundException;
use Rakit\Validation\Validator;

defined( 'ABSPATH' ) || exit;

class Pay {

	/**
	 * @param array $data
	 *
	 * @return array
	 * @throws ValidationErrorException
	 * @throws RuleNotFoundException
	 */
	public static function request( array $data ): array {

		$data = apply_filters( 'nabik/gateland/pay_request', $data );

		$validator = new Validator;

		$validation = $validator->validate( $data, [
			'amount'      => 'required|numeric|min:0|max:999999999999',
			'client'      => [
				'required',
				$validator( 'in', array_keys( Transaction::getClients() ) ),
			],
			'currency'    => [
				'required',
				$validator( 'in', array_keys( CurrenciesEnum::cases() ) ),
			],
			'order_id'    => 'required|integer|min:0',
			'callback'    => 'required|url',
			'description' => 'nullable|between:0,256',
			'mobile'      => 'nullable',
			'user_id'     => 'nullable|integer|min:0',
			'gateway_id'  => 'nullable|integer|min:1',
			'meta'        => 'nullable|array|min:1',
		] );

		if ( $validation->fails() ) {
			return self::response( false, null, [
				'errors' => $validation->errors()->toArray(),
			] );
		}

		$gateways = Gateway::query()
		                   ->where( 'status', StatusesEnum::STATUS_ACTIVE )
		                   ->where( 'currencies', 'LIKE', "%{$data['currency']}%" );


		if ( $data['gateway_id'] ?? 0 ) {
			$gateways->where( 'id', $data['gateway_id'] );
		}

		if ( ! $gateways->count() ) {
			return self::response( false, 'درگاهی برای واحد پولی شما فعال نیست.' );
		}

		// @todo sanitize and validate phone number

		$transactionData = [
			'client'      => esc_sql( $data['client'] ),
			'user_id'     => intval( $data['user_id'] ?? null ),
			'amount'      => esc_sql( $data['amount'] ),
			'currency'    => $data['currency'],
			'callback'    => $data['callback'],
			'description' => esc_sql( $data['description'] ?? null ),
			'order_id'    => intval( $data['order_id'] ),
			'mobile'      => esc_sql( $data['mobile'] ?? null ),
			'status'      => TransactionStatusesEnum::STATUS_PENDING,
			'meta'        => $data['meta'] ?? null,
		];

		if ( $data['gateway_id'] ?? 0 ) {
			$transactionData['gateway_id'] = $data['gateway_id'];
		}

		/** @var Transaction $transaction */
		$transaction = Transaction::create( $transactionData );

		if ( empty( $transaction ) ) {

			Gateland::log( $transactionData );

			return self::response( false, 'تراکنش ایجاد نشد، مجددا تلاش کنید.' );
		}

		do_action( 'nabik/gateland/transaction_created', $transaction );

		return self::response( true, null, [
			'authority'    => $transaction->id,
			'payment_link' => $transaction->getPrettyPayURL(),
		] );
	}

	public static function pay( string $transaction_token ) {

		/** @var Transaction $transaction */
		$transaction = Transaction::find( SQID::decode( $transaction_token ) );

		if ( is_null( $transaction ) ) {
			self::showErrorPage( 'تراکنش یافت نشد.' );
		}

		$transaction->logs()->create( [
			'event' => 'Call::pay',
			'data'  => [
				'token'       => $transaction_token,
				'transaction' => $transaction->toArray(),
			],
		] );

		if ( $transaction->token != $transaction_token ) {
			self::showErrorPage( 'تراکنش یافت نشد!' );
		}

		if ( Gateland::get_option( 'general.iran_access', 0 ) && defined( 'GATELAND_PRO_DIR' ) ) {

			try {
				$reader = new Reader( GATELAND_PRO_DIR . '/assets/ip.mmdb' );

				$user_id = Helper::get_real_ip();
				$data    = $reader->get( $user_id );
				$country = $data['country']['iso_code'] ?? null;

				if ( $country != 'IR' ) {

					$transaction->logs()->create( [
						'event' => 'Transaction::iran_access',
						'data'  => [
							'user_ip' => $user_id,
							'data'    => $data,
						],
					] );

					self::showVPNPage( $transaction );
				}

			} catch ( Reader\InvalidDatabaseException $e ) {
				self::showErrorPage( 'دیتابیس IP معتبر نمی‌باشد.' );
			}

		}

		if ( ! $transaction->isPending() ) {
			switch ( $transaction->status ) {
				case TransactionStatusesEnum::STATUS_PAID:
					self::showErrorPage( 'تراکنش قبلا پرداخت شده است.', 'success' );
				default:
					self::showErrorPage( 'تراکنش قبلا پردازش شده است.' );
			}
		}

		if ( ! is_null( $transaction->gateway_au ) ) {
			/** @var Gateway $gateway */
			$gateway = Gateway::find( $transaction->gateway_id );

			if ( is_null( $gateway ) ) {
				self::showErrorPage( 'درگاه یافت نشد.' );
			}

			return $gateway->build()->redirect( $transaction );
		}

		$gateways = Gateway::query()
		                   ->where( 'status', StatusesEnum::STATUS_ACTIVE )
		                   ->where( 'currencies', 'LIKE', "%{$transaction->currency}%" );

		if ( $transaction->gateway_id ) {
			$gateways->where( 'id', $transaction->gateway_id );
		}

		$gateways = $gateways->orderBy( 'sort' )
		                     ->get()
		                     ->all();

		if ( empty( $gateways ) ) {
			self::showErrorPage( 'درگاهی یافت نشد.' );
		}

		$gateway = null;
		$message = null;

		$gateways = GatewayService::sort( $gateways );

		foreach ( $gateways as $gateway ) {

			$gatewayBuilder = $gateway->build();

			if ( ! $transaction->gateway_id && ! is_a( $gatewayBuilder, ShaparakFeature::class ) ) {
				$gateway = null;

				continue;
			}

			$transaction->logs()->create( [
				'event' => 'Gateway::try',
				'data'  => [
					'gateway' => $gatewayBuilder->name(),
				],
			] );

			try {
				$gatewayBuilder->request( $transaction );

				$transaction->update( [
					'gateway_id' => $gateway->id,
					'ip'         => Helper::get_real_ip(),
				] );

				break;

			} catch ( \Exception $e ) {
				$transaction->logs()->create( [
					'event' => 'Gateway::failed',
					'data'  => [
						'gateway' => $gatewayBuilder->name(),
						'error'   => $e->getMessage(),
					],
				] );

				$message = $e->getMessage();
				$gateway = null;
			}
		}

		if ( is_null( $gateway ) ) {
			self::showPayPage( $transaction, $message );
		}

		return $gateway->build()->redirect( $transaction );
	}

	public static function callback( string $transaction_token, string $sign ) {

		/** @var Transaction $transaction */
		$transaction = Transaction::find( SQID::decode( $transaction_token ) );

		if ( is_null( $transaction ) ) {
			self::showErrorPage( 'تراکنش یافت نشد.' );
		}

		$transaction->logs()->create( [
			'event' => 'Call::callback',
			'data'  => [
				'request'     => array_merge( $_GET, $_POST ),
				'transaction' => $transaction->toArray(),
			],
		] );

		if ( $transaction->token != $transaction_token ) {
			self::showErrorPage( 'تراکنش یافت نشد!' );
		}

		if ( $transaction->sign != $sign ) {
			self::showErrorPage( 'تراکنش یافت نشد :)' );
		}

		if ( ! $transaction->isPending() ) {
			wp_redirect( $transaction->callback );
			exit();
		}

		if ( is_null( $transaction->gateway_id ) ) {

			if ( $transaction->isExpired() ) {

				$transaction->logs()->create( [
					'event' => 'Callback::isExpired',
				] );

				$transaction->update( [
					'status' => TransactionStatusesEnum::STATUS_FAILED,
				] );
			}

			wp_redirect( $transaction->callback );
			exit();
		}

		try {
			$transaction->gateway->build()->verify( $transaction );
		} catch ( VerifyException $e ) {
			self::showVerifyPage( $transaction, $e );
		}

		wp_redirect( $transaction->callback );
		exit();
	}

	public static function verify( int $transaction_id, string $client ): array {
		/** @var Transaction $transaction */
		$transaction = Transaction::find( $transaction_id );

		if ( is_null( $transaction ) ) {
			return self::response( false, 'تراکنش یافت نشد.' );
		}

		if ( $transaction->client !== $client ) {
			return self::response( false, 'تراکنش یافت نشد!' );
		}

		$transaction->logs()->create( [
			'event' => 'Call::verify',
			'data'  => [
				'transaction' => $transaction->toArray(),
			],
		] );

		$success = $transaction->status == TransactionStatusesEnum::STATUS_PAID && is_null( $transaction->verified_at );

		if ( $success ) {
			$transaction->logs()->create( [
				'event' => 'Verify::verified',
				'data'  => [
					'transaction' => $transaction->toArray(),
				],
			] );

			$transaction->update( [
				'verified_at' => current_time( 'mysql', true ),
			] );
		}

		return self::response( $success, null, [
			'status'         => $transaction->status,
			'amount'         => $transaction->amount,
			'currency'       => $transaction->currency,
			'trans_id'       => $transaction->gateway_trans_id,
			'verified_at'    => $transaction->verified_at ? Helper::en_num( Helper::date( $transaction->verified_at ) ) : null,
			'gateway_status' => $transaction->gateway_status,
			'card_number'    => $transaction->card_number,
			'gateway'        => $transaction->gateway ? $transaction->gateway->build()->name() : null,
		] );
	}

	private static function response( bool $success, ?string $message = null, ?array $data = null ): array {
		return [
			'success' => $success,
			'message' => $message,
			'data'    => $data,
		];
	}

	/**
	 * @param string $message
	 * @param string $alert
	 *
	 * @return no-return
	 */
	public static function showErrorPage( string $message, string $alert = 'danger' ) {

		if ( ob_get_length() ) {
			ob_clean();
		}

		$error = apply_filters( 'nabik/gateland/pay_error_message', $message, $alert );

		include GATELAND_DIR . '/templates/pay/error.php';
		exit();
	}

	/**
	 * @param VerifyException $e
	 *
	 * @return no-return
	 */
	public static function showVerifyPage( Transaction $transaction, VerifyException $e ) {

		if ( ob_get_length() ) {
			ob_clean();
		}

		$error = sprintf( 'ارتباط با درگاه پرداخت «%s» برقرار نشد و وضعیت تراکنش نامشخص است. لطفا چند لحظه صبر کنید و دوباره تلاش کنید.', $transaction->gateway_label );

		if ( ! empty( $e->getMessage() ) ) {
			$error = $e->getMessage();
		}

		include GATELAND_DIR . '/templates/pay/verify.php';
		exit();
	}

	/**
	 * @param Transaction     $transaction
	 * @param                 $message
	 *
	 * @return no-return
	 */
	public static function showPayPage( Transaction $transaction, $message ) {

		if ( ob_get_length() ) {
			ob_clean();
		}

		include GATELAND_DIR . '/templates/pay/pay.php';
		exit();
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return no-return
	 */
	public static function showVPNPage( Transaction $transaction ) {

		if ( ob_get_length() ) {
			ob_clean();
		}

		include GATELAND_DIR . '/templates/pay/vpn.php';
		exit();
	}
}