<?php
/**
 * Structured perfume data (P2 #13): fragrance note pyramid.
 *
 * Adds a product-edit meta box with three note layers — top (نوت آغازین),
 * heart (نوت میانی), base (نوت پایه) — one note per line. Renders a
 * «هرم رایحه» card on the single product page when at least one layer
 * has notes. In-house fields, no ACF dependency.
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_Fragrance_Notes {

	private static function layers() {
		return array(
			'top'   => __( 'نوت آغازین (بالایی)', 'lylyrose-core' ),
			'heart' => __( 'نوت میانی', 'lylyrose-core' ),
			'base'  => __( 'نوت پایه', 'lylyrose-core' ),
		);
	}

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ), 10, 1 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_pyramid' ), 45 );
	}

	/**
	 * Meta box under the product data panel.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'asc-fragrance-notes',
			__( 'هرم رایحه (نوت‌های عطر)', 'lylyrose-core' ),
			array( __CLASS__, 'render_meta_box' ),
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Admin meta box: one textarea per layer, one note per line.
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( 'asc_fragrance_notes_save', 'asc_fragrance_notes_nonce' );

		echo '<style>
			.asc-notes-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
			.asc-notes-grid label { font-weight: 600; display: block; margin-bottom: 4px; }
			.asc-notes-grid textarea { width: 100%; min-height: 90px; }
			.asc-notes-grid .description { margin-top: 4px; }
			@media (max-width: 900px) { .asc-notes-grid { grid-template-columns: 1fr; } }
		</style>';
		echo '<div class="asc-notes-grid">';
		foreach ( self::layers() as $key => $label ) {
			$value = get_post_meta( $post->ID, '_asc_notes_' . $key, true );
			echo '<div>';
			echo '<label for="asc_notes_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
			echo '<textarea id="asc_notes_' . esc_attr( $key ) . '" name="asc_notes_' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>';
			echo '<p class="description">' . esc_html__( 'هر رایحه در یک خط. خالی = نمایش داده نمی‌شود.', 'lylyrose-core' ) . '</p>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Persist the three layers (products are posts, so postmeta is correct here).
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST['asc_fragrance_notes_nonce'] )
			|| ! wp_verify_nonce( $_POST['asc_fragrance_notes_nonce'], 'asc_fragrance_notes_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( array_keys( self::layers() ) as $key ) {
			$field = 'asc_notes_' . $key;
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$raw   = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
			$lines = array();
			foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$lines[] = $line;
				}
			}
			if ( $lines ) {
				update_post_meta( $post_id, '_asc_notes_' . $key, implode( "\n", $lines ) );
			} else {
				delete_post_meta( $post_id, '_asc_notes_' . $key );
			}
		}
	}

	/**
	 * Front-end «هرم رایحه» card on the single product page.
	 */
	public static function render_pyramid() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		global $product;
		if ( ! $product ) {
			return;
		}

		$layers = array();
		foreach ( array_keys( self::layers() ) as $key ) {
			$raw = get_post_meta( $product->get_id(), '_asc_notes_' . $key, true );
			if ( $raw ) {
				$notes = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
				if ( $notes ) {
					$layers[ $key ] = $notes;
				}
			}
		}
		if ( empty( $layers ) ) {
			return;
		}

		$icons  = array(
			'top'   => '🌿',
			'heart' => '🌸',
			'base'  => '🪵',
		);
		$titles = array(
			'top'   => __( 'نوت آغازین', 'lylyrose-core' ),
			'heart' => __( 'نوت میانی', 'lylyrose-core' ),
			'base'  => __( 'نوت پایه', 'lylyrose-core' ),
		);

		echo '<div class="dk-notes" style="margin-top:14px;border:1px solid #e0e0e0;border-radius:8px;padding:12px 14px;background:#fff">';
		echo '<h4 style="margin:0 0 10px;font-size:14px;font-weight:700">' . esc_html__( 'هرم رایحه', 'lylyrose-core' ) . '</h4>';
		foreach ( $layers as $key => $notes ) {
			echo '<div class="dk-notes-layer" style="display:flex;align-items:flex-start;gap:8px;margin-bottom:8px">';
			echo '<span style="flex-shrink:0">' . esc_html( $icons[ $key ] ) . '</span>';
			echo '<div><strong style="font-size:12.5px">' . esc_html( $titles[ $key ] ) . '</strong><br/>';
			echo '<span style="font-size:12.5px;color:#666">' . esc_html( implode( '، ', $notes ) ) . '</span></div>';
			echo '</div>';
		}
		echo '</div>';
	}
}
