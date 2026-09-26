<?php

namespace Nabik\Gateland\Plugins\Give;

use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\PaymentGateway;

class Gateway extends PaymentGateway {

	public $gateway_id = null;

	public static function id(): string {
		return 'gateland';
	}

	public function getId(): string {
		return self::id();
	}

	public function getName(): string {
		return 'Give1';
		// TODO: Implement getName() method.
	}

	public function getPaymentMethodLabel(): string {
		return 'Give2';
		// TODO: Implement getPaymentMethodLabel() method.
	}

	public function createPayment( Donation $donation, $gatewayData ) {
		// TODO: Implement createPayment() method.
	}

	public function refundDonation( Donation $donation ) {
		// TODO: Implement refundDonation() method.
	}

	public function getLegacyFormFieldMarkup() {

	}

	public function enqueueScript( int $formId ) {
		//wp_enqueue_scripts();
	}
}