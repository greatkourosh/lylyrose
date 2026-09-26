<?php

namespace Nabik\Gateland\Plugins\Bookly;


use Nabik\Gateland\Services\GatewayService;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RequestStack;

class Load {
	protected static ?Load $_instance = null;

	public static function instance(): ?Load {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {

		if ( ! class_exists( \BooklyPro\Lib\Plugin::class ) ) {
			return;
		}

		$this->add_currency();
		$this->modify_payments_enum();

		PaymentAjax::init();
		PaymentProxy::init();
		BookingProxy::init();
		SettingsProxy::init();
		AppearanceProxy::init();
	}

	public function add_currency() {
		$reflection_class = new ReflectionClass( \Bookly\Lib\Utils\Price::class );
		$property         = $reflection_class->getProperty( 'currencies' );
		$property->setAccessible( true );
		$currency        = $property->getValue();
		$currency['IRT'] = [ 'symbol' => 'تومان', 'format' => '{price} {symbol}' ];
		$property->setValue( null, $currency );
	}

	public function modify_payments_enum() {
		global $wpdb;

		$table  = $wpdb->prefix . 'bookly_payments';
		$column = 'type';

		$col = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM `$table` LIKE %s", $column ) );

		if ( ! $col ) {
			throw new \RuntimeException( "ستون  `$column` در جدول `$table` یافت نشد." );
		}

		if ( preg_match( "/^enum\((.*)\)$/i", $col->Type, $match ) ) {

			$raw    = $match[1];
			$values = array_map(
				function ( $v ) {
					return trim( $v, " '\"" );
				},
				explode( ',', $raw )
			);

			if ( in_array( PaymentEntity::TYPE_GATELAND, $values, true ) ) {
				return;
			}

			$values[] = PaymentEntity::TYPE_GATELAND;

			$escaped = implode(
				',',
				array_map(
					function ( $v ) {
						return "'" . esc_sql( $v ) . "'";
					},
					$values
				)
			);

			$sql = "ALTER TABLE `$table` MODIFY `$column` ENUM($escaped) NOT NULL DEFAULT 'local'";

			$wpdb->query( $sql );

		} else {

			throw new \RuntimeException( "ستون `$column` از نوع انتخاب محدود، نیست." );

		}

	}

	public static function gateways() {
		$gateways = [ [ 0, 'درگاه پرداخت هوشمند آنلاین' ] ];

		foreach ( GatewayService::activated() as $gateway ) {
			$gateways[] = [ $gateway['id'], $gateway['name'] ];
		}

		return $gateways;
	}
}