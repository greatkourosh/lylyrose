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
		add_action( 'admin_print_footer_scripts', [ $this, 'panel_scripts' ] );
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

	public function panel_scripts() {
		$screen_id = function_exists( 'get_current_screen' ) ? get_current_screen()->id : null;

		if ( ! $screen_id || ! str_contains( $screen_id, 'page_wpcf7' ) ) {
			return;
		}

		?>
		<script>
            jQuery(document).ready(function ($) {
                $(document).on('change', '#gateland_price_tag', function (e) {

                    if (this.value === '___') {
                        $('#gateland_price').parents('p').show();
                    } else {
                        $('#gateland_price').parents('p').hide();
                    }
                });
            });
		</script>

		<style>
            #gateland_form, #gateland_form h2, #contact-form-editor-tabs a {
                font-family: IRANYekanX, Vazirmatn, Sahel, serif !important;
            }

            #gateland_form input[type=number], #gateland_form select {
                min-width: 50%;
            }
		</style>
		<?php
	}
}
