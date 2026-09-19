<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 */

namespace Nabik\Gateland;

use Illuminate\Database\Schema\Blueprint;
use Nabik\Gateland\Models\Card;
use Nabik\Gateland\Models\Gateway;
use Nabik\Gateland\Models\Transaction;
use Nabik_Net_Database;

defined( 'ABSPATH' ) || exit;

class Version extends \Nabik\Utils\V1\Version {

	protected string $current_version = GATELAND_VERSION;

	public function updated() {

		flush_rewrite_rules();

	}

	public function update_171() {

		if ( Nabik_Net_Database::schema()->hasColumn( 'gateland_transactions', 'national_code' ) ) {
			return;
		}

		Nabik_Net_Database::schema()->table( 'gateland_transactions', function ( Blueprint $table ) {
			$table->after( 'card_number', function ( Blueprint $table ) {
				$table->string( 'national_code', 20 )->nullable();
				$table->json( 'allowed_cards' )->nullable();
			} );
			$table->json( 'meta' )->nullable()->after( 'gateway_id' );
			$table->string( 'email' )->nullable()->after( 'ip' );
		} );

	}

	public function update_200() {

		/** @var Gateway[] $gateways */
		$gateways = Gateway::query()
		                   ->whereIn( 'class', [
			                   'Nabik\GatelandPro\Gateways\PasargadGateway',
			                   'Nabik\GatelandPro\Gateways\SamanGateway',
			                   'Nabik\GatelandPro\Gateways\VandarGateway',
		                   ] )
		                   ->get();

		foreach ( $gateways as $gateway ) {
			$gateway->class = str_replace( 'GatelandPro', 'Gateland', $gateway->class );
			$gateway->save();
		}

	}

	public function update_201() {

		/** @var Gateway[] $gateways */
		$gateways = Gateway::query()
		                   ->whereIn( 'class', [
			                   'Nabik\GatelandPro\Gateways\BitPayGateway',
			                   'Nabik\GatelandPro\Gateways\ShepaGateway',
			                   'Nabik\GatelandPro\Gateways\PayPingGateway',
		                   ] )
		                   ->get();

		foreach ( $gateways as $gateway ) {
			$gateway->class = str_replace( 'GatelandPro', 'Gateland', $gateway->class );
			$gateway->save();
		}

	}

	public function update_212() {

		/** @var Gateway[] $gateways */
		$gateways = Gateway::query()
		                   ->whereIn( 'class', [
			                   'Nabik\GatelandPro\Gateways\PayIRGateway',
		                   ] )
		                   ->get();

		foreach ( $gateways as $gateway ) {
			$gateway->class = str_replace( 'GatelandPro', 'Gateland', $gateway->class );
			$gateway->save();
		}

	}

	public function update_220() {
		global $wpdb;

		$keys = Nabik_Net_Database::DB()->select( sprintf( 'SHOW KEYS FROM `%s`', $wpdb->prefix . 'gateland_transactions' ) );

		foreach ( $keys as $key ) {

			if ( $key->Key_name == 'gateland_transactions_created_at_index' ) {
				return;
			}

		}

		Nabik_Net_Database::Schema()->table( 'gateland_transactions', function ( Blueprint $table ) {
			$table->index( 'created_at' );
		} );

	}

