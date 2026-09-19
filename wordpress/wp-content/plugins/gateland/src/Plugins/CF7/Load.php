<?php

namespace Nabik\Gateland\Plugins\CF7;

use WPCF7_ContactForm;
use WPCF7_FormTag;

class Load {

	protected static ?Load $_instance = null;

	public static function instance(): ?Load {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {

		new Gateway();

		add_filter( 'wpcf7_editor_panels', [ $this, 'panel_menu' ] );
		add_filter( 'wpcf7_save_contact_form', [ $this, 'save_settings' ], 10, 3 );
	}

	public function panel_menu( array $panels ): array {
		return array_merge( $panels, [
			'Gateland' => [
				'title'    => 'گیت‌لند',
				'callback' => [ $this, 'panel_callback' ],
			],
		] );
	}

	public function panel_callback( WPCF7_ContactForm $form ) {

		$price_tags = [];
		$email_tags = [];
		$phone_tags = [];

		/** @var WPCF7_FormTag[] $form_tags */
		$form_tags = $form->scan_form_tags();

		foreach ( $form_tags as $tag ) {

			if ( in_array( $tag->basetype, [ 'text', 'number', 'menu', 'radio' ] ) ) {
				$price_tags[ $tag->raw_name ] = "{$tag->type}:{$tag->raw_name}";
			}

			if ( in_array( $tag->basetype, [ 'text', 'email' ] ) ) {
				$email_tags[ $tag->raw_name ] = "{$tag->type}:{$tag->raw_name}";
			}

			if ( in_array( $tag->basetype, [ 'text', 'tel' ] ) ) {
				$phone_tags[ $tag->raw_name ] = "{$tag->type}:{$tag->raw_name}";
			}

		}

		$options = Gateway::get_options( $form );

		include GATELAND_DIR . '/templates/cf7/form-panel.php';
	}

	public function save_settings( WPCF7_ContactForm $form, array $args, string $context ) {
		Gateway::set_options( $form, $args['gateland'] );
	}
}
