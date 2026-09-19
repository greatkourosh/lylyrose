<?php

namespace Nabik\Gateland\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Card
 *
 * @package Nabik\Gateland\Models
 *
 * @property int       $id
 * @property string    $name
 * @property string    $card_number
 * @property string    $formatted_card_number
 * @property string    $status
 * @property int       $max_quantity
 * @property int       $max_amount
 * @property boolean   $is_failover
 * @property Carbon    $created_at
 * @property Carbon    $updated_at
 *
 * @property string    $bank_name
 *
 * @property Receipt[] $receipts
 *
 */
class Card extends Model {

	protected $table = 'gateland_cards';

	// Attributes

	protected $fillable = [
		'name',
		'card_number',
		'status',
		'max_quantity',
		'max_amount',
		'is_failover',
	];

	protected $casts = [
		'is_failover' => 'boolean',
	];

	public array $banks = [
		'636795' => 'بانک مرکزی جمهوری اسلامی ایران',
		'603799' => 'بانک ملی ایران',
		'589210' => 'بانک سپه',
		'610433' => 'بانک ملت',
		'991975' => 'بانک ملت',
		'603769' => 'بانک صادرات ایران',
		'627353' => 'بانک تجارت',
		'585983' => 'بانک تجارت',
		'603770' => 'بانک کشاورزی',
		'639217' => 'بانک کشاورزی',
		'628023' => 'بانک مسکن',
		'589463' => 'بانک رفاه کارگران',
		'627648' => 'بانک توسعه صادرات ایران',
		'207177' => 'بانک توسعه صادرات ایران',
		'627961' => 'بانک صنعت و معدن',
		'627760' => 'پست بانک ایران',
		'502908' => 'بانک توسعه تعاون',
		'627412' => 'بانک اقتصاد نوین',
		'622106' => 'بانک پارسیان',
		'639194' => 'بانک پارسیان',
		'627884' => 'بانک پارسیان',
		'502229' => 'بانک پاسارگاد',
		'639347' => 'بانک پاسارگاد',
		'627488' => 'بانک کارآفرین',
		'502910' => 'بانک کارآفرین',
		'621986' => 'بانک سامان',
		'639346' => 'بانک سینا',
		'639607' => 'بانک سرمایه',
		'502806' => 'بانک شهر',
		'504706' => 'بانک شهر',
		'502938' => 'بانک دی',
		'636214' => 'بانک آینده',
		'505785' => 'بانک ایران زمین',
		'505416' => 'بانک گردشگری',
		'606373' => 'بانک قرض الحسنه مهر ایران',
		'504172' => 'بانک قرض الحسنه رسالت',
		'585947' => 'بانک خاورمیانه',
		'636949' => 'بانک حکمت ایرانیان',
		'639599' => 'بانک قوامین',
		'627381' => 'بانک انصار',
		'639370' => 'بانک مهر اقتصاد',
		'505801' => 'موسسه اعتباری کوثر',
		'606256' => 'موسسه اعتباری ملل',
		'507677' => 'موسسه اعتباری نور',
	];

	public function receipts() {
		return $this->hasMany( Receipt::class );
	}

	function getBankNameAttribute( $banks ): string {
		$prefix = substr( $this->card_number, 0, 6 );

		return $this->banks[ $prefix ] ?? '-';
	}

	public function getFormattedCardNumberAttribute(): string {
		return implode( '-', str_split( $this->card_number, 4 ) );
	}

	public static function isValidCardNumber( string $card_number ): bool {

		$sum    = 0;
		$length = strlen( $card_number );

		if ( $length != 16 ) {
			return false;
		}

		$isEven = false;

		for ( $i = $length - 1; $i >= 0; $i -- ) {
			$digit = (int) $card_number[ $i ];

			if ( $isEven ) {
				$digit *= 2;
				if ( $digit > 9 ) {
					$digit = ( $digit % 10 ) + 1;
				}
			}

			$sum    += $digit;
			$isEven = ! $isEven;
		}

		return ( $sum % 10 ) === 0;
	}

	/**
	 * @param string $IBAN International Bank Account Number
	 *
	 * @return void
	 */
	public static function isValidIBAN( string $IBAN ): bool {

		if ( strlen( $IBAN ) != 24 ) {
			return false;
		}

		$IBAN       = 'IR' . $IBAN;
		$rearranged = substr( $IBAN, 4 ) . substr( $IBAN, 0, 4 );

		$numeric = '';
		for ( $i = 0; $i < strlen( $rearranged ); $i ++ ) {
			$char = $rearranged[ $i ];
			if ( ctype_digit( $char ) ) {
				$numeric .= $char;
			} else {
				$numeric .= ( ord( $char ) - 55 );
			}
		}

		$mod = 0;
		for ( $i = 0; $i < strlen( $numeric ); $i ++ ) {
			$mod = (int) ( $mod . $numeric[ $i ] ) % 97;
		}

		return $mod === 1;
	}
}