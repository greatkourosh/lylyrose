<?php
/**
 * Scoring-weight editor for «عطرت رو پیدا کن» (the perfume finder).
 *
 * The finder's weights live in ASC_Perfume_Finder::weights(), behind a filter.
 * This class is the other end of that filter: an admin page so the owner can
 * retune the weighting without editing code.
 *
 * Nothing is stored until the owner saves, so until then the finder runs on its
 * own defaults and this class is inert — an empty option means "use the code
 * defaults", not "every axis is zero".
 *
 * Weights are renormalised to 100 on save. The finder divides `coverage` by the
 * weight total, so a set that did not sum to 100 would make the "based on N% of
 * the criteria" line wrong for every product.
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_Finder_Weights {

	const OPT   = 'asc_finder_weights';
	const GROUP = 'asc_finder_weights';

	/**
	 * Admin-facing names for the axes. Presentation only — the axis keys are the
	 * finder's, so this is a label map rather than a second source of truth.
	 */
	private static function labels() {
		return array(
			'fragrance'   => 'خانواده رایحه',
			'occasion'    => 'مناسبت',
			'season'      => 'فصل',
			'personality' => 'شخصیت',
			'longevity'   => 'ماندگاری',
			'budget'      => 'بودجه',
		);
	}

	public static function init() {
		add_filter( 'asc_finder_weights', array( __CLASS__, 'filter_weights' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * The saved weighting, renormalised. Returns the incoming weights untouched
	 * when the owner has never saved, so the finder keeps its own defaults and
	 * any other filter on this hook keeps its say.
	 */
	public static function filter_weights( $weights ) {
		$saved = get_option( self::OPT, array() );
		if ( ! is_array( $saved ) || ! $saved ) {
			return $weights;
		}
		$scaled = self::renormalise( $saved );
		// An all-zero weighting divides by zero downstream, which would score every
		// product 0% without saying why. The sanitizer rejects that save, but the
		// option is writable by anything with a DB handle — never return empty.
		return $scaled ? $scaled : $weights;
	}

	/**
	 * Clamp to the known axes and rescale to 100. Rounding the scaled values can
	 * leave the total at 99 or 101, so the drift is folded into the largest axis —
	 * otherwise `coverage` is off by a percent on every result.
	 */
	private static function renormalise( $in ) {
		$out = array();
		foreach ( self::labels() as $axis => $label ) {
			$out[ $axis ] = isset( $in[ $axis ] ) ? max( 0, min( 100, (int) $in[ $axis ] ) ) : 0;
		}

		$sum = array_sum( $out );
		if ( $sum <= 0 ) {
			return array(); // Every axis zeroed is not a weighting — fall back to defaults.
		}
		if ( 100 === $sum ) {
			return $out;
		}

		foreach ( $out as $axis => $value ) {
			$out[ $axis ] = (int) round( $value * 100 / $sum );
		}
		$drift   = 100 - array_sum( $out );
		$largest = array_search( max( $out ), $out, true );
		$out[ $largest ] = max( 0, $out[ $largest ] + $drift );

		return $out;
	}

	/* ---------------- Admin ---------------- */

	public static function admin_menu() {
		add_options_page(
			__( 'وزن‌دهی عطرت رو پیدا کن', 'lylyrose-core' ),
			__( 'وزن‌دهی عطرت رو پیدا کن', 'lylyrose-core' ),
			'manage_options',
			'asc-finder-weights',
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function admin_init() {
		register_setting(
			self::GROUP,
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Settings API sanitizer. The reset checkbox is a UI affordance, not a setting,
	 * so it deletes the option and leaves the finder's defaults in place.
	 */
	public static function sanitize( $in ) {
		if ( ! empty( $in['reset'] ) ) {
			delete_option( self::OPT );
			return array();
		}
		$weights = self::renormalise( wp_unslash( (array) $in ) );
		if ( ! $weights ) {
			// Saving all zeroes would renormalise to a division by zero, so the finder
			// treats an empty option as "never saved" and quietly ran on its code
			// defaults. Say so instead: the owner asked for a weighting, not for one.
			add_settings_error(
				self::OPT,
				'asc_finder_weights_zero',
				__( 'حداقل یک معیار باید وزنی بیشتر از صفر داشته باشد؛ وزن‌ها ذخیره نشد.', 'lylyrose-core' ),
				'error'
			);
		}
		return $weights ? $weights : array();
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved   = self::renormalise( (array) get_option( self::OPT, array() ) );
		$default = ASC_Perfume_Finder::weights();
		$labels  = self::labels();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'وزن‌دهی عطرت رو پیدا کن', 'lylyrose-core' ); ?></h1>
			<p><?php esc_html_e( 'هر معیار چقدر در نتیجه اثر بگذارد. اعداد به‌صورت خودکار به جمع ۱۰۰ نرمال می‌شوند، پس نسبت‌ها مهم‌اند نه مجموع عددها.', 'lylyrose-core' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $labels as $axis => $label ) :
						$value = $saved ? $saved[ $axis ] : $default[ $axis ];
						?>
						<tr>
							<th scope="row">
								<label for="asc_weight_<?php echo esc_attr( $axis ); ?>"><?php echo esc_html( $label ); ?></label>
							</th>
							<td>
								<input
									name="<?php echo esc_attr( self::OPT ); ?>[<?php echo esc_attr( $axis ); ?>]"
									id="asc_weight_<?php echo esc_attr( $axis ); ?>"
									type="number" min="0" max="100" step="1"
									value="<?php echo esc_attr( $value ); ?>"
									class="small-text"
									dir="ltr">
								<p class="description">
									<?php
									printf(
										/* translators: %d: the weight this axis has in the code defaults. */
										esc_html__( 'پیش‌فرض کد: %d', 'lylyrose-core' ),
										(int) $default[ $axis ]
									);
									?>
								</p>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'بازگشت به پیش‌فرض', 'lylyrose-core' ); ?></th>
						<td>
							<label>
								<input name="<?php echo esc_attr( self::OPT ); ?>[reset]" type="checkbox" value="1">
								<?php esc_html_e( 'وزن‌های ذخیره‌شده را پاک کن و از پیش‌فرض کد استفاده شود', 'lylyrose-core' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'ذخیره', 'lylyrose-core' ) ); ?>
			</form>
		</div>
		<?php
	}
}