<?php

namespace Nabik\Gateland\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Receipt
 *
 * @package Nabik\Gateland\Models
 *
 * @property int         $id
 * @property int         $transaction_id
 * @property int         $card_id
 * @property int         $attachment_id
 * @property string      $attachment_url
 * @property string      $card_number
 * @property string      $formatted_card_number
 * @property string      $tracking_number
 * @property int         $amount
 * @property int         $accepted_amount
 * @property string      $status
 * @property int         $reviewed_by
 * @property array       $meta
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property Carbon      $reviewed_at
 *
 * @property Transaction $transaction
 * @property Card        $card
 */
class Receipt extends Model {

	protected $table = 'gateland_receipts';

	// Attributes

	protected $fillable = [
		'transaction_id',
		'card_id',
		'attachment_id',
		'card_number',
		'tracking_number',
		'amount',
		'accepted_amount',
		'status',
		'reviewed_by',
		'meta',
		'reviewed_at',
	];

	protected $casts = [
		'meta'        => 'array',
		'reviewed_at' => 'datetime',
	];

	public function getAttachmentUrlAttribute(): string {
		return wp_get_attachment_url( $this->attachment_id );
	}

	public function getFormattedCardNumberAttribute(): string {
		return implode( '-', str_split( $this->card_number, 4 ) );
	}

	public function save( array $options = [] ) {

		if ( isset( $this->getDirty()['status'] ) ) {
			do_action( 'nabik/gateland/receipt_status_changed', $this->getOriginal( 'status' ), $this->getDirty()['status'], $this );
		}

		return parent::save( $options );
	}

	public function transaction() {
		return $this->belongsTo( Transaction::class );
	}

	public function card() {
		return $this->belongsTo( Card::class );
	}

}