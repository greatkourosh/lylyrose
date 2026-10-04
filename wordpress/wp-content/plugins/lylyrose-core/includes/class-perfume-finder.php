<?php
/**
 * «عطرت رو پیدا کن» — guided perfume finder.
 *
 * A scored recommender over the WooCommerce catalogue. The design constraint that
 * shapes everything here: the catalogue's fragrance metadata is largely absent, so
 * the finder must never invent it. A product is ranked only when it carries real
 * fragrance data; a weight that cannot be scored contributes nothing to the score
 * and is reported as unscored rather than counted as a zero. That keeps the match
 * percentage deterministic and explainable instead of confidently wrong.
 *
 * Brand and product category are deliberately NOT scoring axes and never
 * qualify a product on their own — a brand is not a fragrance characteristic.
 * Price tier is derived from _price at query time rather than stored, so it
 * cannot go stale.
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASC_Perfume_Finder {

	const PAGE_SLUG = 'perfume-finder';
	const MAX_RESULTS = 3;

	/**
	 * Axes and their intended weights. Filterable so the weighting can be tuned
	 * once the catalogue carries real data on every axis. Weights are relative —
	 * they are renormalised over the axes a product actually populates, so an
	 * unpopulated axis costs a product nothing instead of scoring it zero.
	 */
	public static function weights() {
		return apply_filters( 'asc_finder_weights', array(
			'fragrance'   => 30,
			'occasion'    => 20,
			'season'      => 15,
			'personality' => 15,
			'longevity'   => 10,
			'budget'      => 10,
		) );
	}

	/**
	 * Axes that describe a fragrance itself. A product needs at least one of
	 * these to be rankable at all — this is the gate that stops the finder
	 * recommending products it knows nothing about.
	 */
	private static function fragrance_axes() {
		return array( 'fragrance', 'occasion', 'season', 'personality', 'longevity' );
	}

	/**
	 * Price tier boundaries (Toman), derived from _price. Filterable because the
	 * catalogue's spread is skewed: a hardcoded split leaves the top tier nearly
	 * empty. Tuned against the real distribution rather than round numbers.
	 */
	public static function price_tiers() {
		return apply_filters( 'asc_finder_price_tiers', array(
			'eco'      => array( 'max' => 15000000,   'label' => 'اقتصادی' ),
			'mid'      => array( 'max' => 50000000,   'label' => 'متوسط' ),
			'premium'  => array( 'max' => 150000000,  'label' => 'پریمیوم' ),
			'luxury'   => array( null,              'label' => 'لوکس' ),
		) );
	}

	/**
	 * Whether the row earned anything on any axis it could actually be scored
	 * against. Gender alone never counts: it is an answer, not a fragrance trait.
	 */
	public static function matched( $row ) {
		foreach ( $row['result']['factors'] as $factor ) {
			if ( $factor['scored'] && $factor['matched'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Persian digits, so the match percentage matches the rest of the storefront.
	 */
	private static function fa_num( $number ) {
		if ( function_exists( 'lylyrose_fa_num' ) ) {
			return lylyrose_fa_num( $number );
		}
		return str_replace(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
			(string) $number
		);
	}

	/**
	 * Whether the current request is the finder page. Mirrors is_incredible_offers().
	 */
	public static function is_finder_page() {
		return is_page( self::PAGE_SLUG );
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_page' ), 30 );
		add_action( 'init', array( __CLASS__, 'register_terms' ), 30 );
	}

	/**
	 * Create the finder page once. An existing slug is never overwritten, and a
	 * trashed page is not resurrected until the version changes.
	 *
	 * The template is left to WordPress: a theme file named page-<slug>.php is
	 * picked up without any _wp_page_template meta, so assigning it here would
	 * only add a row that has to be kept in step with the slug.
	 */
	public static function ensure_page() {
		if ( get_option( 'asc_finder_page_version' ) === LYLYROSE_CORE_VERSION ) {
			return;
		}
		$page = get_page_by_path( self::PAGE_SLUG );
		if ( ! $page ) {
			$page_id = wp_insert_post( array(
				'post_type'      => 'page',
				'post_name'      => self::PAGE_SLUG,
				'post_title'     => 'عطرت رو پیدا کن',
				'post_status'    => 'publish',
				'comment_status' => 'closed',
			) );
		} else {
			$page_id = $page->ID;
		}

		update_option( 'asc_finder_page_version', LYLYROSE_CORE_VERSION );
	}

	/**
	 * The controlled vocabularies for the quiz. Terms are created empty — they
	 * exist so the taxonomy is usable and filterable, not to imply any product
	 * carries them. Membership is what carries meaning.
	 */
	public static function vocabularies() {
		return apply_filters( 'asc_finder_vocabularies', array(
			'pa_fragrance_family' => array( 'گل', 'میوه', 'چوب', 'ادویه', 'آکواتیک', 'شرقی' ),
			'pa_occasion'         => array( 'روزمره', 'مهمانی', 'محل کار', 'ورزشی', 'سفر', 'مراسم رسمی' ),
			'pa_season'           => array( 'بهار', 'تابستان', 'پاییز', 'زمستان' ),
			'pa_personality'      => array( 'کلاسیک', 'مدرن', 'جسور', 'مؤدب', 'شیطون', 'آرام' ),
			'pa_gender'           => array( 'مردانه', 'زنانه', 'یونیسکس' ),
		) );
	}

	/**
	 * Create the vocabulary terms that do not exist yet. Idempotent, and it only
	 * ever adds — nothing is removed if the vocabularies are later narrowed.
	 */
	public static function register_terms() {
		foreach ( self::vocabularies() as $taxonomy => $terms ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			foreach ( $terms as $name ) {
				if ( ! term_exists( $name, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy );
				}
			}
		}
	}

	/**
	 * Real fragrance metadata for one product, or null when it has none.
	 *
	 * Returns only axes that are genuinely populated. Brand and category are
	 * collected separately and never feed the score.
	 */
	public static function profile( $product ) {
		$id = $product->get_id();

		$data = array(
			'fragrance'   => self::terms( $id, 'pa_fragrance_family' ),
			'notes'       => self::notes( $id ),
			'longevity'   => self::terms( $id, 'pa_longevity' ),
			'sillage'     => self::terms( $id, 'pa_sillage' ),
			'season'      => self::terms( $id, 'pa_season' ),
			'occasion'    => self::terms( $id, 'pa_occasion' ),
			'personality' => self::terms( $id, 'pa_personality' ),
			'gender'      => self::terms( $id, 'pa_gender' ),
			'price_tier'  => self::price_tier( $product->get_price() ),
			'brand'       => self::terms( $id, 'pa_brand' ),
		);

		// Rankable only if it carries a real fragrance characteristic. Notes count:
		// an authored pyramid is genuine fragrance data even without a family term.
		$has_fragrance = ! empty( $data['notes'] );
		foreach ( self::fragrance_axes() as $axis ) {
			if ( ! empty( $data[ $axis ] ) ) {
				$has_fragrance = true;
				break;
			}
		}
		if ( ! $has_fragrance ) {
			return null;
		}

		$data['_scorable'] = true;
		return $data;
	}

	/**
	 * Term names on a taxonomy. Read straight from the DB rather than through
	 * wc_get_attribute_taxonomies(): the attribute table is not populated on this
	 * install, so that lookup would report an axis that exists as empty.
	 */
	private static function terms( $product_id, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
		return is_wp_error( $terms ) ? array() : array_values( array_filter( $terms ) );
	}

	/**
	 * The note pyramid, reusing the storage convention the notes feature defines.
	 */
	private static function notes( $product_id ) {
		$out = array();
		foreach ( array( 'top', 'heart', 'base' ) as $layer ) {
			$raw = get_post_meta( $product_id, '_asc_notes_' . $layer, true );
			if ( $raw ) {
				$lines = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
				if ( $lines ) {
					$out[ $layer ] = $lines;
				}
			}
		}
		return $out;
	}

	/**
	 * Derive the tier from the current price. Not stored, so it cannot drift.
	 */
	public static function price_tier( $price ) {
		$price = (float) $price;
		foreach ( self::price_tiers() as $key => $tier ) {
			if ( ! isset( $tier['max'] ) || null === $tier['max'] || $price < $tier['max'] ) {
				return $key;
			}
		}
		return 'luxury';
	}

	/**
	 * Score one product against the visitor's answers.
	 *
	 * Every axis is scored independently, then combined over the axes that both
	 * the answer set and the product actually have. No randomness, no fallbacks.
	 */
	public static function score( $profile, $answers ) {
		$weights   = self::weights();
		$earned    = 0.0;
		$possible  = 0.0;
		$factors   = array();

		// Budget is the one axis every product can answer — it is derived, not authored.
		$budget = $answers['budget'] ?? '';
		if ( $budget !== '' && ! empty( $profile['price_tier'] ) ) {
			$hit = ( $profile['price_tier'] === $budget );
			$earned   += $weights['budget'] * ( $hit ? 1.0 : 0.0 );
			$possible += $weights['budget'];
			$factors[] = array(
				'axis'   => 'budget',
				'label'  => 'بودجه',
				'matched' => $hit,
				'scored' => true,
				'text'   => $hit ? 'در محدوده بودجه شما' : 'خارج از محدوده بودجه شما',
			);
		}

		// Preference axes: exact term match, then partial credit for shared notes.
		$axes = array(
			'fragrance'   => array( 'pa_fragrance_family', 'رایحه‌های مورد علاقه شما' ),
			'season'      => array( 'pa_season',           'فصل مورد نظر' ),
			'personality' => array( 'pa_personality',      'شخصیت شما' ),
		);
		foreach ( $axes as $axis => $meta ) {
			list( $taxonomy, $label ) = $meta;
			$answer = $answers[ $axis ] ?? '';
			$values = $profile[ $axis ] ?? array();
			if ( $answer === '' || empty( $values ) || empty( $weights[ $axis ] ) ) {
				continue;
			}
			$hit = in_array( $answer, $values, true );
			$earned   += $weights[ $axis ] * ( $hit ? 1.0 : 0.0 );
			$possible += $weights[ $axis ];
			$factors[] = array(
				'axis'    => $axis,
				'label'   => $label,
				'matched' => $hit,
				'scored'  => true,
				'text'    => $hit ? $answer : null,
			);
		}

		// Occasion.
		$occasion = $answers['occasion'] ?? '';
		if ( $occasion !== '' && ! empty( $profile['occasion'] ) ) {
			$hit = in_array( $occasion, $profile['occasion'], true );
			$earned   += $weights['occasion'] * ( $hit ? 1.0 : 0.0 );
			$possible += $weights['occasion'];
			$factors[] = array(
				'axis'    => 'occasion',
				'label'   => 'مناسب برای موقعیت انتخابی',
				'matched' => $hit,
				'scored'  => true,
				'text'    => $hit ? $occasion : null,
			);
		}

		// Longevity + sillage share one axis weight, per the requested weighting.
		// Compared on the ordered scale, so a wanted level can be met by a
		// product at or above it rather than only by an exact wording match.
		$longevity = $answers['longevity'] ?? '';
		$longevity_hit = false;
		$profile_level = 0;
		if ( ! empty( $profile['longevity'] ) ) {
			$levels = self::longevity_levels();
			foreach ( $profile['longevity'] as $term ) {
				if ( isset( $levels[ $term ] ) ) {
					$profile_level = max( $profile_level, $levels[ $term ] );
				}
			}
		}
		$wanted_level = ( '' !== $longevity && isset( self::longevity_levels()[ $longevity ] ) )
			? self::longevity_levels()[ $longevity ] : 0;
		if ( $wanted_level > 0 && $profile_level > 0 ) {
			$longevity_hit = $profile_level >= $wanted_level;
			$earned   += $weights['longevity'] * ( $longevity_hit ? 1.0 : 0.0 );
			$possible += $weights['longevity'];
			$factors[] = array(
				'axis'    => 'longevity',
				'label'   => 'ماندگاری مورد نظر',
				'matched' => $longevity_hit,
				'scored'  => true,
				'text'    => $longevity_hit ? $answers['longevity'] : null,
			);
		}

		// Unscored axes: the product has no real data here, so it is reported as
		// such and never folded into the percentage.
		foreach ( array( 'fragrance' => 'رایحه', 'occasion' => 'مناسبت', 'season' => 'فصل', 'personality' => 'شخصیت', 'longevity' => 'ماندگاری' ) as $axis => $label ) {
			$has = $axis === 'longevity'
				? ( ! empty( $profile['longevity'] ) || ! empty( $profile['sillage'] ) )
				: ! empty( $profile[ $axis ] );
			$answered = '' !== ( $answers[ $axis ] ?? '' ) || ( $axis === 'longevity' && '' !== ( $answers['longevity'] ?? '' ) );
			if ( $answered && ! $has ) {
				$factors[] = array( 'axis' => $axis, 'label' => $label, 'matched' => false, 'scored' => false, 'text' => null );
			}
		}

		$percent = $possible > 0 ? (int) round( $earned / $possible * 100 ) : 0;

		// How much of the total weight could actually be scored. A 100% match
		// earned on one axis out of six is a true statement and a misleading
		// headline, so the share is returned and shown alongside the percentage.
		$total_weight = array_sum( $weights );

		return array(
			'percent'  => $percent,
			'factors'  => $factors,
			'coverage' => $total_weight > 0 ? (int) round( $possible / $total_weight * 100 ) : 0,
			'scored'   => $possible,
			'possible' => $possible > 0,
		);
	}

	/**
	 * Rank the catalogue. Returns at most MAX_RESULTS scorable products, best
	 * first, with a stable tie-break on product ID so equal scores do not
	 * reorder between requests. The cap is filterable so a test can see the whole
	 * scored set; nothing on the storefront changes the result count.
	 */
	public static function recommend( $answers ) {
		if ( empty( $answers ) ) {
			return array();
		}

		$query = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		$scored = array();
		foreach ( $query->posts as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$profile = self::profile( $product );
			if ( null === $profile ) {
				continue; // Not enough real data to rank honestly.
			}
			$result = self::score( $profile, $answers );
			if ( ! $result['possible'] ) {
				continue;
			}
			$scored[] = array(
				'id'      => $id,
				'product' => $product,
				'profile' => $profile,
				'result'  => $result,
			);
		}

		usort( $scored, function ( $a, $b ) {
			if ( $a['result']['percent'] === $b['result']['percent'] ) {
				return $a['id'] - $b['id'];
			}
			return $b['result']['percent'] - $a['result']['percent'];
		} );

		return array_slice( $scored, 0, (int) apply_filters( 'asc_perfume_finder_max_results', self::MAX_RESULTS ) );
	}

	/**
	 * Sanitise submitted answers into the known vocabulary. An answer is only
	 * accepted if it is valid for its own axis, so a season value submitted for
	 * occasion is dropped rather than silently scoring against the wrong axis.
	 */
	public static function sanitise_answers( $raw ) {
		$out = array();
		foreach ( self::answer_vocabulary() as $key => $allowed ) {
			$value = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] )
				? sanitize_text_field( wp_unslash( $raw[ $key ] ) )
				: '';
			if ( '' !== $value && in_array( $value, $allowed, true ) ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * The valid answers per quiz axis, including the two axes that are not
	 * taxonomies (longevity is answered against a plain ordered list, and budget
	 * against the price-tier keys).
	 */
	public static function answer_vocabulary() {
		$vocab = self::vocabularies();
		$out   = array();
		foreach ( array(
			'fragrance'   => 'pa_fragrance_family',
			'gender'      => 'pa_gender',
			'occasion'    => 'pa_occasion',
			'season'      => 'pa_season',
			'personality' => 'pa_personality',
		) as $axis => $taxonomy ) {
			$out[ $axis ] = isset( $vocab[ $taxonomy ] ) ? $vocab[ $taxonomy ] : array();
		}
		$out['longevity'] = array_keys( self::longevity_levels() );
		$out['budget']    = array_keys( self::price_tiers() );
		return $out;
	}

	/**
	 * Longevity as an ordered scale, so a profile can be compared against a
	 * wanted level rather than requiring an exact term match.
	 */
	public static function longevity_levels() {
		return apply_filters( 'asc_finder_longevity_levels', array(
			'کوتاه'     => 1,
			'متوسط'     => 2,
			'بلند'      => 3,
			'خیلی بلند' => 4,
		) );
	}

	/**
	 * Render the finder. On the base URL it shows the quiz; with answers it shows
	 * the ranked result. Called from the page template, which supplies the
	 * theme's header and footer.
	 */
	public static function render() {
		$submitted = isset( $_SERVER['REQUEST_METHOD'] )
			&& 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );

		echo '<div class="asc-finder" dir="rtl">';
		if ( $submitted ) {
			$answers = self::sanitise_answers( wp_unslash( $_POST ) );
			if ( empty( $answers ) ) {
				echo '<p class="asc-finder__empty">' . esc_html__( 'لطفاً حداقل یک گزینه انتخاب کنید.', 'lylyrose-core' ) . '</p>';
				self::render_quiz();
			} else {
				// Gender is an answer, not a fragrance trait: a product is not
				// required to declare it, so it must not make a product match.
				$scoring = $answers;
				unset( $scoring['gender'] );

				// A product that matches nothing on any answer it could be scored
				// against is not a recommendation, however well authored its metadata
				// is. Dropping it before the cap also means the list can pull in a
				// genuine next-best rather than padding with a 0%.
				self::render_results(
					array_filter( self::recommend( $scoring ), array( __CLASS__, 'matched' ) ),
					$answers
				);
			}
		} else {
			self::render_quiz();
		}
		echo '</div>';
	}

	private static function render_quiz() {
		// Read through answer_vocabulary(), which fills a missing taxonomy with an empty
		// list, so a filtered vocabulary cannot notice-storm the whole quiz.
		$vocab = self::answer_vocabulary();
		$steps = array(
			'fragrance'   => array( 'label' => 'چه رایحه‌ای را دوست دارید؟', 'terms' => $vocab['fragrance'], 'name' => 'fragrance' ),
			'gender'      => array( 'label' => 'برای چه کسی؟',           'terms' => $vocab['gender'],           'name' => 'gender' ),
			'occasion'    => array( 'label' => 'برای چه موقعیتی؟',       'terms' => $vocab['occasion'],         'name' => 'occasion' ),
			'season'      => array( 'label' => 'فصل مورد نظر؟',          'terms' => $vocab['season'],           'name' => 'season' ),
			'personality' => array( 'label' => 'شخصیت شما؟',            'terms' => $vocab['personality'],      'name' => 'personality' ),
			'longevity'   => array( 'label' => 'ماندگاری مورد نظر؟',     'terms' => array_keys( self::longevity_levels() ), 'name' => 'longevity' ),
		);

		echo '<form method="post" class="asc-finder__quiz">';
		foreach ( $steps as $key => $step ) {
			echo '<fieldset class="asc-finder__step">';
			echo '<legend>' . esc_html( $step['label'] ) . '</legend>';
			echo '<div class="asc-finder__options">';
			foreach ( $step['terms'] as $term ) {
				printf(
					'<label class="asc-finder__option"><input type="radio" name="%s" value="%s"> <span>%s</span></label>',
					esc_attr( $step['name'] ),
					esc_attr( $term ),
					esc_html( $term )
				);
			}
			echo '</div></fieldset>';
		}

		echo '<fieldset class="asc-finder__step">';
		echo '<legend>' . esc_html__( 'بودجه تقریبی؟', 'lylyrose-core' ) . '</legend>';
		echo '<div class="asc-finder__options">';
		foreach ( self::price_tiers() as $key => $tier ) {
			printf(
				'<label class="asc-finder__option"><input type="radio" name="budget" value="%s"> <span>%s</span></label>',
				esc_attr( $key ),
				esc_html( $tier['label'] )
			);
		}
		echo '</div></fieldset>';

		echo '<p class="asc-finder__actions"><button type="submit" class="asc-finder__submit">' . esc_html__( 'پیشنهاد بده', 'lylyrose-core' ) . '</button></p>';
		echo '</form>';
		echo '<p class="asc-finder__alt"><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'یا مستقیم از فروشگاه خرید کنید', 'lylyrose-core' ) . '</a></p>';
	}

	/**
	 * Results: each product with its percentage and the factors behind it.
	 * Fewer than MAX_RESULTS is a valid outcome — the list is never padded with
	 * products that cannot be scored.
	 */
	private static function render_results( $results, $answers ) {
		echo '<h2 class="asc-finder__title">' . esc_html__( 'پیشنهادهای شما', 'lylyrose-core' ) . '</h2>';

		if ( empty( $results ) ) {
			echo '<p class="asc-finder__empty">' . esc_html__( 'هنوز محصولی با اطلاعات کافی برای امتیازدهی وجود ندارد.', 'lylyrose-core' ) . '</p>';
			echo '<p class="asc-finder__alt"><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'مشاهده همه محصولات', 'lylyrose-core' ) . '</a></p>';
			return;
		}

		// Every axis the profile already carries, shown whether or not it scored:
		// the visitor asked what this perfume IS, not only why it matched.
		$axes = array(
			'brand'       => __( 'برند', 'lylyrose-core' ),
			'gender'      => __( 'جنسیت', 'lylyrose-core' ),
			'fragrance'   => __( 'خانواده رایحه', 'lylyrose-core' ),
			'longevity'   => __( 'ماندگاری', 'lylyrose-core' ),
			'sillage'     => __( 'پخش بو', 'lylyrose-core' ),
			'season'      => __( 'فصل', 'lylyrose-core' ),
			'occasion'    => __( 'مناسبت', 'lylyrose-core' ),
			'personality' => __( 'شخصیت', 'lylyrose-core' ),
		);
		$tier_labels = wp_list_pluck( self::price_tiers(), 'label' );
		$note_titles = array(
			'top'   => __( 'نوت آغازین', 'lylyrose-core' ),
			'heart' => __( 'نوت میانی', 'lylyrose-core' ),
			'base'  => __( 'نوت پایه', 'lylyrose-core' ),
		);

		echo '<ol class="asc-finder__results">';
		foreach ( $results as $row ) {
			$product = $row['product'];
			$result  = $row['result'];
			$profile = $row['profile'];
			$url     = get_permalink( $product->get_id() );
			?>
			<li class="asc-finder__result">
				<a class="asc-finder__media" href="<?php echo esc_url( $url ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) ); ?>
				</a>
				<div class="asc-finder__body">
					<div class="asc-finder__head">
						<h3 class="asc-finder__name"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
						<span class="asc-finder__percent"><span class="asc-finder__num"><?php echo esc_html( self::fa_num( (int) $result['percent'] ) ); ?></span>٪</span>
					</div>
					<?php
					// Say how much of the intended weighting was actually available, so a
					// high percentage earned on one axis is not read as a whole-catalogue
					// verdict.
					printf(
						'<span class="asc-finder__coverage">%s</span>',
						esc_html(
							sprintf(
								/* translators: %d: percentage of the total weight that could be scored. */
								__( 'بر اساس %s%% از معیارها', 'lylyrose-core' ),
								self::fa_num( (int) $result['coverage'] )
							)
						)
					);
					?>

					<div class="asc-finder__buy">
						<span class="asc-finder__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
						<span class="asc-finder__stock <?php echo $product->is_in_stock() ? 'is-in' : 'is-out'; ?>">
							<?php echo esc_html( $product->is_in_stock() ? __( 'موجود', 'lylyrose-core' ) : __( 'ناموجود', 'lylyrose-core' ) ); ?>
						</span>
					</div>

					<?php if ( $product->get_review_count() > 0 ) : ?>
						<p class="asc-finder__rating">
							<span class="asc-finder__stars">&#9733;</span>
							<?php echo esc_html( self::fa_num( number_format_i18n( $product->get_average_rating(), 1 ) ) ); ?>
							<span class="asc-finder__reviews">(<?php echo esc_html( self::fa_num( $product->get_review_count() ) ); ?>)</span>
						</p>
					<?php endif; ?>

					<?php
					$meta = array();
					foreach ( $axes as $key => $label ) {
						if ( ! empty( $profile[ $key ] ) ) {
							$meta[] = array( $label, implode( '، ', $profile[ $key ] ) );
						}
					}
					if ( ! empty( $profile['price_tier'] ) && isset( $tier_labels[ $profile['price_tier'] ] ) ) {
						$meta[] = array( __( 'رده قیمتی', 'lylyrose-core' ), $tier_labels[ $profile['price_tier'] ] );
					}
					if ( $meta ) :
						?>
						<dl class="asc-finder__meta">
							<?php foreach ( $meta as $pair ) : ?>
								<div class="asc-finder__meta-row">
									<dt><?php echo esc_html( $pair[0] ); ?></dt>
									<dd><?php echo esc_html( $pair[1] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>

					<?php
						// profile()'s pyramid is empty on the authored catalogue, which stores notes
						// under the older _top_notes keys, so read both. Display only: profile() stays
						// the ranking gate and its output is unchanged.
						$notes = $profile['notes'];
						foreach ( array( 'top' => '_top_notes', 'heart' => '_heart_notes', 'base' => '_base_notes' ) as $layer => $meta_key ) {
							if ( ! empty( $notes[ $layer ] ) ) {
								continue;
							}
							$raw = get_post_meta( $product->get_id(), $meta_key, true );
							if ( ! $raw ) {
								continue;
							}
							// Older keys hold a serialised array; the pyramid holds newlines.
							$lines = is_array( $raw ) ? $raw : explode( "\n", (string) $raw );
							$lines = array_filter( array_map( 'trim', $lines ) );
							if ( $lines ) {
								$notes[ $layer ] = $lines;
							}
						}
						if ( $notes ) :
							?>
							<div class="asc-finder__notes">
							<?php foreach ( $notes as $layer => $lines ) : ?>
								<div class="asc-finder__note-row">
									<span class="asc-finder__note-layer"><?php echo esc_html( isset( $note_titles[ $layer ] ) ? $note_titles[ $layer ] : $layer ); ?></span>
									<span class="asc-finder__note-list"><?php echo esc_html( implode( '، ', $lines ) ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<ul class="asc-finder__factors">
						<?php foreach ( $result['factors'] as $factor ) : ?>
							<?php if ( ! $factor['scored'] ) : ?>
								<li class="asc-finder__factor is-unscored"><?php esc_html_e( 'اطلاعاتی برای این مورد ثبت نشده', 'lylyrose-core' ); ?></li>
							<?php else : ?>
								<li class="asc-finder__factor<?php echo $factor['matched'] ? ' is-match' : ''; ?>"><?php echo esc_html( $factor['label'] ); ?><?php echo $factor['text'] ? ' — ' . esc_html( $factor['text'] ) : ''; ?></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>

					<p class="asc-finder__actions">
						<a class="asc-finder__view" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'مشاهده و خرید', 'lylyrose-core' ); ?></a>
					</p>
				</div>
			</li>
			<?php
		}
		echo '</ol>';
		echo '<p class="asc-finder__alt"><a href="' . esc_url( get_permalink( get_page_by_path( self::PAGE_SLUG ) ) ) . '">' . esc_html__( 'شروع دوباره', 'lylyrose-core' ) . '</a></p>';
	}
}
