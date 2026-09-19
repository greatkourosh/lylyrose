<?php

namespace Nabik\Gateland\Models;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Carbon\Carbon;
use GFAPI;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Gateland;
use Nabik\Gateland\Helpers\SQID;

/**
 * Class Transaction
 *
 * @package Nabik\Gateland\Models
 *
 * @property integer $id
 * @property int     $amount
 * @property string  $currency
 * @property string  $callback
 * @property string  $description
 * @property integer $order_id
 * @property string  $ip
 * @property string  $email
 * @property string  $gateway_trans_id
 * @property string  $gateway_au
 * @property string  $gateway_status
 * @property string  $status
 * @property string  $card_number
 * @property array   $allowed_cards
 * @property string  $national_code
 * @property string  $gateway_callback
 * @property string  $sign
 * @property string  $token
 * @property string  $mobile
 * @property int     $user_id
 * @property string  $client
 * @property string  $client_label
 * @property string  $gateway_label
 * @property integer $gateway_id
 * @property array   $meta
 * @property Carbon  $created_at
 * @property Carbon  $updated_at
 * @property Carbon  $paid_at
 * @property Carbon  $verified_at
 *
 * @property Gateway $gateway
 * @property Refund  $refund
 *
 */
class Transaction extends Model {

	// Attributes
	protected $table = 'gateland_transactions';

	protected $casts = [
//		'currency'      => CurrenciesEnum::class,
//		'status'        => StatusesEnum::class,
		'allowed_cards' => 'array',
		'paid_at'       => 'datetime',
		'verified_at'   => 'datetime',
	];

	protected $fillable = [
		'amount',
		'currency',
		'callback',
		'description',
		'order_id',
		'ip',
		'email',
		'gateway_trans_id',
		'gateway_au',
		'gateway_status',
		'status',
		'card_number',
		'allowed_cards',
		'national_code',
		'mobile',
		'user_id',
		'client',
		'gateway_id',
		'meta',
		'paid_at',
		'verified_at',
	];

	public const CLIENT_WOOCOMMERCE = 'woocommerce';
	public const CLIENT_CF7 = 'cf7';
	public const CLIENT_RCP = 'rcp';
	public const CLIENT_EDD = 'edd';
	public const CLIENT_GF = 'gf';
	public const CLIENT_GIVE = 'give';
	public const CLIENT_WPUF = 'wpuf';
	public const CLIENT_PMP = 'pmp';
	public const CLIENT_MYCRED = 'mycred';
	public const CLIENT_WPFORMS = 'wpforms';
	public const CLIENT_LP = 'learnpress';
	public const CLIENT_LD = 'learndash';
	public const CLIENT_SI = 'sliced_invoices';
	public const CLIENT_BOOKLY = 'bookly';

	public static function getClients(): array {

		return apply_filters( 'nabik/gateland/transaction_clients', [
			self::CLIENT_WOOCOMMERCE => 'ووکامرس',
			self::CLIENT_CF7         => 'فرم تماس ۷',
			self::CLIENT_RCP         => 'اشتراک ویژه',
			self::CLIENT_EDD         => 'فروش فایل',
			self::CLIENT_GF          => 'گرویتی فرمز',
			self::CLIENT_GIVE        => 'دونیت',
			self::CLIENT_WPUF        => 'ناحیه کاربری',
			self::CLIENT_PMP         => 'عضویت ویژه',
			self::CLIENT_MYCRED      => 'امتیاز من',
			self::CLIENT_WPFORMS     => 'WP Forms',
			self::CLIENT_LP          => 'لرن‌پرس',
			self::CLIENT_LD          => 'لرن‌دش',
			self::CLIENT_SI          => 'Sliced Invoices',
			self::CLIENT_BOOKLY      => 'بوکلی',
		] );

	}

	public function save( array $options = [] ) {

		if ( isset( $this->getDirty()['status'] ) ) {
			do_action( 'nabik/gateland/transaction_status_changed', $this->getOriginal( 'status' ), $this->getDirty()['status'], $this );
		}

		return parent::save( $options );
	}

	// Functions

	public function getClientLabelAttribute(): string {
		$clients = $this->getClients();

		return $clients[ $this->client ] ?? $this->client;
	}

	public function getGatewayLabelAttribute(): string {

		if ( is_null( $this->gateway ) ) {
			return '-';
		}

		return $this->gateway->build()->name();
	}

	public function getMetaAttribute( $value ): array {

		if ( is_null( $value ) ) {
			return [];
		}

		return json_decode( $value, true ) ?? [];
	}

