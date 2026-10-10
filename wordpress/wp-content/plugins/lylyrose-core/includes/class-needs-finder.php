<?php
/**
 * Skin and hair needs quizzes. Each quiz answers a type and a concern and
 * returns the care products whose generated needs metadata matches them.
 * Separate from the perfume finder, which it does not touch.
 */

defined( 'ABSPATH' ) || exit;

class ASC_Needs_Finder {

	const RESULT_LIMIT = 4;

	public static function kinds() {
		return array(
			'skin' => array(
				'slug'      => 'skin-needs',
				'title'     => 'پوستت چه نیازی داره',
				'category'  => 'skin-care',
				'questions' => array(
					'type'    => array( 'label' => 'نوع پوستت چطوری است؟', 'options' => array(
						'dry' => 'خشک', 'oily' => 'چرب', 'combination' => 'مختلط', 'sensitive' => 'حساس', 'normal' => 'نرمال',
					) ),
					'concern' => array( 'label' => 'مهم‌ترین مشکلت چیست؟', 'options' => array(
						'acne' => 'جوش و آکنه', 'dehydration' => 'کم‌آبی', 'dullness' => 'کدری و بی‌رونقی',
						'aging' => 'چین و چروک', 'dark_spots' => 'لک و تیرگی', 'redness' => 'قرمزی',
					) ),
				),
			),
			'hair' => array(
				'slug'      => 'hair-needs',
				'title'     => 'موهات چه نیازی داره',
				'category'  => 'hair-care',
				'questions' => array(
					'type'    => array( 'label' => 'بافت موهات چطوری است؟', 'options' => array(
						'straight' => 'صاف', 'wavy' => 'موج‌دار', 'curly' => 'فر', 'coily' => 'خیلی فر',
					) ),
					'concern' => array( 'label' => 'مهم‌ترین مشکلت چیست؟', 'options' => array(
						'frizz' => 'وز و پریشی', 'hair_fall' => 'ریزش', 'damage' => 'آسیب و شکنندگی',
						'dandruff' => 'شوره', 'dryness' => 'خشکی',
					) ),
				),
			),
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_pages' ), 30 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	public static function enqueue() {
		if ( ! function_exists( 'get_queried_object' ) || ! is_page() ) {
			return;
		}
		$slugs = wp_list_pluck( self::kinds(), 'slug' );
		if ( ! in_array( get_queried_object()->post_name ?? '', $slugs, true ) ) {
			return;
		}
		wp_enqueue_style(
			'lylyrose-perfume-finder',
			get_theme_file_uri( 'assets/css/perfume-finder.css' ),
			array( 'lylyrose-style' ),
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
	 * A product scores one point per matching type and one per matching concern,
	 * so 2 is a full match.
	 */
	public static function score( array $needs, array $answers ) {
		$score = 0;
		if ( isset( $answers['type'], $needs['types'] ) && in_array( $answers['type'], $needs['types'], true ) ) {
			$score++;
		}
		if ( isset( $answers['concern'], $needs['concerns'] ) && in_array( $answers['concern'], $needs['concerns'], true ) ) {
			$score++;
		}
		return $score;
	}

	/**
	 * @param array<int,array{id:int,types:array,concerns:array}> $products
	 * @return array<int,array{id:int,score:int}> best matches first, no zero scores
	 */
	public static function rank( array $products, array $answers ) {
		$ranked = array();
		foreach ( $products as $product ) {
			$score = self::score( $product, $answers );
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
				echo '<p class="asc-finder__empty">' . esc_html__( 'لطفاً همه سوال‌ها را پاسخ دهید.', 'lylyrose-core' ) . '</p>';
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
		echo '<h2 class="asc-finder__title">' . esc_html( $kind['title'] ) . '</h2>';
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
		echo '<p class="asc-finder__alt"><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'یا مستقیم از فروشگاه خرید کنید', 'lylyrose-core' ) . '</a></p>';
	}

	private static function render_results( array $kind, array $answers ) {
		$ranked = self::rank( self::products_for( $kind['category'] ), $answers );

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
			$url   = get_permalink( $product->get_id() );
			$usage = (string) get_post_meta( $product->get_id(), '_needs_usage', true );
			?>
			<li class="asc-finder__result">
				<a class="asc-finder__media" href="<?php echo esc_url( $url ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) ); ?>
				</a>
				<div class="asc-finder__body">
					<div class="asc-finder__stack">
						<div class="asc-finder__head">
							<h3 class="asc-finder__name"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
						</div>
						<div class="asc-finder__buy">
							<span class="asc-finder__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
							<span class="asc-finder__stock <?php echo $product->is_in_stock() ? 'is-in' : 'is-out'; ?>">
								<?php echo esc_html( $product->is_in_stock() ? __( 'موجود', 'lylyrose-core' ) : __( 'ناموجود', 'lylyrose-core' ) ); ?>
							</span>
						</div>
						<?php if ( '' !== $usage ) : ?>
							<dl class="asc-finder__meta">
								<div class="asc-finder__meta-row">
									<dt><?php esc_html_e( 'روش استفاده', 'lylyrose-core' ); ?></dt>
									<dd><?php echo esc_html( $usage ); ?></dd>
								</div>
							</dl>
						<?php endif; ?>
					</div>
					<p class="asc-finder__actions">
						<a class="asc-finder__view" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'مشاهده و خرید', 'lylyrose-core' ); ?></a>
					</p>
				</div>
			</li>
			<?php
		}
		echo '</ol>';
		echo '<p class="asc-finder__alt"><a href="' . esc_url( get_permalink( get_page_by_path( $kind['slug'] ) ) ) . '">' . esc_html__( 'شروع دوباره', 'lylyrose-core' ) . '</a></p>';
	}

	private static function products_for( $category ) {
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array( array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $category,
			) ),
		) );
		$products = array();
		foreach ( $ids as $id ) {
			$products[] = array(
				'id'       => (int) $id,
				'types'    => (array) get_post_meta( $id, '_needs_types', true ),
				'concerns' => (array) get_post_meta( $id, '_needs_concerns', true ),
			);
		}
		return $products;
	}
}
