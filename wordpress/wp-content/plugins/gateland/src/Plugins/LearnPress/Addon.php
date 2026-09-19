<?php

namespace Nabik\Gateland\Plugins\LearnPress;

use LP_Addon;

class Addon extends LP_Addon {

	public $version = GATELAND_VERSION;

	public $require_version = '4.0.0';

	public $text_domain = 'gateland';

	public $plugin_file = __FILE__;

	public function __construct() {

		parent::__construct();

		$this->_includes();
		$this->_init_hooks();
	}

	protected function _includes() {
		require_once 'Gateway.php';
	}

	protected function _init_hooks() {
		Gateway::webhook();
		add_filter( 'learn-press/payment-methods', [ $this, 'add_payment' ] );
	}

	public function add_payment( array $methods ): array {

		$methods['gateland'] = Gateway::class;

		return $methods;
	}
}