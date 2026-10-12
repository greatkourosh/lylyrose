<?php
/**
 * Quiz options for «عطرت رو پیدا کن» (the perfume finder).
 *
 * Edits the choices the quiz offers, the longevity scale, and how many results
 * it returns. Removing an option only hides it from the quiz: the WooCommerce
 * attribute term stays, so products that carry it keep their data. Adding one
 * creates the term on the next init, through the existing register_terms().
 *
 * Weights are edited on their own page (ASC_Finder_Weights).
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_Finder_Settings {

	const VOCAB = 'asc_finder_vocab';
	const LONG  = 'asc_finder_longevity';
	const MAXR  = 'asc_finder_max_results';
	const GROUP = 'asc_finder_settings';
	const PAGE  = 'asc-finder-settings';
	const LIST_MAX = 30;

	/** Taxonomy => admin label. Gender and budget are not edited here. */
	public static function axes() {
		return array(
			'pa_fragrance_family' => __( 'خانواده رایحه', 'lylyrose-core' ),
			'pa_occasion'         => __( 'مناسبت', 'lylyrose-core' ),
			'pa_season'           => __( 'فصل', 'lylyrose-core' ),
			'pa_personality'      => __( 'شخصیت', 'lylyrose-core' ),
		);
	}

	public static function init() {
		add_filter( 'asc_finder_vocabularies', array( __CLASS__, 'filter_vocabularies' ) );
		add_filter( 'asc_finder_longevity_levels', array( __CLASS__, 'filter_longevity' ) );
		add_filter( 'asc_perfume_finder_max_results', array( __CLASS__, 'filter_max_results' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * Replace each edited axis with the saved list. Unsaved axes keep the code list.
	 */
	public static function filter_vocabularies( $vocab ) {
		$saved = get_option( self::VOCAB, array() );
		if ( ! is_array( $saved ) ) {
			return $vocab;
		}
		foreach ( $saved as $taxonomy => $terms ) {
			if ( isset( $vocab[ $taxonomy ] ) && is_array( $terms ) && $terms ) {
				$vocab[ $taxonomy ] = array_values( $terms );
			}
		}
		return $vocab;
	}

	public static function filter_longevity( $levels ) {
		$saved = get_option( self::LONG, array() );
		return is_array( $saved ) && $saved ? $saved : $levels;
	}

	public static function filter_max_results( $max ) {
		$saved = (int) get_option( self::MAXR, 0 );
		return $saved > 0 ? $saved : $max;
	}

	/**
	 * One option per textarea line, trimmed and de-duplicated. An empty list is
	 * dropped so the axis falls back to the code default instead of an empty quiz.
	 */
	public static function sanitize_vocab( $in ) {
		$out = array();
		foreach ( array_keys( self::axes() ) as $taxonomy ) {
			$lines = isset( $in[ $taxonomy ] ) ? (string) wp_unslash( $in[ $taxonomy ] ) : '';
			$terms = array();
			foreach ( preg_split( '/\R/u', $lines ) as $line ) {
				$name = sanitize_text_field( trim( $line ) );
				if ( '' !== $name && ! in_array( $name, $terms, true ) ) {
					$terms[] = $name;
				}
			}
			$terms = array_slice( $terms, 0, self::LIST_MAX );
			if ( $terms ) {
				$out[ $taxonomy ] = $terms;
			}
		}
		return $out;
	}

	/**
	 * Lines of «نام|سطح». Level is 1 to 10; a row without a usable level is skipped.
	 */
	public static function sanitize_longevity( $in ) {
		$out = array();
		foreach ( preg_split( '/\R/u', (string) wp_unslash( $in ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 !== count( $parts ) || '' === $parts[0] ) {
				continue;
			}
			$level = (int) $parts[1];
			if ( $level < 1 || $level > 10 ) {
				continue;
			}
			$out[ sanitize_text_field( $parts[0] ) ] = $level;
		}
		return array_slice( $out, 0, self::LIST_MAX, true );
	}

	public static function sanitize_max( $in ) {
		return min( 10, max( 1, (int) $in ) );
	}

	public static function admin_menu() {
		add_options_page(
			__( 'گزینههای عطرت رو پیدا کن', 'lylyrose-core' ),
			__( 'گزینههای کوییز عطر', 'lylyrose-core' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function admin_init() {
		register_setting( self::GROUP, self::VOCAB, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_vocab' ), 'default' => array() ) );
		register_setting( self::GROUP, self::LONG, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize_longevity' ), 'default' => array() ) );
		register_setting( self::GROUP, self::MAXR, array( 'type' => 'integer', 'sanitize_callback' => array( __CLASS__, 'sanitize_max' ), 'default' => 0 ) );
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$vocab = ASC_Perfume_Finder::vocabularies();
		$long  = ASC_Perfume_Finder::longevity_levels();
		$max   = (int) ASC_Perfume_Finder::MAX_RESULTS;
		$effective_max = (int) apply_filters( 'asc_perfume_finder_max_results', ASC_Perfume_Finder::MAX_RESULTS );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'گزینههای کوییز عطر', 'lylyrose-core' ); ?></h1>
			<p><?php esc_html_e( 'هر خط یک گزینه است. حذف یک خط فقط آن گزینه را از کوییز برمیدارد؛ ترم ووکامرس و محصولاتی که آن را دارند دست نمیخورد. گزینه جدید بعد از ذخیره ساخته میشود و میتواند به محصولات نسبت داده شود.', 'lylyrose-core' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'گزینههای هر معیار', 'lylyrose-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( self::axes() as $taxonomy => $label ) : ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_html( $label ); ?></label><br><code dir="ltr"><?php echo esc_html( $taxonomy ); ?></code></th>
							<td>
								<textarea id="<?php echo esc_attr( $taxonomy ); ?>" rows="6" cols="40" dir="rtl" name="<?php echo esc_attr( self::VOCAB ); ?>[<?php echo esc_attr( $taxonomy ); ?>]"><?php echo esc_textarea( implode( "\n", (array) ( $vocab[ $taxonomy ] ?? array() ) ) ); ?></textarea>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'سطوح ماندگاری', 'lylyrose-core' ); ?></h2>
				<p class="description"><?php esc_html_e( 'هر خط به صورت «نام|سطح» با سطح ۱ تا ۱۰. سطح بالاتر یعنی ماندگاری بیشتر.', 'lylyrose-core' ); ?></p>
				<textarea rows="5" cols="40" dir="rtl" name="<?php echo esc_attr( self::LONG ); ?>"><?php
					$lines = array();
					foreach ( $long as $name => $level ) {
						$lines[] = $name . '|' . (int) $level;
					}
					echo esc_textarea( implode( "\n", $lines ) );
				?></textarea>

				<h2><?php esc_html_e( 'تعداد نتیجه', 'lylyrose-core' ); ?></h2>
				<p>
					<input type="number" min="1" max="10" class="small-text" name="<?php echo esc_attr( self::MAXR ); ?>" value="<?php echo (int) $effective_max; ?>">
					<span class="description"><?php printf( esc_html__( 'پیشفرض کد: %d. بین ۱ تا ۱۰.', 'lylyrose-core' ), $max ); ?></span>
				</p>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
