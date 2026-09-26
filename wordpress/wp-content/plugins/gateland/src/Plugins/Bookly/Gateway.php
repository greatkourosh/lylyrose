<?php

namespace Nabik\Gateland\Plugins\Bookly;

use Bookly\Lib\Config;
use Bookly\Lib\Entities\CustomerAppointment;
use Bookly\Lib\Entities\Order;
use Bookly\Lib\Entities\Payment;
use Nabik\Gateland\Models\Transaction;
use Nabik\Gateland\Pay;

class Gateway extends \Bookly\Lib\Base\Gateway {
	protected $type = PaymentEntity::TYPE_GATELAND;
	protected $on_site = false;

	protected function createPayment() {

		if ( $this->payment || $this->request->getGateway()->getType() === null ) {
			return true;
		}

		$this->payment = new PaymentEntity();

		return $this->payment
			->setCartInfo( $this->request->getCartInfo() )
			->setType( PaymentEntity::TYPE_GATELAND )
			->save();
	}

	public function isOnSite() {
		return false;
	}

	protected function createGatewayIntent() {
		$user_id = wp_get_current_user()->ID;

		$data = [
			'amount'      => $this->getGatewayAmount(),
			'client'      => Transaction::CLIENT_BOOKLY,
			'user_id'     => $user_id,
			'order_id'    => $this->getPayment()->getId(),
			'callback'    => $this->getResponseUrl( self::EVENT_RETRIEVE ),
			'description' => $this->request->getUserData()->cart->getItemsTitle( 126 ),
			'email'       => $this->request->getUserData()->getEmail(),
			'mobile'      => $this->request->getUserData()->getPhone(),
			'currency'    => Config::getCurrency(),
		];

		$gateway = get_option( 'bookly_gateland_selected_gateway', '0' );

		if ( $gateway != '0' ) {
			$data['gateway_id'] = $gateway;
		}

		try {
			$response = Pay::request( $data );
		} catch ( \Exception $e ) {
			$message = 'Gateland: Bookly cant request payment -> ' . $e->getMessage();
			throw new \Exception( $message );
		}

		if ( ! isset( $response['success'] ) || ! boolval( $response['success'] ) ) {
			$message = sprintf( 'خطایی در زمان ارتباط با درگاه پرداخت رخ داده است: %s', $response['message'] );
			throw new \Exception( $response['message'] ?? $message );
		}

		$this->getPayment()->setRefId( $response['data']['authority'] )->setType( PaymentEntity::TYPE_GATELAND )->save();

		return [
			'ref_id'       => $response['data']['authority'],
			'checkout_url' => $response['data']['payment_link'],
			'type'         => PaymentEntity::TYPE_GATELAND
		];
	}


	public function retrieve() {
		if ( ! $this->payment ) {

			$order_id = Order::query()
			                 ->where( 'token', $this->request->get( 'bookly_order' ) )
			                 ->fetchVar( 'id' );

			if ( $order_id ) {

				$payment = Payment::query()
				                  ->where( 'order_id', $order_id )
				                  ->findOne();

				$this->setPayment( $payment->setType( PaymentEntity::TYPE_GATELAND ) );

			}

		}

		if ( ! $this->payment ) {
			return Payment::STATUS_REJECTED;
		}

		return parent::retrieve();
	}

	public function retrieveStatus() {

		$payment_id = $this->getPayment()->getRefId();

		$response = Pay::verify( $payment_id, Transaction::CLIENT_BOOKLY );

		if ( ( isset( $response['success'] ) && $response['success'] ) && $this->validatePaymentData( $response['data']['amount'], Config::getCurrency() ) ) {
			return Payment::STATUS_COMPLETED;
		}

		return Payment::STATUS_REJECTED;
	}

	public function createCheckout() {
		try {
			return [
				'target_url'   => $this->getCheckoutUrl( $this->createIntent() ),
				'bookly_order' => Order::find( $this->order->getOrderId() )->getToken(),
			];
		} catch ( \Exception $e ) {
			$this->fail();
			throw $e;
		}
	}

	protected function getCheckoutUrl( array $intent_data ) {
		return $intent_data['checkout_url'];
	}

	public function getInternalMetaData() {
		return [];
	}
}