	public function update_230() {
		global $wpdb;

		$duplicate_transactions = Nabik_Net_Database::DB()->table( 'gateland_transactions' )
		                                            ->select( 'gateway_trans_id', 'gateway_id' )
		                                            ->whereNotNull( 'gateway_trans_id' )
		                                            ->groupBy( 'gateway_trans_id', 'gateway_id' )
		                                            ->havingRaw( 'count(*) > 1' )
		                                            ->get();

		foreach ( $duplicate_transactions as $duplicate_transaction ) {

			$transactions = Transaction::query()
			                           ->where( 'gateway_trans_id', $duplicate_transaction->gateway_trans_id )
			                           ->where( 'gateway_id', $duplicate_transaction->gateway_id )
			                           ->get();

			foreach ( $transactions as $index => $transaction ) {

				if ( $index ) {
					$transaction->gateway_trans_id = $duplicate_transaction->gateway_trans_id . '-' . $index;
					$transaction->save();
				}

			}

		}

		$table = $wpdb->prefix . 'gateland_transactions';
		$query = sprintf( 'ALTER TABLE `%s` MODIFY COLUMN `gateway_trans_id` varchar(191)', $table );
		Nabik_Net_Database::DB()->statement( $query );

		if ( ! Nabik_Net_Database::Schema()->hasTable( 'gateland_refunds' ) ) {

			Nabik_Net_Database::Schema()->create( 'gateland_refunds', function ( Blueprint $table ) {
				$table->id();
				$table->double( 'amount', 12, 2 );
				$table->text( 'description' )->nullable();
				$table->string( 'refund_id' )->nullable();
				$table->foreignId( 'transaction_id' );
				$table->foreignId( 'user_id' )->nullable();
				$table->timestamp( 'created_at' );
			} );

		}

		$keys = Nabik_Net_Database::DB()->select( sprintf( 'SHOW KEYS FROM `%s`', $wpdb->prefix . 'gateland_transactions' ) );

		foreach ( $keys as $key ) {

			if ( $key->Key_name == 'gateland_transactions_gateway_trans_id_gateway_id_unique' ) {
				return;
			}

		}

		Nabik_Net_Database::Schema()->table( 'gateland_transactions', function ( Blueprint $table ) {
			$table->unique( [ 'gateway_trans_id', 'gateway_id' ] );
		} );
	}

	public function update_231() {

		/** @var Gateway[] $gateways */
		$gateways = Gateway::query()
		                   ->whereIn( 'class', [
			                   'Nabik\GatelandPro\Gateways\AsanPardakhtGateway',
		                   ] )
		                   ->get();

		foreach ( $gateways as $gateway ) {
			$gateway->class = str_replace( 'GatelandPro', 'Gateland', $gateway->class );
			$gateway->save();
		}

	}

	public function update_234() {

		/** @var Gateway[] $gateways */
		$gateways = Gateway::query()
		                   ->whereIn( 'class', [
			                   'Nabik\Gateland\Gateways\StarShopGateway',
		                   ] )
		                   ->get();

		foreach ( $gateways as $gateway ) {
			$gateway->class = str_replace( 'StarShopGateway', 'DirectPayGateway', $gateway->class );
			$gateway->save();
		}

	}

	public function update_240() {

		if ( ! Nabik_Net_Database::Schema()->hasTable( 'gateland_cards' ) ) {

			Nabik_Net_Database::Schema()->create( 'gateland_cards', function ( Blueprint $table ) {
				$table->id();
				$table->string( 'name' );
				$table->string( 'card_number' )->unique();
				$table->string( 'status' );
				$table->integer( 'max_quantity' )->nullable();
				$table->integer( 'max_amount' )->nullable();
				$table->boolean( 'is_failover' );
				$table->timestamps();
			} );

		}

		if ( ! Nabik_Net_Database::Schema()->hasTable( 'gateland_receipts' ) ) {

			Nabik_Net_Database::Schema()->create( 'gateland_receipts', function ( Blueprint $table ) {
				$table->id();
				$table->foreignIdFor( Transaction::class );
				$table->foreignIdFor( Card::class );
				$table->foreignId( 'attachment_id' );
				$table->string( 'card_number' )->nullable();
				$table->string( 'tracking_number' )->nullable();
				$table->integer( 'amount' );
				$table->integer( 'accepted_amount' )->nullable();
				$table->string( 'status' )->default( 'pending' );
				$table->foreignId( 'reviewed_by' )->nullable();
				$table->json( 'meta' )->nullable();
				$table->timestamps();
				$table->timestamp( 'reviewed_at' )->nullable();
			} );

		}

	}

}
