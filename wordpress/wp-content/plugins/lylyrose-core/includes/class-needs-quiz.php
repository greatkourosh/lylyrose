<?php
/**
 * Skin and hair needs quizzes (پوستت / موهات چه نیازی داره).
 *
 * Each quiz searches a category tree: the root term and every descendant. The
 * quiz is only live while that tree holds at least one published product. When
 * it empties or its root is removed, the page answers 404, the menu row hides,
 * and ensure_pages() does not recreate it, so a catalogue change cannot leave a
 * half-working quiz behind.
 */

defined( 'ABSPATH' ) || exit;

class ASC_Needs_Quiz {

	const RESULT_LIMIT = 4;

	const OPT_PREFIX = 'asc_needs_';

	/**
	 * Code defaults with saved admin overrides applied. Option keys are fixed: they
	 * are the values stored in product meta, so the admin edits labels only.
	 */
	public static function kinds() {
		$kinds = self::defaults();
		foreach ( $kinds as $key => $kind ) {
			$saved = get_option( self::OPT_PREFIX . $key, array() );
			if ( ! is_array( $saved ) || ! $saved ) {
				continue;
			}
			if ( ! empty( $saved['category'] ) ) {
				$kind['category'] = $saved['category'];
			}
			if ( ! empty( $saved['weights'] ) ) {
				$kind['weights'] = array_merge( $kind['weights'], array_intersect_key( $saved['weights'], $kind['weights'] ) );
			}
			foreach ( $kind['questions'] as $field => $question ) {
				$names = $saved['labels'][ $field ] ?? array();
				if ( ! empty( $names['label'] ) ) {
					$question['label'] = $names['label'];
				}
				foreach ( $question['options'] as $value => $label ) {
					if ( ! empty( $names['options'][ $value ] ) ) {
						$question['options'][ $value ] = $names['options'][ $value ];
					}
				}
				$kind['questions'][ $field ] = $question;
			}
			$kinds[ $key ] = $kind;
		}
		return $kinds;
	}

