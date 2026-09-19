<?php

namespace Nabik\Gateland\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Nabik\Gateland\Enums\Gateway\StatusesEnum;
use Nabik\Gateland\Gateland;
use Nabik\Gateland\Gateways\BaseGateway;
use Nabik\Gateland\Gateways\Features\FreeFeature;
use Nabik\Gateland\Models\Gateway;
use Nabik\Gateland\Models\Transaction;

class GatewayService {

	public static function get_host_ip(): string {

		$ip = get_transient( 'gateland_host_ip' );

		if ( $ip !== false ) {
			return $ip;
		}

		$response = wp_remote_get( 'https://icanhazip.com', [
			'timeout' => 1,
		] );
		$response = wp_remote_retrieve_body( $response );

		set_transient( 'gateland_host_ip', $response, MINUTE_IN_SECONDS );

		return $response;
	}

	public static function activated(): array {

		$gateways = get_transient( 'gateland_active_gateways' );

		if ( $gateways !== false ) {
			return $gateways;
		}

		if ( ! \Nabik_Net_Database::Schema()->hasTable( 'gateland_gateways' ) ) {
			return [];
		}

		/** @var Gateway[] $gateways */
		$gateways = Gateway::query()
		                   ->where( 'status', StatusesEnum::STATUS_ACTIVE )
		                   ->get()
		                   ->map( function ( Gateway $gateway ) {
			                   return [
				                   'id'   => $gateway->id,
				                   'name' => $gateway->build()->name(),
				                   'icon' => $gateway->build()->icon(),
			                   ];
		                   } )
		                   ->keyBy( 'id' )
		                   ->toArray();

		set_transient( 'gateland_active_gateways', $gateways, DAY_IN_SECONDS );

		return $gateways;
	}

	public static function reset_activated() {
		delete_transient( 'gateland_active_gateways' );
	}

	/**
	 * Check that new gateways has added
	 *
	 * @return BaseGateway[]
	 */
	public static function loaded(): array {

		$dir       = GATELAND_DIR . '/src/Gateways/';
		$namespace = 'Nabik\Gateland\Gateways';
		$classes   = self::load_dir( $dir, $namespace );

		if ( defined( 'GATELAND_PRO_DIR' ) ) {
			$dir       = GATELAND_PRO_DIR . '/src/Gateways/';
			$namespace = 'Nabik\GatelandPro\Gateways';
			$classes   = array_merge( $classes, self::load_dir( $dir, $namespace ) );
		}

		unset( $classes['BaseGateway'] );

		$gateways = array_map( function ( $class ) {
			return new $class;
		}, $classes );

		shuffle( $gateways );

		usort( $gateways, function ( $a, $b ) {
			return (int) is_a( $b, FreeFeature::class ) <=> (int) is_a( $a, FreeFeature::class );
		} );

		return array_values( $gateways );
	}

	protected static function load_dir( string $dir, string $namespace ): array {

		$classes = [];

		$gateways = glob( $dir . '*.php' );

		foreach ( $gateways as $gateway ) {

			$gateway = str_replace( $dir, '', $gateway );
			$class   = $namespace . '\\' . str_replace( '.php', '', $gateway );
			$slug    = str_replace( [ $namespace, '\\' ], '', $class );

			$classes[ $slug ] = $class;
		}

		return $classes;
	}

	/**
	 * @return Gateway[]
	 */
	public static function used( $has_transaction = true ): array {
		return Gateway::query()
		              ->when( $has_transaction, function ( Builder $query ) {
			              $query->whereHas( 'transactions' );
		              } )
		              ->get()
		              ->all();
	}

	public static function get_features( $object_or_class ): array {

		$features = array_values( class_implements( $object_or_class ) );

		return array_map( function ( $feature ) {
			return str_replace( 'Nabik\\Gateland\\Gateways\\Features\\', '', $feature );
		}, $features );
	}

	/**
	 * @param Collection|Gateway[] $gateways
	 *
	 * @return Gateway[]
	 */
	public static function sort( array $gateways ): array {

		$order = Gateland::get_option( 'general.gateway_order', 'sort' );

		if ( $order === 'sort' ) {
			return $gateways;
		}

		if ( $order === 'amount' ) {
			$select = 'gateway_id, SUM(amount) as total';
		} else {
			$select = 'gateway_id, count(*) as total';
		}

		$gateways = collect( $gateways );

		$transactions = Transaction::query()
		                           ->selectRaw( $select )
		                           ->whereIn( 'gateway_id', $gateways->pluck( 'id' ) )
		                           ->where( 'status', \Nabik\Gateland\Enums\Transaction\StatusesEnum::STATUS_PAID )
		                           ->where( 'created_at', '>', Carbon::now()->subMonth() )
		                           ->groupBy( 'gateway_id' )
		                           ->get()
		                           ->pluck( 'total', 'gateway_id' )
		                           ->toArray();

		return $gateways->sortBy( function ( Gateway $gateway ) use ( $transactions ) {
			return $transactions[ $gateway->id ] ?? 0;
		} )->all();
	}

}