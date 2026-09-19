<?php

namespace Nabik\Gateland\Services;

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Gateland;
use Nabik\Gateland\Models\Transaction;

class SMSService {

	public function __construct() {
		add_action( 'nabik/gateland/transaction_created', [ $this, 'transaction_created' ] );
		add_action( 'nabik/gateland/transaction_status_changed', [ $this, 'transaction_status_changed' ], 10, 3 );
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function transaction_created( Transaction $transaction ): void {
		self::transaction_status_changed( null, 'created', $transaction );
	}

	/**
	 * @param ?string     $old_status
	 * @param string      $new_status
	 * @param Transaction $transaction
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function transaction_status_changed( ?string $old_status, string $new_status, Transaction $transaction ) {

		$sms = Gateland::get_option( "sms.transaction_{$new_status}_sms" );

		if ( empty( $sms ) || empty( $transaction->mobile ) ) {
			return;
		}

		$variables = self::getVariables( $transaction );

		$variableKeys = array_map( function ( $key ) {
			return sprintf( "{%s}", $key );
		}, array_keys( $variables ) );

		$message = str_replace( $variableKeys, array_values( $variables ), $sms );

		\Nabik\Utils\V1\SMS::send( $transaction->mobile, $message );
	}

	/**
	 * @param Transaction $transaction
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function getVariables( Transaction $transaction ): array {

		$order = $transaction->getClientOrder();

		return [
			'pay_url'           => $transaction->getPrettyPayURL(),
			'first_name'        => $order['first_name'] ?? '',
			'last_name'         => $order['last_name'] ?? '',
			'order'             => $transaction->order_id,
			'order_id'          => $transaction->order_id,
			'transaction'       => $transaction->id,
			'transaction_id'    => $transaction->id,
			'transaction_token' => $transaction->token,
			'description'       => $transaction->description,
			'amount'            => CurrenciesEnum::tryFrom( $transaction->currency )->price( $transaction->amount ),
		];
	}
}