	public static function defaults() {
		return array(
			'skin' => array(
				'slug'      => 'skin-needs',
				'title'     => 'پوستت چه نیازی داره',
				'category'  => 'skin-care',
				'weights'   => array( 'type' => 1, 'concern' => 1 ),
				'questions' => array(
					'type'    => array( 'label' => 'نوع پوستت چطوری است؟', 'options' => array(
						'dry' => 'خشک', 'oily' => 'چرب', 'combination' => 'مختلط', 'sensitive' => 'حساس', 'normal' => 'نرمال',
					) ),
					'concern' => array( 'label' => 'مهمترین مشکلت چیست؟', 'options' => array(
						'acne' => 'جوش و آکنه', 'dehydration' => 'کمآبی', 'dullness' => 'کدری و بیرونقی',
						'aging' => 'چین و چروک', 'dark_spots' => 'لک و تیرگی', 'redness' => 'قرمزی',
					) ),
				),
			),
			'hair' => array(
				'slug'      => 'hair-needs',
				'title'     => 'موهات چه نیازی داره',
				'category'  => 'hair-care',
				'weights'   => array( 'type' => 1, 'concern' => 1 ),
				'questions' => array(
					'type'    => array( 'label' => 'بافت موهات چطوری است؟', 'options' => array(
						'straight' => 'صاف', 'wavy' => 'موجدار', 'curly' => 'فر', 'coily' => 'خیلی فر',
					) ),
					'concern' => array( 'label' => 'مهمترین مشکلت چیست؟', 'options' => array(
						'frizz' => 'وز و پریشی', 'hair_fall' => 'ریزش', 'damage' => 'آسیب و شکنندگی',
						'dandruff' => 'شوره', 'dryness' => 'خشکی',
					) ),
				),
			),
		);
	}

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'init', array( __CLASS__, 'ensure_pages' ), 30 );
		add_action( 'template_redirect', array( __CLASS__, 'guard' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ) );
	}

	/**
	 * Published product IDs in the quiz's category tree (root plus descendants).
	 *
	 * @return int[]
	 */
	public static function tree_product_ids( $kind ) {
		$root = get_term_by( 'slug', $kind['category'], 'product_cat' );
		if ( ! $root ) {
			return array();
		}
		$terms = array_merge( array( (int) $root->term_id ), array_map( 'intval', get_term_children( $root->term_id, 'product_cat' ) ) );
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => $terms,
				'include_children' => false,
			) ),
		) );
		return array_map( 'intval', $ids );
	}

	public static function is_live( $kind ) {
		return count( self::tree_product_ids( $kind ) ) > 0;
	}

	/**
	 * The quiz that owns the current request's page, or null.
	 */
	public static function current_kind() {
		if ( ! is_page() ) {
			return null;
		}
		$slug = get_queried_object()->post_name ?? '';
		foreach ( self::kinds() as $kind ) {
			if ( $kind['slug'] === $slug ) {
				return $kind;
			}
		}
		return null;
	}

	/**
	 * Empty tree: 404 instead of an empty quiz. The page stays in the database so
	 * it comes back by itself when products return.
	 */
	public static function guard() {
		$kind = self::current_kind();
		if ( $kind && ! self::is_live( $kind ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			if ( $template = get_404_template() ) {
				include $template;
				exit;
			}
		}
	}

	/**
	 * Show the quiz in the page body, so the page works under any theme's default template.
	 */
	public static function filter_content( $content ) {
		$kind = self::current_kind();
		if ( ! $kind || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		ob_start();
		self::render( self::key_for( $kind ) );
		return ob_get_clean();
	}

	private static function key_for( $kind ) {
		foreach ( self::kinds() as $key => $k ) {
			if ( $k === $kind ) {
				return $key;
			}
		}
		return '';
	}

	public static function register_settings() {
		foreach ( array_keys( self::defaults() ) as $key ) {
			register_setting(
				self::OPT_PREFIX . $key,
				self::OPT_PREFIX . $key,
				array(
					'type'              => 'array',
					'sanitize_callback' => function ( $in ) use ( $key ) {
						return self::sanitize_settings( $key, (array) $in );
					},
					'default'           => array(),
				)
			);
		}
	}

	/**
	 * An unusable field keeps its saved value, so a bad save never drops the quiz's
	 * category or zeroes its weights.
	 */
	public static function sanitize_settings( $key, $in ) {
		$option  = self::OPT_PREFIX . $key;
		$current = get_option( $option, array() );
		$out     = array();

		$category = isset( $in['category'] ) ? sanitize_title( wp_unslash( $in['category'] ) ) : '';
		if ( $category && get_term_by( 'slug', $category, 'product_cat' ) ) {
			$out['category'] = $category;
		} else {
			add_settings_error( $option, 'asc_needs_category', __( 'دسته انتخابی پیدا نشد؛ دسته قبلی حفظ شد.', 'lylyrose-core' ) );
			$out['category'] = $current['category'] ?? self::defaults()[ $key ]['category'];
		}

		$weights = array();
		foreach ( array( 'type', 'concern' ) as $field ) {
			$weights[ $field ] = isset( $in['weights'][ $field ] ) ? min( 10, max( 0, (int) $in['weights'][ $field ] ) ) : 1;
		}
		if ( array_sum( $weights ) > 0 ) {
			$out['weights'] = $weights;
		} else {
			add_settings_error( $option, 'asc_needs_weights', __( 'حداقل یک معیار باید وزنی بیشتر از صفر داشته باشد؛ وزنها ذخیره نشد.', 'lylyrose-core' ) );
			$out['weights'] = $current['weights'] ?? self::defaults()[ $key ]['weights'];
		}

		$labels = array();
		foreach ( self::defaults()[ $key ]['questions'] as $field => $question ) {
			$in_field = $in['labels'][ $field ] ?? array();
			$names    = array_filter( array(
				'label'   => sanitize_text_field( wp_unslash( $in_field['label'] ?? '' ) ),
				'options' => array_filter( array_map(
					static function ( $text ) { return sanitize_text_field( wp_unslash( (string) $text ) ); },
					array_intersect_key( (array) ( $in_field['options'] ?? array() ), $question['options'] )
				) ),
			) );
			if ( $names ) {
				$labels[ $field ] = $names;
			}
		}
		$out['labels'] = $labels;

		return $out;
	}

	/**
	 * Live status, then the editable form. The form posts to options.php through its
	 * own settings group, so saving it touches only this quiz.
	 */
	public static function render_settings( $key ) {
		$kinds = self::kinds();
		if ( ! isset( $kinds[ $key ] ) ) {
			return;
		}
		$kind   = $kinds[ $key ];
		$option = self::OPT_PREFIX . $key;
		$ids    = self::tree_product_ids( $kind );
		$root   = get_term_by( 'slug', $kind['category'], 'product_cat' );
		$kids   = $root ? get_term_children( $root->term_id, 'product_cat' ) : array();
		$cats   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		?>
		<h2><?php echo esc_html( $kind['title'] ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'وضعیت', 'lylyrose-core' ); ?></th>
				<td><strong style="color:<?php echo $ids ? '#1e7e34' : '#b32d2e'; ?>"><?php echo esc_html( $ids ? __( 'فعال', 'lylyrose-core' ) : __( 'غیرفعال (محصولی در دسته نیست؛ صفحه ۴۰۴ میدهد)', 'lylyrose-core' ) ); ?></strong></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'محصولات منتشرشده در دسته', 'lylyrose-core' ); ?></th>
				<td><?php echo (int) count( $ids ); ?></td></tr>
		</table>

		<form method="post" action="options.php">
			<?php settings_fields( $option ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="<?php echo esc_attr( $option ); ?>_category"><?php esc_html_e( 'دسته ریشه', 'lylyrose-core' ); ?></label></th>
					<td><select id="<?php echo esc_attr( $option ); ?>_category" name="<?php echo esc_attr( $option ); ?>[category]">
						<?php foreach ( is_array( $cats ) ? $cats : array() as $cat ) : ?>
							<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $kind['category'], $cat->slug ); ?>><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<?php foreach ( array( 'type' => __( 'وزن نوع', 'lylyrose-core' ), 'concern' => __( 'وزن مشکل', 'lylyrose-core' ) ) as $field => $title ) : ?>
					<tr><th scope="row"><label for="<?php echo esc_attr( $option . '_w_' . $field ); ?>"><?php echo esc_html( $title ); ?></label></th>
						<td><input type="number" min="0" max="10" step="1" class="small-text" dir="ltr"
							id="<?php echo esc_attr( $option . '_w_' . $field ); ?>"
							name="<?php echo esc_attr( $option ); ?>[weights][<?php echo esc_attr( $field ); ?>]"
							value="<?php echo (int) $kind['weights'][ $field ]; ?>">
						<p class="description"><?php esc_html_e( 'امتیاز پاسخ مطابق با این معیار. صفر یعنی این پرسش در امتیاز حساب نمیشود.', 'lylyrose-core' ); ?></p></td></tr>
				<?php endforeach; ?>
			</table>

			<?php foreach ( $kind['questions'] as $field => $question ) : ?>
				<h3><?php echo esc_html( $question['label'] ); ?></h3>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'متن پرسش', 'lylyrose-core' ); ?></th>
						<td><input type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[labels][<?php echo esc_attr( $field ); ?>][label]" value="<?php echo esc_attr( $question['label'] ); ?>"></td></tr>
					<?php foreach ( $question['options'] as $value => $label ) : ?>
						<tr><th scope="row"><code dir="ltr"><?php echo esc_html( $value ); ?></code></th>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( $option ); ?>[labels][<?php echo esc_attr( $field ); ?>][options][<?php echo esc_attr( $value ); ?>]" value="<?php echo esc_attr( $label ); ?>"></td></tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>

			<?php submit_button( __( 'ذخیره', 'lylyrose-core' ) ); ?>
		</form>
		<?php
	}

	public static function enqueue() {
		if ( ! self::current_kind() ) {
			return;
		}
		wp_enqueue_style(
			'asc-needs-quiz',
			plugins_url( 'assets/needs-quiz.css', dirname( __FILE__ ) ),
			array(),
			LYLYROSE_CORE_VERSION
		);
	}

	public static function ensure_pages() {
		if ( get_option( 'asc_needs_pages_version' ) === LYLYROSE_CORE_VERSION ) {
			return;
		}
		foreach ( self::kinds() as $kind ) {
			if ( get_page_by_path( $kind['slug'] ) ) {
				continue;
			}
			wp_insert_post( array(
				'post_type'      => 'page',
				'post_name'      => $kind['slug'],
				'post_title'     => $kind['title'],
				'post_status'    => 'publish',
				'comment_status' => 'closed',
			) );
		}
		update_option( 'asc_needs_pages_version', LYLYROSE_CORE_VERSION, false );
	}

	/**
	 * One point per matching type and one per matching concern; 2 is a full match.
	 */
	public static function score( array $needs, array $answers, array $weights = array( 'type' => 1, 'concern' => 1 ) ) {
		$score = 0;
		if ( isset( $answers['type'], $needs['types'] ) && in_array( $answers['type'], $needs['types'], true ) ) {
			$score += (int) $weights['type'];
		}
		if ( isset( $answers['concern'], $needs['concerns'] ) && in_array( $answers['concern'], $needs['concerns'], true ) ) {
			$score += (int) $weights['concern'];
		}
		return $score;
	}

	/**
	 * @param array<int,array{id:int,types:array,concerns:array}> $products
	 * @return array<int,array{id:int,score:int}>
	 */
	public static function rank( array $products, array $answers, array $weights = array( 'type' => 1, 'concern' => 1 ) ) {
		$ranked = array();
		foreach ( $products as $product ) {
			$score = self::score( $product, $answers, $weights );
			if ( $score > 0 ) {
				$ranked[] = array( 'id' => $product['id'], 'score' => $score );
			}
		}
		usort( $ranked, static function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );
		return array_slice( $ranked, 0, self::RESULT_LIMIT );
	}

	public static function render( $key ) {
		$kinds = self::kinds();
		if ( ! isset( $kinds[ $key ] ) ) {
			return;
		}
		$kind      = $kinds[ $key ];
		$submitted = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];
		$valid     = $submitted ? self::sanitise( $kind, wp_unslash( $_POST ) ) : array();

		echo '<div class="asc-finder" dir="rtl">';
		if ( $submitted && count( $valid ) === count( $kind['questions'] ) ) {
			self::render_results( $kind, $valid );
		} else {
			if ( $submitted ) {
				echo '<p class="asc-finder__empty">' . esc_html__( 'لطفاً همه سوالها را پاسخ دهید.', 'lylyrose-core' ) . '</p>';
			}
			self::render_quiz( $kind );
		}
		echo '</div>';
	}

	public static function sanitise( array $kind, array $input ) {
		$answers = array();
		foreach ( array( 'type', 'concern' ) as $field ) {
			$value = isset( $input[ $field ] ) ? sanitize_key( $input[ $field ] ) : '';
			if ( isset( $kind['questions'][ $field ]['options'][ $value ] ) ) {
				$answers[ $field ] = $value;
			}
		}
		return $answers;
	}

	private static function render_quiz( array $kind ) {
		echo '<form method="post" class="asc-finder__quiz">';
		foreach ( $kind['questions'] as $name => $question ) {
			echo '<fieldset class="asc-finder__step">';
			echo '<legend>' . esc_html( $question['label'] ) . '</legend>';
			echo '<div class="asc-finder__options">';
			foreach ( $question['options'] as $value => $label ) {
				printf(
					'<label class="asc-finder__option"><input type="radio" name="%1$s" value="%2$s"> <span>%3$s</span></label>',
					esc_attr( $name ), esc_attr( $value ), esc_html( $label )
				);
			}
			echo '</div></fieldset>';
		}
		echo '<p class="asc-finder__actions"><button type="submit" class="asc-finder__submit">' . esc_html__( 'پیشنهاد بده', 'lylyrose-core' ) . '</button></p>';
		echo '</form>';
	}

	private static function render_results( array $kind, array $answers ) {
		$ranked = self::rank( self::products_for( $kind ), $answers, $kind['weights'] );

		echo '<h2 class="asc-finder__title">' . esc_html__( 'پیشنهادهای شما', 'lylyrose-core' ) . '</h2>';
		if ( empty( $ranked ) ) {
			echo '<p class="asc-finder__empty">' . esc_html__( 'فعلاً محصولی برای این مورد نداریم.', 'lylyrose-core' ) . '</p>';
			echo '<p class="asc-finder__alt"><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'مشاهده همه محصولات', 'lylyrose-core' ) . '</a></p>';
			return;
		}

		echo '<ol class="asc-finder__results">';
		foreach ( $ranked as $row ) {
			$product = wc_get_product( $row['id'] );
			if ( ! $product ) {
				continue;
			}
			$url = get_permalink( $product->get_id() );
			?>
			<li class="asc-finder__result">
				<a class="asc-finder__media" href="<?php echo esc_url( $url ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) ); ?>
				</a>
				<div class="asc-finder__body">
					<h3 class="asc-finder__name"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
					<span class="asc-finder__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
					<a class="asc-finder__view" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'مشاهده و خرید', 'lylyrose-core' ); ?></a>
				</div>
			</li>
			<?php
		}
		echo '</ol>';
		echo '<p class="asc-finder__alt"><a href="' . esc_url( get_permalink( get_page_by_path( $kind['slug'] ) ) ) . '">' . esc_html__( 'شروع دوباره', 'lylyrose-core' ) . '</a></p>';
	}

	/**
	 * Products in the category tree, with their needs meta.
	 */
	private static function products_for( array $kind ) {
		$products = array();
		foreach ( self::tree_product_ids( $kind ) as $id ) {
			$products[] = array(
				'id'       => $id,
				'types'    => (array) get_post_meta( $id, '_needs_types', true ),
				'concerns' => (array) get_post_meta( $id, '_needs_concerns', true ),
			);
		}
		return $products;
	}
}
