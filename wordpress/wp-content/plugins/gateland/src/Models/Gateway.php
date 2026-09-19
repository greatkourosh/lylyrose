<?php

namespace Nabik\Gateland\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nabik\Gateland\Enums\Gateway\StatusesEnum;
use Nabik\Gateland\Gateways\BaseGateway;

/**
 * Class Gateway
 *
 * @package Nabik\Gateland\Models
 *
 * @property integer                  $id
 * @property string                   $class
 * @property StatusesEnum             $status
 * @property int                      $sort
 * @property array                    $data
 * @property array                    $currencies
 *
 * @property Carbon                   $created_at
 * @property Carbon                   $updated_at
 *
 * @property Transaction[]|Collection $transactions
 */
class Gateway extends Model {

	protected $table = 'gateland_gateways';

	// Attributes

	protected $fillable = [
		'id',
		'class',
		'status',
		'sort',
		'data',
		'currencies',
	];

	protected $casts = [
		'data'       => 'array',
		'currencies' => 'array',
//		'status'     => StatusesEnum::class,
	];

	/**
	 * @var BaseGateway|null
	 */
	public ?BaseGateway $gateway = null;

	// Function

	public function save( array $options = [] ) {

		if ( is_null( $this->id ) ) {
			$this->sort = Gateway::query()->max( 'sort' ) + 1;
		}

		return parent::save( $options );
	}

	/**
	 * @return BaseGateway
	 */
	public function build(): BaseGateway {

		if ( ! class_exists( $this->class ) ) {

			return new class( $this ) extends BaseGateway {

				protected Gateway $gateway;

				protected string $name = 'درگاه حذف شده';

				protected string $description = 'درگاه';

				protected string $url = 'https://nabik.net';


				public function __construct( $gateway ) {
					$this->gateway = $gateway;

					parent::__construct();
				}

				public function name(): string {

					$is_pro = str_contains( $this->gateway->class, 'GatelandPro' );

					return $is_pro ? 'نسخه حرفه‌ای را نصب و فعال کنید.' : $this->name;
				}

				public function description(): string {
					return str_replace( 'Nabik\\', '', $this->gateway->class );
				}

				public function icon(): string {
					$slug = str_replace( [
						'Nabik\Gateland\Gateways\\',
						'Nabik\GatelandPro\Gateways\\',
						'Gateway',
					], '',
						$this->gateway->class
					);

					return GATELAND_URL . ( sprintf( 'assets/images/gateways/%s.png', $slug ) );
				}

				public function request( Transaction $transaction ): void {
					throw new \Exception( 'درگاه حذف شده است.' );
				}

				public function inquiry( Transaction $transaction ): bool {
					return false;
				}

				public function redirect( Transaction $transaction ) {
				}

				public function currencies(): array {
					return [];
				}

				public function options(): array {
					return [];
				}

			};

		}

		if ( ! is_null( $this->gateway ) ) {
			return $this->gateway;
		}

		$this->gateway = new $this->class;

		$this->gateway->options = collect( $this->gateway->options() )
			->pluck( 'default', 'key' )
			->merge( $this->data )
			->toArray();

		return $this->gateway;
	}

	// Relations

	public function transactions(): HasMany {
		return $this->hasMany( Transaction::class );
	}

	//Scopes

	/**
	 * @param Builder $query
	 * @param string  $name
	 *
	 * @return Builder
	 */
	public function scopeName( Builder $query, string $name ) {
		return $query->where( 'name', $name );
	}

	/**
	 * @param Builder $query
	 * @param string  $currency
	 *
	 * @return Builder
	 */
	public function scopeCurrency( Builder $query, $currency ) {
		return $query->whereJsonContains( 'currencies', $currency );
	}

	/**
	 * @param Builder             $query
	 * @param StatusesEnum|string $status
	 *
	 * @return Builder
	 */
	public function scopeStatus( Builder $query, $status ) {
		return $query->where( 'status', $status );
	}


}
