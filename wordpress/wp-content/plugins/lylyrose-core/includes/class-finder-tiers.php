<?php
/**
 * Price-tier settings for the perfume finder (عطرت رو پیدا کن).
 *
 * The tiers used to be four hardcoded boundaries in ASC_Perfume_Finder. They
 * were tuned once against a snapshot of the catalogue and never moved, so they
 * drifted: the current catalogue splits 83/24/4/3 across them, which leaves the
 * two top tiers nearly empty — a visitor who picks "premium" sees four products.
 *
 * Two modes, chosen by the owner on a Settings page:
 *   auto   — boundaries are the catalogue's own price percentiles
 *   manual — boundaries are typed in and stored
 *
 * The tier KEYS never change (eco/mid/premium/luxury). Only their boundaries and
 * labels do, so stored answers and the finder's existing scoring stay valid.
 */
class ASC_Finder_Tiers {

	const OPT = 'asc_finder_tiers';

	/**
	 * Boundaries as percentiles of the published catalogue's prices. p25/p50/p80
	 * rather than equal quarters: the price distribution is heavily skewed (the
	 * live catalogue runs 500K to 906M against a 9.9M median), so equal
	 * percentiles put almost everything in the bottom tier. p80 as the last cut
	 * leaves the top tier large enough to actually shop.
	 */
	const PERCENTILES = array( 25, 50, 80 );

	/** The open-ended top tier. One fixed key keeps the quiz's shape stable. */
	const TOP_KEY = 'luxury';

	/** Labels for the auto mode. Only the labels are ours to change; the keys are not. */
	const DEFAULT_LABELS = array(
		'eco'     => 'اقتصادی',
		'mid'     => 'متوسط',
		'premium' => 'پریمیوم',
		'luxury'  => 'لوکس',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/* ---------------- Reading ---------------- */

	/**
	 * The active tiers, each {max: int|null, label: string, range: string}.
	 * Manual when the owner chose manual and what they saved is usable;
	 * auto otherwise. A saved-but-empty manual set falls back rather than
	 * rendering a quiz with no budget options.
	 */
	public static function get() {
		$saved = get_option( self::OPT, array() );
		if ( is_array( $saved ) && isset( $saved['mode'] ) && 'manual' === $saved['mode'] ) {
			$tiers = self::clean( $saved['tiers'] ?? array() );
			if ( $tiers ) {
				return self::with_ranges( self::complete( $tiers ) );
			}
		}
		return self::with_ranges( self::auto_tiers() );
	}

	/**
	 * A manual save that names only some tiers still has to produce the four the
	 * quiz and the scorer expect. Missing ones are filled from the automatic
	 * result, in the canonical order, rather than leaving a short list that would
	 * make most products unrankable on the budget axis.
	 */
	private static function complete( $tiers ) {
		$keys   = array_keys( self::DEFAULT_LABELS );
		$labels = array_values( self::DEFAULT_LABELS );
		$auto   = self::auto_tiers();
		$out    = array();
		foreach ( $keys as $i => $key ) {
			if ( isset( $tiers[ $key ] ) ) {
				$out[ $key ] = $tiers[ $key ];
			} elseif ( isset( $auto[ $key ] ) ) {
				$out[ $key ] = array(
					'max'   => $auto[ $key ]['max'],
					'label' => $labels[ $i ],
				);
			}
		}
		// Anything the owner added beyond the canonical four keeps its place at
		// the bottom, where an uncapped tier is harmless.
		foreach ( $tiers as $key => $tier ) {
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = $tier;
			}
		}
		return $out;
	}

	/**
	 * Boundaries from the live catalogue's own price distribution.
	 */
	private static function auto_tiers() {
		$cut = self::percentile_cuts();
		if ( ! $cut ) {
			return array();
		}

		$keys   = array_keys( self::DEFAULT_LABELS );
		$tiers  = array();
		$labels = array_values( self::DEFAULT_LABELS );
		foreach ( $cut as $i => $boundary ) {
			$tiers[ $keys[ $i ] ] = array(
				'max'   => $boundary,
				'label' => $labels[ $i ],
			);
		}
		$tiers[ self::TOP_KEY ] = array( 'max' => null, 'label' => $labels[ count( $keys ) - 1 ] );
		return $tiers;
	}

