<?php
/**
 * Iranian National ID (کد ملی) checkout field with validation.
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_National_ID {

	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_field' ), 20 );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'admin_field' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_to_order' ), 10, 2 );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_field' ), 10, 3 );
		add_filter( 'woocommerce_checkout_redirect_empty_cart', '__return_false' );

		// Block (React) checkout uses a separate field-registration API.
		if ( function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			add_action( 'woocommerce_init', array( $this, 'register_block_field' ) );
		}
	}

	/**
	 * Register the same field for the block-based checkout.
	 */
	public function register_block_field() {
		woocommerce_register_additional_checkout_field(
			array(
				'id'         => 'lylyrose-core/national-id',
				'label'      => __( 'کد ملی', 'lylyrose-core' ),
				'location'   => 'address',
				'type'       => 'text',
				'required'   => true,
				'attributes' => array(
					'maxLength' => 10,
					'inputMode' => 'numeric',
				),
			)
		);
	}

	/**
	 * Add the national-ID field to billing fields (required for physical delivery).
	 */
	public function add_field( $fields ) {
		$fields['billing']['billing_national_id'] = array(
			'label'        => __( 'کد ملی', 'lylyrose-core' ),
			'required'     => true,
			'class'        => array( 'form-row-first' ),
			'priority'     => 65,
			'custom_attributes' => array(
				'maxlength' => '10',
				'inputmode' => 'numeric',
				'pattern'   => '[0-9]{10}',
			),
		);
		return $fields;
	}

	public function admin_field( $fields ) {
		$fields['national_id'] = array(
			'label' => __( 'کد ملی', 'lylyrose-core' ),
		);
		return $fields;
	}

	public function save_to_order( $order, $data ) {
		if ( ! empty( $data['billing_national_id'] ) ) {
			$order->update_meta_data( '_billing_national_id', sanitize_text_field( $data['billing_national_id'] ) );
		}
	}

	public function email_field( $fields, $sent_to_admin, $order ) {
		$v = $order->get_meta( '_billing_national_id' );
		if ( $v ) {
			$fields['billing_national_id'] = array( 'label' => __( 'کد ملی', 'lylyrose-core' ), 'value' => $v );
		}
		return $fields;
	}

	/**
	 * Validate Iranian national ID checksum (mod-11 algorithm).
	 */
	public static function is_valid( $code ) {
		$code = preg_replace( '/[^0-9]/', '', (string) $code );

		if ( strlen( $code ) !== 10 || preg_match( '/^([0-9])\1{9}$/', $code ) ) {
			return false;
		}

		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += ( (int) $code[ $i ] ) * ( 10 - $i );
		}
		$remainder = $sum % 11;
		$check     = (int) $code[9];

		return ( $remainder < 2 ) ? ( $check === $remainder ) : ( $check === 11 - $remainder );
	}
}
