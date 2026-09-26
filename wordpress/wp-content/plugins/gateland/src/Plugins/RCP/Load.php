<?php

namespace Nabik\Gateland\Plugins\RCP;


class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_filter( 'rcp_currencies', [ $this, 'currencies' ] );
		add_filter( 'rcp_irt_symbol', [ $this, 'irt_symbol' ] );
		add_filter( 'rcp_irr_symbol', [ $this, 'irr_symbol' ] );
		add_filter( 'rcp_irht_symbol', [ $this, 'irht_symbol' ] );
		add_filter( 'rcp_irhr_symbol', [ $this, 'irhr_symbol' ] );
		add_filter( 'rcp_is_zero_decimal_currency', '__return_true' );
		add_filter( 'rcp_payment_gateways', [ $this, 'register' ] );
	}

	public function currencies( array $currencies ): array {
		$currencies['IRT']  = 'تومان ایران (تومان)';
		$currencies['IRR']  = 'ریال ایران (ریال)';
		$currencies['IRHT'] = 'هراز تومان ایران (هزار تومان)';
		$currencies['IRHR'] = 'هزار ریال ایران (هزار ریال)';

		return $currencies;
	}

	public function irt_symbol(): string {
		return ' تومان';
	}

	public function irr_symbol(): string {
		return ' ریال';
	}

	public function irht_symbol(): string {
		return ' هزار تومان';
	}

	public function irhr_symbol(): string {
		return ' هزار ریال';
	}

	public function register( $gateways ) {

		unset( $gateways['stripe'] );

		$gateways['gateland'] = [
			'label'       => 'پرداخت آنلاین',
			'admin_label' => 'گیت‌لند',
			'class'       => \Nabik\Gateland\Plugins\RCP\Gateway::class,
		];

//      @todo find a solution, maybe anonymous class :)
//		foreach ( \Nabik\Gateland\Services\GatewayService::activated() as $gateway_id => $gateway ) {
//			$gateways[ 'gateland_' . $gateway_id ] = [
//				'label'       => $gateway['name'],
//				'admin_label' => $gateway['name'],
//				'class'       => \Nabik\Gateland\Plugins\RCP\Gateway::class,
//				'gateway_id'  => $gateway_id,
//			];
//		}

		return $gateways;
	}
}