	public function setMetaAttribute( ?array $value ) {

		if ( is_null( $value ) ) {
			$value = [];
		}

		$meta = $value + $this->getMetaAttribute( $this->attributes['meta'] ?? null );

		$this->attributes['meta'] = empty( $meta ) ? null : json_encode( $meta );
	}

	public function getSignAttribute(): string {
		return substr( sha1( $this->id . AUTH_KEY ), 14, 8 );
	}

	public function getTokenAttribute(): string {
		return SQID::encode( $this->id );
	}

	public function getGatewayCallbackAttribute(): string {
		return add_query_arg( [ 'sign' => $this->sign ], rest_url( 'gateland/payment/' . $this->token . '/callback' ) );
	}

	public function isExpired(): bool {
		return $this->isPending() && $this->created_at->addHour()->isPast();
	}

	public function isPending(): bool {
		return $this->status == StatusesEnum::STATUS_PENDING;
	}

	public function isPaid(): bool {
		return $this->status == StatusesEnum::STATUS_PAID && ! is_null( $this->paid_at );
	}

	public function isVerified(): bool {
		return ! is_null( $this->verified_at );
	}

	/**
	 * @return array
	 */
	public static function getStatusesWithCount(): array {
		return Transaction::query()
		                  ->select( 'status', DB::raw( 'count(*) as total' ) )
		                  ->groupBy( 'status' )
		                  ->pluck( 'total', 'status' )
		                  ->toArray();
	}

	/**
	 * Get order based on client
	 * @return array
	 */
	public function getClientOrder(): array {

		$client_order = [];

		if ( $this->client === self::CLIENT_WOOCOMMERCE ) {

			if ( ! function_exists( 'wc_get_order' ) ) {
				return [];
			}

			$order = wc_get_order( $this->order_id );

			$client_order = [
				'first_name'   => $order->get_billing_first_name(),
				'last_name'    => $order->get_billing_last_name(),
				'phone_number' => $order->get_billing_phone(),
			];
		}

		if ( $this->client == self::CLIENT_GF ) {

			$entry = GFAPI::get_entry( $this->order_id );

			if ( is_array( $entry ) ) {

				$form = GFAPI::get_form( $entry['form_id'] );

				$client_order = [
					'first_name'   => $entry[ $form['gateland_first_name_field'] . '.3' ] ?? $entry[ $form['gateland_first_name_field'] ] ?? null,
					'last_name'    => $entry[ $form['gateland_last_name_field'] . '.6' ] ?? $entry[ $form['gateland_last_name_field'] ] ?? null,
					'phone_number' => $entry[ $form['gateland_phone_field'] ] ?? null,
				];

			}
		}

		return apply_filters( 'nabik/gateland/transaction_client_order', $client_order, $this );
	}

	public function getClientOrderUrl() {

		$url = '';

		if ( $this->client === self::CLIENT_WOOCOMMERCE && class_exists( OrderUtil::class ) ) {
			$url = OrderUtil::get_order_admin_edit_url( $this->order_id );
		}

		if ( $this->client == self::CLIENT_GF && class_exists( GFAPI::class ) ) {

			$entry = GFAPI::get_entry( $this->order_id );

			if ( is_array( $entry ) ) {
				$path = sprintf( 'admin.php?page=gf_entries&view=entry&id=%d&lid=%d', $entry['form_id'], $entry['id'] );
				$url  = admin_url( $path );
			}
		}

		if ( $this->client == self::CLIENT_GIVE ) {
			$path = sprintf( 'edit.php?post_type=give_forms&page=give-payment-history&view=view-payment-details&id=%d', $this->order_id );
			$url  = admin_url( $path );
		}

		if ( $this->client == self::CLIENT_WPUF ) {
			$url = admin_url( 'admin.php?page=wpuf_transaction' );
		}

		if ( $this->client == self::CLIENT_LP ) {
			$path = sprintf( 'post.php?post=%d&action=edit', $this->order_id );
			$url  = admin_url( $path );
		}

		return apply_filters( 'nabik/gateland/transaction_client_order_url', $url, $this );
	}

	public function getReceiptURL(): string {
		return admin_url( 'admin.php?page=gateland-transaction&transaction_id=' . $this->id );
	}

	public function getPayURL(): string {
		return rest_url( 'gateland/payment/' . $this->token . '/start' );
	}

	public function getPrettyPayURL(): string {
		$prefix = Gateland::get_option( 'sms.pay_link', 'pay' );

		return site_url( $prefix . '/' . $this->token );
	}

	// Relations

	public function gateway() {
		return $this->belongsTo( Gateway::class );
	}

	public function logs() {
		return $this->hasMany( Log::class );
	}

	public function refund() {
		return $this->hasOne( Refund::class );
	}
}
