<?php
/**
 * Editable colour palettes (settings page «پالت رنگی»).
 *
 * The theme declares 16 colour tokens once in :root and every rule reads them
 * through var(--dk-*), so a palette is an override of those declarations rather
 * than a rewrite of the stylesheet. That is what this class serves: a saved
 * palette is emitted as an inline :root block on the lylyrose-style handle.
 *
 * Inline rather than written into style.css on purpose. Autoptimize caches the
 * stylesheet as a hashed file whose only cache-buster is the Version: header, so
 * a colour change written into style.css ships stale to every browser that
 * already has it — and the suite cannot catch that, because a stale stylesheet is
 * still a 200 with valid CSS. An inline block rides the same Autoptimize request
 * but is not cached as its own hashed file, so switching needs no cache purge.
 * (Do not purge wp-content/cache by hand to work around it: from a root shell the
 * directory comes back root-owned and www-data can no longer write it.)
 *
 * Storage is one associative array, matching every other setting here
 * (asc_finder_weights, asc_instagram). Shape:
 *
 *   { enabled: bool, active: 'ivory', presets: { slug => { token => hex } } }
 *
 * Only the 8 brand colours are editable. The remaining tokens --dk-red-dark,
 * --dk-red-light, --dk-green, --dk-orange, --dk-white, --dk-star, the radii and
 * the shadows are carried per-preset rather than derived: the :root comments
 * record that WCAG forced specific darkening for several of them, so deriving
 * them from 8 inputs would quietly undo work that was done for a reason.
 */
class ASC_Palette {

	const OPT   = 'asc_palette';
	const GROUP = 'asc_palette';

	/** The editable brand colours, and their Persian labels. */
	const TOKENS = array(
		'red'       => 'رنگ اصلی (دکمه و لینک)',
		'teal'      => 'رنگ مکمل (طلایی/نقره‌ای)',
		'ink'       => 'رنگ عنوان‌ها',
		'text'      => 'رنگ متن',
		'muted'     => 'رنگ متن کم‌رنگ',
		'bg'        => 'رنگ پس‌زمینه',
		'border'    => 'رنگ خطوط',
		'badge-red' => 'رنگ برچسب تخفیف',
	);

	/** Tokens a palette may also carry without exposing them in the form.
	 *  'bar'/'on-bar' are the strip above the header: the one surface that has
	 *  to stay dark in every palette, which --dk-ink does not do. */
	const EXTRA = array( 'on-accent', 'on-gold', 'on-ink', 'white', 'bar', 'on-bar' );

	/** The three palettes that ship. Values match the theme's :root. */
	public static function defaults() {
		return array(
			'ivory' => array(
				'label' => 'عاجی',
				'red'       => '#9c5c5f',
				'teal'      => '#8a6848',
				'ink'       => '#302522',
				'text'      => '#5a4f49',
				'muted'     => '#6e5f58',
				'bg'        => '#f8f3ee',
				'border'    => '#e5dcd4',
				'white'     => '#ffffff',
				'badge-red' => '#b5484d',
				// Matches :root, so the light palettes keep this strip exactly as it
				// renders today. Only Night needs a different value.
				'bar'       => '#302522',
				'on-bar'    => '#ffffff',
			),
			'navy'  => array(
				'label' => 'سرمه‌ای',
				'red'       => '#070f27',
				'teal'      => '#5a6b85',
				'ink'       => '#070f27',
				'text'      => '#4a5468',
				'muted'     => '#5c6675',
				'bg'        => '#eaecef',
				'border'    => '#d5dae3',
				'white'     => '#fefefe',
				'badge-red' => '#c0392b',
				'bar'       => '#070f27',
				'on-bar'    => '#ffffff',
			),
			// Derived by docker/contrast-check.py --derive-night, not hand-picked:
			// a dark surface needs a LIGHT accent, and the label on that accent has
			// to be dark. Verified at 7.24:1 and 8.16:1 for the button label.
			'night' => array(
				'label' => 'شب',
				'red'       => '#789df9',
				'teal'      => '#c4a577',
				'ink'       => '#f2f4f8',
				'text'      => '#bec4d1',
				'body-text' => '#d5d9e2',
				'muted'     => '#949daf',
				'bg'        => '#12151c',
				'border'    => '#323945',
				// --dk-white is the card surface, not a constant. Every surface
				// rule reads it, so on a dark page it has to be a lighter dark;
				// #ffffff left a white slab sitting on #12151c.
				'white'     => '#1b1f28',
				'badge-red' => '#f96868',
				'on-accent' => '#0d1016',
				'on-gold'   => '#0d1016',
				'on-ink'    => '#0d1016',
				// Night's --dk-ink is #f2f4f8, which rendered this strip near-white
				// across the dark page. The bar is now the darkest surface in the
				// palette and the label stays light on it: 14.01:1.
				'bar'       => '#08090d',
				'on-bar'    => '#d5d9e2',
				// Derived steps, re-derived for a dark surface. Leaving the
				// light values in would put a #f8f1f0 panel on #12151c.
				'red-dark'  => '#5b7fd4',
				'red-light' => '#1e2534',
				'green'     => '#4fd07f',
				'orange'    => '#e0a03a',
				'star'      => '#e8b64c',
			),
		);
	}