	/**
	 * The percentile boundaries, or empty when there is nothing to measure.
	 * Cached because it reads the whole catalogue and the answer only changes
	 * when a price does.
	 */
	private static function percentile_cuts() {
		$key    = 'asc_finder_tier_cuts';
		$cached = wp_cache_get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$prices = array();
		foreach ( get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) ) as $id ) {
			$product = wc_get_product( $id );
			// Zero-price products are placeholders, not a price band to rank.
			if ( $product && (float) $product->get_price() > 0 ) {
				$prices[] = (float) $product->get_price();
			}
		}

		$cuts = array();
		if ( count( $prices ) >= count( self::PERCENTILES ) ) {
			sort( $prices );
			$count = count( $prices );
			foreach ( self::PERCENTILES as $percentile ) {
				$cuts[] = (int) round( $prices[ (int) ceil( $percentile / 100 * $count ) - 1 ] );
			}
			// Two percentiles can land on the same price in a tightly-clustered
			// catalogue, which would make one tier empty and read as a bug.
			$cuts = array_values( array_unique( $cuts ) );
			sort( $cuts );
		}

		wp_cache_set( $key, $cuts, HOUR_IN_SECONDS );
		return $cuts;
	}

	/**
	 * Attach the human range to each tier, e.g. "تا ۶٬۱۵۰٬۰۰۰ تومان".
	 * The top tier reads as "بیشتر از X" because it has no ceiling.
	 */
	private static function with_ranges( $tiers ) {
		$keys   = array_keys( $tiers );
		$top    = count( $keys ) - 1;
		$i      = 0;
		$out    = array();
		foreach ( $tiers as $key => $tier ) {
			$tier['max']   = isset( $tier['max'] ) && null !== $tier['max'] ? (int) $tier['max'] : null;
			$tier['label'] = (string) $tier['label'];
			$tier['range'] = self::range_text( $tier['max'], $i === $top && null === $tier['max'] );
			$out[ $key ]    = $tier;
			$i++;
		}
		return $out;
	}

	private static function range_text( $max, $is_top ) {
		if ( null === $max ) {
			return $is_top ? __( 'بالاتر از همه', 'lylyrose-core' ) : '';
		}
		$amount = number_format_i18n( $max );
		if ( function_exists( 'lylyrose_fa_num' ) ) {
			$amount = lylyrose_fa_num( $amount );
		}
		return $is_top
			/* translators: %s: formatted price with thousands separators. */
			? sprintf( __( 'بیشتر از %s تومان', 'lylyrose-core' ), $amount )
			/* translators: %s: formatted price with thousands separators. */
			: sprintf( __( 'تا %s تومان', 'lylyrose-core' ), $amount );
	}

	/* ---------------- Writing ---------------- */

	public static function admin_menu() {
		add_options_page(
			__( 'رده‌های قیمتی عطرت رو پیدا کن', 'lylyrose-core' ),
			__( 'رده‌های قیمتی عطر', 'lylyrose-core' ),
			'manage_options',
			'asc-finder-tiers',
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function admin_init() {
		register_setting( 'asc_finder_tiers', self::OPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	/**
	 * Drop anything that would break the quiz: a non-numeric or negative ceiling,
	 * a key that is not a safe slug, a duplicate key, or a row with no label.
	 * A manual set that survives this empty is simply not used.
	 */
	public static function sanitize( $in ) {
		$in    = is_array( $in ) ? $in : array();
		$mode  = ( isset( $in['mode'] ) && 'manual' === $in['mode'] ) ? 'manual' : 'auto';
		$out   = array( 'mode' => $mode );
		$seen  = array();

		foreach ( (array) ( $in['tiers'] ?? array() ) as $tier ) {
			if ( ! is_array( $tier ) ) {
				continue;
			}
			$key = sanitize_key( (string) ( $tier['key'] ?? '' ) );
			if ( ! $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $tier['label'] ?? '' ) );
			if ( ! $label ) {
				continue;
			}
			$raw = $tier['max'] ?? '';
			$max = ( '' === $raw || null === $raw || 'null' === $raw )
				? null
				: max( 0, (int) $raw );
			if ( null !== $max && $max < 1 ) {
				continue;
			}

			$seen[ $key ] = true;
			$out['tiers'][] = array( 'key' => $key, 'max' => $max, 'label' => $label );
		}

		// Switching back to auto, or saving a manual set that did not survive,
		// must not leave a half-configured manual behind.
		if ( empty( $out['tiers'] ) ) {
			unset( $out['tiers'] );
		}
		return $out;
	}

	/**
	 * Manual tiers in the saved order, so the admin form and the quiz agree.
	 */
	private static function clean( $rows ) {
		$out  = array();
		$seen = array();
		foreach ( $rows as $tier ) {
			if ( ! is_array( $tier ) ) {
				continue;
			}
			$key = sanitize_key( (string) ( $tier['key'] ?? '' ) );
			if ( ! $key || isset( $seen[ $key ] ) || ! isset( $tier['label'] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$max = ( null === ( $tier['max'] ?? null ) ) ? null : max( 0, (int) $tier['max'] );
			$out[ $key ] = array( 'max' => $max, 'label' => sanitize_text_field( (string) $tier['label'] ) );
		}
		return $out;
	}

	/* ---------------- Settings page ---------------- */

	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$saved = get_option( self::OPT, array() );
		$mode  = is_array( $saved ) && 'manual' === ( $saved['mode'] ?? '' ) ? 'manual' : 'auto';

		// The form always shows four rows: the saved ones in manual mode, the
		// current auto result otherwise, so switching modes does not blank the
		// form and the owner can see what auto is currently producing.
		$rows = $mode === 'manual'
			? self::clean( $saved['tiers'] ?? array() )
			: self::auto_tiers();
		$keys = array_keys( self::DEFAULT_LABELS );
		$auto = self::auto_tiers();

		$split = self::catalogue_split( self::get() );
		?>
		<div class="wrap" dir="rtl">
			<h1><?php esc_html_e( 'رده‌های قیمتی «عطرت رو پیدا کن»', 'lylyrose-core' ); ?></h1>
			<p><?php esc_html_e( 'این مرزها تعیین می‌کنند هر محصول در کدام رده قرار بگیرد و در کوییز به خریدار چه بازه‌ای نشان داده شود. کلید رده‌ها (اقتصادی، متوسط، پریمیوم، لوکس) ثابت است؛ فقط مرز و عنوانشان عوض می‌شود.', 'lylyrose-core' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'asc_finder_tiers' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'روش تعیین مرزها', 'lylyrose-core' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( 'روش تعیین مرزها', 'lylyrose-core' ); ?></span></legend>
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPT ); ?>[mode]" value="auto" <?php checked( $mode, 'auto' ); ?>>
									<?php esc_html_e( 'خودکار — از قیمت‌های کاتالوگ محاسبه شود', 'lylyrose-core' ); ?>
								</label><br>
								<label>
									<input type="radio" name="<?php echo esc_attr( self::OPT ); ?>[mode]" value="manual" <?php checked( $mode, 'manual' ); ?>>
									<?php esc_html_e( 'دستی — مرزها را خودم وارد کنم', 'lylyrose-core' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'در حالت خودکار مرزها صدک ۲۵، ۵۰ و ۸۰ قیمت محصولات منتشرشده‌اند، پس با تغییر قیمت‌ها خودکار به‌روز می‌شوند.', 'lylyrose-core' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'رده‌ها', 'lylyrose-core' ); ?></h2>
				<table class="widefat striped" style="max-width:760px">
					<thead>
						<tr>
							<th style="width:140px"><?php esc_html_e( 'کلید', 'lylyrose-core' ); ?></th>
							<th style="width:200px"><?php esc_html_e( 'عنوان (در کوییز نمایش داده می‌شود)', 'lylyrose-core' ); ?></th>
							<th style="width:220px"><?php esc_html_e( 'حداکثر قیمت (تومان)', 'lylyrose-core' ); ?></th>
							<th><?php esc_html_e( 'محصولات', 'lylyrose-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $keys as $key ) :
						$label = $rows[ $key ]['label'] ?? self::DEFAULT_LABELS[ $key ];
						$max   = $rows[ $key ]['max'] ?? null;
						$is_top = ( $key === self::TOP_KEY );
						?>
						<tr>
							<td><code><?php echo esc_html( $key ); ?></code></td>
							<td>
								<input type="text" name="<?php echo esc_attr( self::OPT ); ?>[tiers][<?php echo esc_attr( $key ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text">
								<input type="hidden" name="<?php echo esc_attr( self::OPT ); ?>[tiers][<?php echo esc_attr( $key ); ?>][key]" value="<?php echo esc_attr( $key ); ?>">
							</td>
							<td>
								<?php if ( $is_top ) : ?>
									<input type="text" value="<?php echo esc_attr( $auto[ $key ]['range'] ?? '' ); ?>" readonly>
									<p class="description"><?php esc_html_e( 'این رده سقف ندارد؛ بالاتر از مرز رده قبلی است.', 'lylyrose-core' ); ?></p>
								<?php else : ?>
									<input type="number" min="0" step="1000" name="<?php echo esc_attr( self::OPT ); ?>[tiers][<?php echo esc_attr( $key ); ?>][max]" value="<?php echo esc_attr( null === $max ? '' : $max ); ?>" class="regular-text" dir="ltr">
									<p class="description">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: the automatic boundary for this tier. */
												__( 'پیشنهاد خودکار: %s تومان', 'lylyrose-core' ),
												isset( $auto[ $key ]['max'] ) && null !== $auto[ $key ]['max']
													? number_format_i18n( $auto[ $key ]['max'] )
													: '—'
											)
										);
										?>
									</p>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( $split[ $key ] ?? 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'ذخیره', 'lylyrose-core' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * How many published products fall in each tier, so the owner can see that a
	 * manual split has not emptied the top tiers. Read-only and capped: it walks
	 * prices only, never a full product load.
	 */
	private static function catalogue_split( $tiers ) {
		$counts = array_fill_keys( array_keys( $tiers ), 0 );
		foreach ( get_posts( array(
			'post_type'     => 'product',
			'post_status'   => 'publish',
			'numberposts'   => -1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		) ) as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$price = (float) $product->get_price();
			foreach ( $tiers as $key => $tier ) {
				if ( null === $tier['max'] || $price < $tier['max'] ) {
					$counts[ $key ]++;
					break;
				}
			}
		}
		return $counts;
	}
}