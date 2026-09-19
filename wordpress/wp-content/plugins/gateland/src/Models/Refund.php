<?php

namespace Nabik\Gateland\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Refund
 *
 * @package Nabik\Gateland\Models
 *
 * @property int         $id
 * @property int         $amount
 * @property string      $description
 * @property int         $refund_id
 * @property int         $transaction_id
 * @property int         $user_id
 * @property Carbon      $created_at
 *
 * @property Transaction $transaction
 */
class Refund extends Model {
	use HasFactory;

	protected $table = 'gateland_refunds';

	// Const

	public const UPDATED_AT = null;

	// Attributes

	protected $fillable = [
		'amount',
		'description',
		'refund_id',
		'transaction_id',
		'user_id',
	];

	public function save( array $options = [] ) {

		$this->user_id = get_current_user_id();

		return parent::save( $options );
	}

	// Relations

	public function transaction() {
		return $this->belongsTo( Transaction::class );
	}

}