	/** The token -> CSS custom property map. --dk-red and --dk-teal keep their
	 *  old names on purpose: ~96 call sites depend on them. */
	const CSS_PROP = array(
		'red'       => '--dk-red',
		'teal'      => '--dk-teal',
		'ink'       => '--dk-ink',
		'text'      => '--dk-text',
		// The token the theme's body copy reads. Without it here a dark palette
		// kept the light --dk-body-text and left dark-brown text on a dark page.
		'body-text' => '--dk-body-text',
		'muted'     => '--dk-muted',
		'bg'        => '--dk-bg',
		'border'    => '--dk-border',
		'white'     => '--dk-white',
		'badge-red' => '--dk-badge-red',
		'on-accent' => '--dk-on-accent',
		'on-gold'   => '--dk-on-gold',
		'on-ink'    => '--dk-on-ink',
		// The strip above the header. Carried per palette because it is the one
		// surface that must stay dark, which --dk-ink does not do.
		'bar'       => '--dk-bar',
		'on-bar'    => '--dk-on-bar',
		// Not editable in the form, but a dark palette MUST carry them: the
		// light palettes' --dk-red-light is a near-white panel used for tinted
		// surfaces, and leaving it in place on a #12151c background turns every
		// such panel into a glaring block. Same reason --dk-red-dark and
		// --dk-star are per-palette: both were darkened for a light surface.
		'red-dark'  => '--dk-red-dark',
		'red-light' => '--dk-red-light',
		'green'     => '--dk-green',
		'orange'    => '--dk-orange',
		'star'      => '--dk-star',
	);

