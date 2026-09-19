<?php

namespace Nabik\Gateland\Plugins\PMP;

use PMProGateway_gateland;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {

		if ( ! class_exists( 'PMProGateway' ) ) {
			return;
		}

		require_once 'Gateway.php';

		new PMProGateway_gateland();

		add_filter( 'pmpro_currencies', [ $this, 'add_currencies' ] );
		add_filter( 'pmpro_gateways', [ $this, 'add_gateways' ] );
	}

	public function add_currencies( array $currencies ): array {

		$currencies['IRR'] = [
			'name'                => 'ریال ایران (ریال)',
			'decimals'            => 0,
			'thousands_separator' => ',',
			'decimal_separator'   => '',
			'symbol'              => '&nbsp;ریال',
			'position'            => 'right',
		];

		$currencies['IRHR'] = [
			'name'                => 'هزار ریال ایران (هزار ریال)',
			'decimals'            => 0,
			'thousands_separator' => ',',
			'decimal_separator'   => '',
			'symbol'              => '&nbsp;هزار ریال',
			'position'            => 'right',
		];

		$currencies['IRT'] = [
			'name'                => 'تومان ایران (تومان)',
			'decimals'            => 0,
			'thousands_separator' => ',',
			'decimal_separator'   => '',
			'symbol'              => '&nbsp;تومان',
			'position'            => 'right',
		];

		$currencies['IRHT'] = [
			'name'                => 'هزار تومان ایران (هزار تومان)',
			'decimals'            => 0,
			'thousands_separator' => ',',
			'decimal_separator'   => '',
			'symbol'              => '&nbsp;هزار تومان',
			'position'            => 'right',
		];

		return $currencies;
	}

	public function add_gateways( array $gateways ): array {

		$gateways['gateland'] = 'گیت‌لند';

		return $gateways;
	}
}
