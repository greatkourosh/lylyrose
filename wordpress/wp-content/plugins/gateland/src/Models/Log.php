<?php

namespace Nabik\Gateland\Models;

use Illuminate\Database\Eloquent\Model;

defined( 'ABSPATH' ) || exit;

/**
 * @property int    $id
 * @property string $ray_id
 * @property string $event
 * @property int    $transaction_id
 * @property array  $data
 * @property string $created_at
 */
class Log extends Model {

	protected $table = 'gateland_logs';

	protected $fillable = [
		'ray_id',
		'event',
		'transaction_id',
		'data',
	];

	protected $casts = [
		'data' => 'array',
	];

	const UPDATED_AT = null;

	public static string $ray_id = '';

	public function save( array $options = [] ) {

		if ( empty( self::$ray_id ) ) {
			self::$ray_id = wp_generate_uuid4();
		}

		$this->ray_id = self::$ray_id;

		if ( get_current_user_id() ) {
			$data            = $this->data;
			$data['user_id'] = get_current_user_id();
			$this->data      = $data;
		}

		return parent::save( $options );
	}

	// Relations

	public function transaction() {
		return $this->belongsTo( Transaction::class );
	}
}