	const COOKIE = 'lylyrose_palette';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'inject_css' ), 20 );
	}

	/* ---------------- storage ---------------- */

	/**
	 * The saved option, merged over the shipped defaults so a partial or absent
	 * option still yields every palette and every token.
	 */
	public static function get_config() {
		$saved = get_option( self::OPT, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$presets = self::defaults();
		if ( ! empty( $saved['presets'] ) && is_array( $saved['presets'] ) ) {
			foreach ( $saved['presets'] as $slug => $cols ) {
				if ( ! is_array( $cols ) ) {
					continue;
				}
				$presets[ $slug ] = array_merge(
					isset( $presets[ $slug ] ) ? $presets[ $slug ] : array( 'label' => $slug ),
					$cols
				);
			}
		}
		$active = isset( $saved['active'] ) && isset( $presets[ $saved['active'] ] )
			? $saved['active']
			: 'ivory';

		return array(
			'enabled' => isset( $saved['enabled'] ) ? (bool) $saved['enabled'] : true,
			'active'  => $active,
			'presets' => $presets,
		);
	}

	/** The palette this request should render: the visitor's cookie if they have
	 *  one and it exists, otherwise the site default. */
	public static function current_slug() {
		$config = self::get_config();
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return $config['active'];
		}
		$slug = sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		return isset( $config['presets'][ $slug ] ) ? $slug : $config['active'];
	}

	/**
	 * The :root override for one palette.
	 *
	 * Only the tokens a palette actually declares are emitted, so a palette
	 * without on-accent leaves that property alone rather than clearing it.
	 */
	public static function palette_css( $slug, $config = null ) {
		$config = $config ?: self::get_config();
		if ( ! isset( $config['presets'][ $slug ] ) ) {
			return '';
		}
		$lines = array();
		foreach ( self::CSS_PROP as $token => $prop ) {
			if ( empty( $config['presets'][ $slug ][ $token ] ) ) {
				continue;
			}
			$lines[] = $prop . ':' . $config['presets'][ $slug ][ $token ] . ';';
		}
		if ( ! $lines ) {
			return '';
		}
		return ':root{' . implode( '', $lines ) . '}';
	}

	public static function inject_css() {
		$config = self::get_config();
		$css    = self::palette_css( self::current_slug(), $config );
		if ( ! $css ) {
			return;
		}
		wp_add_inline_style( 'lylyrose-style', $css );

		// The visitor control needs the palette list in JS. Attach it to the
		// SCRIPT handle, not the style handle: wp_localize_script is a script-API
		// function, and handing it 'lylyrose-style' registers nothing while
		// returning without error -- so palette.js found no config, returned
		// early, and the buttons did nothing at all.
		$choices = array();
		foreach ( $config['presets'] as $slug => $cols ) {
			$choices[] = array(
				'slug'   => $slug,
				'label'  => isset( $cols['label'] ) ? $cols['label'] : $slug,
				'accent' => isset( $cols['red'] ) ? $cols['red'] : '',
				'bg'     => isset( $cols['bg'] ) ? $cols['bg'] : '',
				'css'    => self::palette_css( $slug, $config ),
			);
		}
		if ( ! empty( $config['enabled'] ) && wp_script_is( 'lylyrose-palette', 'registered' ) ) {
			wp_localize_script( 'lylyrose-palette', 'lylyrosePalette', array(
				'enabled' => true,
				'active'  => self::current_slug(),
				'choices' => $choices,
				'cookie'  => self::COOKIE,
			) );
		}
	}

	/* ---------------- admin ---------------- */

	public static function admin_menu() {
		add_options_page(
			__( 'پالت رنگی', 'lylyrose-core' ),
			__( 'پالت رنگی', 'lylyrose-core' ),
			'manage_options',
			'asc-palette',
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

	/** Reject anything that is not a colour. A malformed hex would otherwise be
	 *  written into the inline style block and silently break the whole theme. */
	private static function clean_hex( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$value = ltrim( $value, '#' );
		if ( preg_match( '/^[0-9a-fA-F]{3}$/', $value ) ) {
			$value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
		}
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $value ) ) {
			return '';
		}
		return '#' . strtolower( $value );
	}

	private static function clean_label( $value, $fallback ) {
		$value = sanitize_text_field( is_string( $value ) ? $value : '' );
		return '' !== $value ? $value : $fallback;
	}

	public static function sanitize( $in ) {
		$in     = wp_unslash( (array) $in );
		$out    = array( 'enabled' => ! empty( $in['enabled'] ) );
		$preset = isset( $in['preset'] ) ? sanitize_key( $in['preset'] ) : '';

		// "new" is a UI affordance, not a preset: it copies the active one so the
		// owner can adjust from a known-good starting point instead of from blank.
		if ( 'new' === $preset ) {
			$base     = self::get_config();
			$source   = $base['active'];
			$preset   = self::unique_slug( $base, sanitize_text_field( $in['new_slug'] ?? '' ) );
			$in['new_slug'] = $preset;
		}

		$presets = self::get_config()['presets'];
		foreach ( $presets as $slug => $cols ) {
			$key = 'palette_' . $slug;
			if ( ! isset( $in[ $key ] ) || ! is_array( $in[ $key ] ) ) {
				continue;
			}
			$row = $in[ $key ];
			$cols['label'] = self::clean_label( $row['label'] ?? '', $cols['label'] ?? $slug );
			foreach ( array_keys( self::CSS_PROP ) as $token ) {
				if ( 'white' === $token ) {
					$cols['white'] = self::clean_hex( $row['white'] ?? '' ) ?: ( $cols['white'] ?? '#ffffff' );
					continue;
				}
				if ( isset( $row[ $token ] ) ) {
					$clean = self::clean_hex( $row[ $token ] );
					if ( '' === $clean ) {
						// Keep the previous value rather than blanking the token: an
						// empty custom property would fall back to nothing at all.
						add_settings_error(
							self::OPT,
							'asc_palette_bad_hex',
							sprintf(
								/* translators: %s: palette name, %s: colour name */
								__( 'رنگ «%2$s» در پالت «%1$s» کد معتبر نداشت و تغییر نکرد.', 'lylyrose-core' ),
								$cols['label'],
								$token
							),
							'error'
						);
					} else {
						$cols[ $token ] = $clean;
					}
				}
			}
			$presets[ $slug ] = $cols;
		}

		$out['presets'] = $presets;

		if ( ! empty( $in['delete'] ) ) {
			$del = sanitize_key( $in['delete'] );
			if ( isset( $out['presets'][ $del ] ) && count( $out['presets'] ) > 1 ) {
				unset( $out['presets'][ $del ] );
				if ( $out['active'] === $del ) {
					$out['active'] = (string) array_key_first( $out['presets'] );
				}
			}
		}

		if ( isset( $in['active'] ) ) {
			$active = sanitize_key( $in['active'] );
			if ( isset( $out['presets'][ $active ] ) ) {
				$out['active'] = $active;
			}
		} elseif ( ! isset( $out['active'] ) ) {
			$out['active'] = (string) array_key_first( $out['presets'] );
		}

		return $out;
	}

	/** A slug that does not collide, so adding a palette twice cannot overwrite. */
	private static function unique_slug( $config, $wanted ) {
		$wanted = $wanted ? sanitize_key( $wanted ) : 'palette';
		if ( '' === $wanted ) {
			$wanted = 'palette';
		}
		$slug = $wanted;
		$i    = 2;
		while ( isset( $config['presets'][ $slug ] ) ) {
			$slug = $wanted . '-' . $i;
			$i++;
		}
		return $slug;
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$config = self::get_config();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'پالت رنگی', 'lylyrose-core' ); ?></h1>
			<p><?php esc_html_e( 'رنگ‌های سایت از همین‌جا تغییر می‌کنند. پالت فعال برای همه بازدیدکنندگان اعمال می‌شود، مگر آن‌که بازدیدکننده خودش پالت دیگری را انتخاب کرده باشد.', 'lylyrose-core' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'تنظیمات', 'lylyrose-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'پالت پیش‌فرض سایت', 'lylyrose-core' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPT ); ?>[active]">
								<?php foreach ( $config['presets'] as $slug => $cols ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $config['active'], $slug ); ?>>
										<?php echo esc_html( self::clean_label( $cols['label'] ?? '', $slug ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'انتخاب پالت توسط بازدیدکننده', 'lylyrose-core' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[enabled]" value="1" <?php checked( $config['enabled'] ); ?>>
								<?php esc_html_e( 'نمایش کلید انتخاب پالت در سربرگ سایت', 'lylyrose-core' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php foreach ( $config['presets'] as $slug => $cols ) : ?>
					<?php
					$name  = 'label';
					$label = self::clean_label( $cols['label'] ?? '', $slug );
					$key   = self::OPT . '[palette_' . $slug . ']';
					?>
					<h2>
						<?php echo esc_html( $label ); ?>
						<code><?php echo esc_html( $slug ); ?></code>
					</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $key . '_label' ); ?>"><?php esc_html_e( 'نام نمایشی', 'lylyrose-core' ); ?></label></th>
							<td>
								<input id="<?php echo esc_attr( $key . '_label' ); ?>" type="text" class="regular-text"
									name="<?php echo esc_attr( $key . '[label]' ); ?>"
									value="<?php echo esc_attr( $label ); ?>">
								<?php if ( count( $config['presets'] ) > 1 ) : ?>
									<p>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[delete]" value="<?php echo esc_attr( $slug ); ?>">
											<?php esc_html_e( 'حذف این پالت', 'lylyrose-core' ); ?>
										</label>
									</p>
								<?php endif; ?>
							</td>
						</tr>
						<?php foreach ( self::TOKENS as $token => $desc ) : ?>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $key . '_' . $token ); ?>"><?php echo esc_html( $desc ); ?></label>
								</th>
								<td>
									<input id="<?php echo esc_attr( $key . '_' . $token ); ?>" type="color"
										name="<?php echo esc_attr( $key . '[' . $token . ']' ); ?>"
										value="<?php echo esc_attr( self::clean_hex( $cols[ $token ] ?? '' ) ?: '#000000' ); ?>">
									<code><?php echo esc_html( self::clean_hex( $cols[ $token ] ?? '' ) ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endforeach; ?>

				<h2><?php esc_html_e( 'افزودن پالت تازه', 'lylyrose-core' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="asc_palette_new_slug"><?php esc_html_e( 'شناسه (انگلیسی)', 'lylyrose-core' ); ?></label></th>
						<td>
							<input id="asc_palette_new_slug" type="text" class="regular-text"
								name="<?php echo esc_attr( self::OPT ); ?>[new_slug]" value="">
							<select name="<?php echo esc_attr( self::OPT ); ?>[preset]">
								<option value=""><?php esc_html_e( '— بدون تغییر —', 'lylyrose-core' ); ?></option>
								<option value="new"><?php esc_html_e( 'کپی از پالت فعال', 'lylyrose-core' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( '«کپی از پالت فعال» را انتخاب کنید تا پالت تازه از پالت فعلی ساخته شود و بتوانید از همان مقادیر تغییرش دهید.', 'lylyrose-core' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}