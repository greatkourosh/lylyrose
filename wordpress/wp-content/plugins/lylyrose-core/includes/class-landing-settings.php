<?php
/**
 * Landing-page content panel (Settings → گزینههای کوییزها → صفحات فرود).
 *
 * Everything on /perfume/ that the owner may rewrite — the hero photo, the three
 * quick-card photos, the seven scent-family tiles, the guide banner and the copy
 * around them — is hardcoded in the theme. This is where it becomes editable.
 *
 * The theme never reads this class. It asks by filter (lylyrose_asset_url /
 * lylyrose_text_value, declared in themes/lylyrose/functions.php) and falls back
 * to the bundled file or the inline default when nothing answers. So the theme
 * keeps rendering with this plugin switched off, and no asc_* name crosses into
 * it — the boundary docs/UPSTREAM_RELATIONSHIP.md draws.
 *
 * Images are stored as attachment IDs, never URLs: a URL pins the image to
 * whichever host it was picked from (this site answers on more than one) and
 * absint() would collapse it to 0. The same trap lylyrose_customize_register()
 * documents for hero slides.
 *
 * @package lylyrose-core
 */

defined( 'ABSPATH' ) || exit;

class ASC_Landing_Settings {

	/**
	 * Editable area => option name. One option per area, see sanitize().
	 *
	 * 'contact' is not a landing page: it is the store-wide footer and contact
	 * details, which belong to every page rather than to one. It rides in the same
	 * registry because the mechanism is identical.
	 */
	const PAGES = array(
		'perfume'  => 'asc_landing_perfume',
		'skincare' => 'asc_landing_skincare',
		'haircare' => 'asc_landing_haircare',
		'contact'  => 'asc_contact',
	);

	public static function init() {
		// Front-end too, not just admin: the filters the theme asks through have to
		// answer a page render. Same reason ASC_Palette::init() sits in
		// includes_frontend() rather than the WooCommerce block.
		add_filter( 'lylyrose_asset_url', array( __CLASS__, 'filter_asset' ), 10, 2 );
		add_filter( 'lylyrose_text_value', array( __CLASS__, 'filter_text' ), 10, 2 );

		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * The single source of truth: one entry drives the form row, the sanitizer and
	 * the reader. Add a field here and all three follow.
	 *
	 * 'default' is the file path or string the template already renders, repeated
	 * here so the panel can preview and so a save can tell "unchanged" from "cleared".
	 */
	public static function schema( $page ) {
		$img = 'assets/images/' . $page . '/';

		if ( 'perfume' === $page ) {
			return array(
				'perfume.hero'            => array( 'type' => 'image', 'label' => __( 'تصویر بنر بزرگ (هیرو)', 'lylyrose-core' ), 'default' => $img . 'hero.webp' ),
				'perfume.quiz'            => array( 'type' => 'image', 'label' => __( 'عکس کارت «کمکم کن عطر انتخاب کنم»', 'lylyrose-core' ), 'default' => $img . 'quiz.webp' ),
				'perfume.quick_a'         => array( 'type' => 'image', 'label' => __( 'عکس کارت «میخوام خودم بگردم»', 'lylyrose-core' ), 'default' => $img . 'quick-a.webp' ),
				'perfume.quick_b'         => array( 'type' => 'image', 'label' => __( 'عکس کارت «عطرم رو میشناسم»', 'lylyrose-core' ), 'default' => $img . 'quick-b.webp' ),
				'perfume.family.floral'   => array( 'type' => 'image', 'label' => __( 'تایل رایحه — گلی', 'lylyrose-core' ), 'default' => $img . 'family-floral.webp' ),
				'perfume.family.aquatic'  => array( 'type' => 'image', 'label' => __( 'تایل رایحه — آکواتیک', 'lylyrose-core' ), 'default' => $img . 'family-aquatic.webp' ),
				'perfume.family.fruity'   => array( 'type' => 'image', 'label' => __( 'تایل رایحه — میوهای', 'lylyrose-core' ), 'default' => $img . 'family-fruity.webp' ),
				'perfume.family.woody'    => array( 'type' => 'image', 'label' => __( 'تایل رایحه — چوبی', 'lylyrose-core' ), 'default' => $img . 'family-woody.webp' ),
				'perfume.family.spicy'    => array( 'type' => 'image', 'label' => __( 'تایل رایحه — ادویهای', 'lylyrose-core' ), 'default' => $img . 'family-spicy.webp' ),
				'perfume.family.oriental' => array( 'type' => 'image', 'label' => __( 'تایل رایحه — شرقی', 'lylyrose-core' ), 'default' => $img . 'family-oriental.webp' ),
				'perfume.family.all'      => array( 'type' => 'image', 'label' => __( 'تایل رایحه — همه رایحهها', 'lylyrose-core' ), 'default' => $img . 'family-all.webp' ),
				'perfume.guide'           => array( 'type' => 'image', 'label' => __( 'عکس بنر راهنمای انتخاب', 'lylyrose-core' ), 'default' => $img . 'guide.webp' ),

				'perfume.hero_title'      => array( 'type' => 'text', 'label' => __( 'هیرو — عنوان', 'lylyrose-core' ), 'default' => __( 'دنیای عطرها، به سلیقه تو', 'lylyrose' ) ),
				'perfume.hero_copy'       => array( 'type' => 'text', 'label' => __( 'هیرو — توضیح', 'lylyrose-core' ), 'default' => __( 'از رایحههای خنک و روزانه تا عطرهای گرم و ماندگار، عطر دلخواهت رو پیدا کن.', 'lylyrose' ) ),
				'perfume.hero_cta'        => array( 'type' => 'text', 'label' => __( 'هیرو — متن دکمه', 'lylyrose-core' ), 'default' => __( 'مشاهده همه عطرها', 'lylyrose' ) ),
				'perfume.quiz_title'      => array( 'type' => 'text', 'label' => __( 'کارت تست — عنوان', 'lylyrose-core' ), 'default' => __( 'کمکم کن عطر انتخاب کنم', 'lylyrose' ) ),
				'perfume.quiz_copy'       => array( 'type' => 'text', 'label' => __( 'کارت تست — توضیح', 'lylyrose-core' ), 'default' => __( 'هنوز انتخاب‌ت رو نمی‌دونی؟ با چند سوال ساده بهترین گزینه‌ها رو پیشنهاد می‌دیم', 'lylyrose' ) ),
				'perfume.quiz_cta'        => array( 'type' => 'text', 'label' => __( 'کارت تست — متن دکمه', 'lylyrose-core' ), 'default' => __( 'شروع تست انتخاب عطر', 'lylyrose' ) ),
				'perfume.quick_a_title'   => array( 'type' => 'text', 'label' => __( 'کارت فیلتر — عنوان', 'lylyrose-core' ), 'default' => __( 'می‌خوام خودم بگردم', 'lylyrose' ) ),
				'perfume.quick_a_copy'    => array( 'type' => 'text', 'label' => __( 'کارت فیلتر — توضیح', 'lylyrose-core' ), 'default' => __( 'با فیلترها و دسته‌بندی‌ها عطر مناسب خودت رو پیدا کن', 'lylyrose' ) ),
				'perfume.quick_b_title'   => array( 'type' => 'text', 'label' => __( 'کارت جستجو — عنوان', 'lylyrose-core' ), 'default' => __( 'عطرم رو می‌شناسم', 'lylyrose' ) ),
				'perfume.quick_b_copy'    => array( 'type' => 'text', 'label' => __( 'کارت جستجو — توضیح', 'lylyrose-core' ), 'default' => __( 'اسم عطر یا برندش رو می‌دونی؟ همین‌جا جستجو کن', 'lylyrose' ) ),
				'perfume.families_title'  => array( 'type' => 'text', 'label' => __( 'عنوان بخش رایحه‌ها', 'lylyrose-core' ), 'default' => __( 'انتخاب سریع بر اساس رایحه', 'lylyrose' ) ),
				'perfume.guide_title'     => array( 'type' => 'text', 'label' => __( 'بنر راهنما — عنوان', 'lylyrose-core' ), 'default' => __( 'راهنمای انتخاب عطر', 'lylyrose' ) ),
				'perfume.guide_copy'      => array( 'type' => 'text', 'label' => __( 'بنر راهنما — توضیح', 'lylyrose-core' ), 'default' => __( 'با چند سوال کوتاه، عطر مناسب شخصیت و سلیقه‌ات رو پیدا کن.', 'lylyrose' ) ),
				'perfume.guide_cta'       => array( 'type' => 'text', 'label' => __( 'بنر راهنما — متن دکمه', 'lylyrose-core' ), 'default' => __( 'شروع تست انتخاب عطر', 'lylyrose' ) ),
			);
		}

		if ( 'skincare' === $page || 'haircare' === $page ) {
			return self::care_schema( $page );
		}

		if ( 'contact' === $page ) {
			return self::contact_schema();
		}

		return array();
	}

	/**
	 * Store-wide details: the footer's own text and the four social links, plus the
	 * support phone.
	 *
	 * The social fields have no default — the markup ships them as href="#" because
	 * the shop has no accounts yet, and a default that guessed a handle would be
	 * worse than none. Empty means the link keeps rendering as "#"; the reader
	 * (lylyrose_contact) turns an empty social into '' so the template can decide.
	 */
	private static function contact_schema() {
		return array(
			'contact.footer_about' => array(
				'type'    => 'text',
				'label'   => __( 'فوتر — متن معرفی فروشگاه', 'lylyrose-core' ),
				'default' => __( 'فروشگاه اینترنتی لیلی رز با هزاران محصول، ضمانت اصالت کالا و ارسال سریع به سراسر ایران. خرید آسان و مطمئن را تجربه کنید.', 'lylyrose' ),
			),
			'contact.footer_services' => array(
				'type'    => 'text',
				'label'   => __( 'فوتر — چهار مزیت (با کاما جدا کنید)', 'lylyrose-core' ),
				'default' => __( 'امکان تحویل اکسپرس, پرداخت در محل, ۷ روز ضمانت بازگشت, ضمانت اصل بودن کالا', 'lylyrose' ),
			),
			'contact.copyright' => array(
				'type'    => 'text',
				'label'   => __( 'فوتر — خط کپی‌رایت', 'lylyrose-core' ),
				'default' => __( '© تمامی حقوق برای لیلی رز محفوظ است.', 'lylyrose' ),
			),

			'contact.phone' => array(
				'type'    => 'tel',
				'label'   => __( 'تلفن پشتیبانی', 'lylyrose-core' ),
				'default' => __( '۰۲۱-۰۰۰۰۰۰۰۰ (شنبه تا پنجشنبه، ۹ تا ۱۸)', 'lylyrose' ),
			),
			'contact.phone_link' => array(
				'type'    => 'tel',
				'label'   => __( 'تلفن برای لینک تماس (فقط رقم، مثل 02112345678)', 'lylyrose-core' ),
				'default' => '',
			),
			'contact.contact_lead' => array(
				'type'    => 'text',
				'label'   => __( 'صفحهٔ تماس — متن معرفی', 'lylyrose-core' ),
				'default' => __( 'هر سؤال، انتقاد یا پیشنهادی دارید برای ما بنویسید. پیام‌ها در ساعات کاری بررسی و پاسخ داده می‌شوند.', 'lylyrose' ),
			),

			'contact.social_instagram' => array( 'type' => 'url', 'label' => __( 'اینستاگرام — نشانی', 'lylyrose-core' ), 'default' => '' ),
			'contact.social_telegram'  => array( 'type' => 'url', 'label' => __( 'تلگرام — نشانی', 'lylyrose-core' ), 'default' => '' ),
			'contact.social_twitter'   => array( 'type' => 'url', 'label' => __( 'توییتر — نشانی', 'lylyrose-core' ), 'default' => '' ),
			'contact.social_linkedin'  => array( 'type' => 'url', 'label' => __( 'لینکدین — نشانی', 'lylyrose-core' ), 'default' => '' ),
		);
	}

	/**
	 * skincare and haircare are the same page wearing a different config: the shared
	 * body is care-landing.php, and each page-{slug}.php supplies a $care array. So
	 * the fixed fields are identical and only the tile list differs.
	 *
	 * Tiles are per-subcategory and their bundled files are named after the term slug
	 * (care-landing.php builds `tile-{term}.webp`), so a tile the shop later adds has
	 * no field here — it keeps rendering through the template's own
	 * lylyrose_term_image() fallback until a field is added for it.
	 */
	private static function care_schema( $page ) {
		$img = 'assets/images/' . $page . '/';

		$fields = array(
			$page . '.hero'        => array( 'type' => 'image', 'label' => __( 'تصویر بنر بزرگ (هیرو)', 'lylyrose-core' ), 'default' => $img . 'hero.webp' ),
			$page . '.quick_quiz'  => array( 'type' => 'image', 'label' => __( 'عکس کارت تست', 'lylyrose-core' ), 'default' => $img . 'quick-bg-quiz.webp' ),
			$page . '.quick_a'     => array( 'type' => 'image', 'label' => __( 'عکس کارت «میخوام خودم بگردم»', 'lylyrose-core' ), 'default' => $img . 'quick-bg-a.webp' ),
			$page . '.quick_b'     => array( 'type' => 'image', 'label' => __( 'عکس کارت «محصول رو میشناسم»', 'lylyrose-core' ), 'default' => $img . 'quick-bg-b.webp' ),
			$page . '.guide'       => array( 'type' => 'image', 'label' => __( 'عکس بنر راهنما', 'lylyrose-core' ), 'default' => $img . 'guide.webp' ),
		);

		foreach ( self::care_tiles( $page ) as $slug => $label ) {
			$fields[ $page . '.tile.' . $slug ] = array(
				'type'    => 'image',
				'label'   => sprintf( /* translators: %s: subcategory name. */ __( 'تایل دسته — %s', 'lylyrose-core' ), $label ),
				'default' => $img . 'tile-' . $slug . '.webp',
			);
		}

		// A tile is the one image whose bundled file may be absent: the template uses
		// tile-{slug}.webp only when that file exists and otherwise falls back to the
		// term's own product image. So the 'default' above names where the override
		// would live, not a file guaranteed to be there.
		$copy = array(
			'hero_title'      => __( 'هیرو — عنوان', 'lylyrose-core' ),
			'hero_copy'       => __( 'هیرو — توضیح', 'lylyrose-core' ),
			'hero_cta'        => __( 'هیرو — متن دکمه', 'lylyrose-core' ),
			'quiz_card_title' => __( 'کارت تست — عنوان', 'lylyrose-core' ),
			'quiz_card_copy'  => __( 'کارت تست — توضیح', 'lylyrose-core' ),
			'quiz_card_cta'   => __( 'کارت تست — متن دکمه', 'lylyrose-core' ),
			'quick_a_title'   => __( 'کارت فیلتر — عنوان', 'lylyrose-core' ),
			'quick_a_copy'    => __( 'کارت فیلتر — توضیح', 'lylyrose-core' ),
			'quick_b_title'   => __( 'کارت جستجو — عنوان', 'lylyrose-core' ),
			'quick_b_copy'    => __( 'کارت جستجو — توضیح', 'lylyrose-core' ),
			'tiles_title'     => __( 'عنوان بخش دستهها', 'lylyrose-core' ),
			'guide_title'     => __( 'بنر راهنما — عنوان', 'lylyrose-core' ),
			'guide_copy'      => __( 'بنر راهنما — توضیح', 'lylyrose-core' ),
			'guide_cta'       => __( 'بنر راهنما — متن دکمه', 'lylyrose-core' ),
			'rail_title'      => __( 'عنوان ردیف تازهها', 'lylyrose-core' ),
			'empty_title'     => __( 'حالت خالی — عنوان', 'lylyrose-core' ),
			'empty_copy'      => __( 'حالت خالی — توضیح', 'lylyrose-core' ),
			'empty_cta'       => __( 'حالت خالی — متن دکمه', 'lylyrose-core' ),
		);

		$defaults = self::care_copy_defaults( $page );
		foreach ( $copy as $key => $label ) {
			$fields[ $page . '.' . $key ] = array(
				'type'    => 'text',
				'label'   => $label,
				'default' => isset( $defaults[ $key ] ) ? $defaults[ $key ] : '',
			);
		}

		return $fields;
	}

	/**
	 * Subcategory tiles, slug => display name. Mirrors each config's tile_order.
	 * A slug that is not a live term simply never renders.
	 */
	private static function care_tiles( $page ) {
		if ( 'skincare' === $page ) {
			return array(
				'facial-cleanser' => __( 'پاک‌کننده صورت', 'lylyrose-core' ),
				'face-foam'       => __( 'فوم صورت', 'lylyrose-core' ),
				'exfoliant-toner' => __( 'تونر و لایه‌بردار', 'lylyrose-core' ),
				'moisturizer'     => __( 'مرطوب‌کننده', 'lylyrose-core' ),
				'sunscreen'       => __( 'ضدآفتاب', 'lylyrose-core' ),
				'body-scrub'      => __( 'اسکراب بدن', 'lylyrose-core' ),
				'body-care'       => __( 'مراقبت بدن', 'lylyrose-core' ),
			);
		}
		return array(
			'hair-treatment' => __( 'ماسک و درمان مو', 'lylyrose-core' ),
			'hair-styling'   => __( 'حالت‌دهنده', 'lylyrose-core' ),
			'hair-oil'       => __( 'روغن مو', 'lylyrose-core' ),
		);
	}

	/**
	 * The copy the templates already render, repeated here so the panel can show it
	 * as the default. Keys match the $care array in page-{slug}.php, plus the three
	 * the template hardcodes (quick_a/quick_b are the same on both pages; empty_copy
	 * likewise).
	 */
	private static function care_copy_defaults( $page ) {
		$shared = array(
			'hero_cta'        => __( 'مشاهده همه محصولات', 'lylyrose' ),
			'tiles_title'     => __( 'خرید بر اساس دسته‌بندی', 'lylyrose' ),
			'rail_title'      => __( 'تازه رسیده‌ها', 'lylyrose' ),
			'empty_title'     => __( 'محصولی مطابق فیلترهای شما پیدا نشد', 'lylyrose' ),
			'empty_copy'      => __( 'فیلترها را تغییر دهید یا همه محصولات را ببینید.', 'lylyrose' ),
			'empty_cta'       => __( 'نمایش همه محصولات', 'lylyrose' ),
			'quick_a_title'   => __( 'می‌خوام خودم بگردم', 'lylyrose' ),
			'quick_a_copy'    => __( 'با فیلتر نوع و نیاز، محصول مناسب خودت رو پیدا کن', 'lylyrose' ),
			'quick_b_title'   => __( 'محصول رو می‌شناسم', 'lylyrose' ),
			'quick_b_copy'    => __( 'اسم محصول یا برندش رو می‌دونی؟ همین‌جا جستجو کن', 'lylyrose' ),
		);

		if ( 'skincare' === $page ) {
			return $shared + array(
				'hero_title'      => __( 'پوست سالم، به سلیقه تو', 'lylyrose' ),
				'hero_copy'       => __( 'از پاک‌کننده و تونر تا مرطوب‌کننده و ضدآفتاب — روتین مناسب نوع پوستت رو پیدا کن.', 'lylyrose' ),
				'quiz_card_title' => __( 'پوستت چه نیازی داره؟', 'lylyrose' ),
				'quiz_card_copy'  => __( 'با دو سوال ساده، محصول مناسب نوع پوست و نیازت رو پیشنهاد می‌دیم', 'lylyrose' ),
				'quiz_card_cta'   => __( 'شروع تست پوست', 'lylyrose' ),
				'guide_title'     => __( 'راهنمای مراقبت از پوست', 'lylyrose' ),
				'guide_copy'      => __( 'نوع پوستت رو نمی‌دونی یا بین چند محصول مردد هستی؟ تست کوتاه ما راهنماییت می‌کنه.', 'lylyrose' ),
				'guide_cta'       => __( 'شروع تست پوست', 'lylyrose' ),
			);
		}

		return $shared + array(
			'hero_title'      => __( 'موی سالم، از ریشه تا نوک', 'lylyrose' ),
			'hero_copy'       => __( 'شامپو، ماسک، روغن و محصولات حالت‌دهنده — برای هر بافت و هر نیاز مو.', 'lylyrose' ),
			'quiz_card_title' => __( 'موهات چه نیازی داره؟', 'lylyrose' ),
			'quiz_card_copy'  => __( 'با دو سوال ساده، محصول مناسب بافت و نیاز موهات رو پیشنهاد می‌دیم', 'lylyrose' ),
			'quiz_card_cta'   => __( 'شروع تست مو', 'lylyrose' ),
			'guide_title'     => __( 'راهنمای مراقبت از مو', 'lylyrose' ),
			'guide_copy'      => __( 'بافت موهات رو نمی‌دونی یا بین چند محصول مردد هستی؟ تست کوتاه ما راهنماییت می‌کنه.', 'lylyrose' ),
			'guide_cta'       => __( 'شروع تست مو', 'lylyrose' ),
		);
	}

	/** Section heading per page, matching the hub's own names for the same pages. */
	public static function labels() {
		return array(
			'perfume'  => __( 'صفحهٔ عطر', 'lylyrose-core' ),
			'skincare' => __( 'صفحهٔ مراقبت از پوست', 'lylyrose-core' ),
			'haircare' => __( 'صفحهٔ مراقبت از مو', 'lylyrose-core' ),
			'contact'  => __( 'فوتر و اطلاعات تماس', 'lylyrose-core' ),
		);
	}

	public static function register_settings() {
		foreach ( self::PAGES as $page => $option ) {
			register_setting(
				$option,
				$option,
				array(
					'type'              => 'array',
					'sanitize_callback' => function ( $in ) use ( $page, $option ) {
						return self::sanitize( $page, $option, $in );
					},
					'default'           => array(),
				)
			);
		}
	}

	/**
	 * One option per page, and this always replaces the whole array — so a shared
	 * option would let a save on /perfume/ wipe the skincare fields. Keeping them
	 * apart is what makes each tab's form safe on its own.
	 *
	 * An empty or cleared field is dropped rather than stored, so the read side
	 * falls through to the bundled default. "Never set" and "cleared" are the same
	 * state on purpose: both mean "use what the theme ships".
	 */
	public static function sanitize( $page, $option, $in ) {
		$in = (array) $in;

		if ( ! empty( $in['reset'] ) ) {
			delete_option( $option );
			return array();
		}

		$schema = self::schema( $page );
		$clean  = array();

		foreach ( $schema as $key => $field ) {
			$raw = isset( $in[ $key ] ) ? $in[ $key ] : '';

			switch ( $field['type'] ) {
				case 'image':
					$value = absint( $raw );
					break;
				case 'url':
					$value = esc_url_raw( trim( (string) wp_unslash( $raw ) ) );
					break;
				case 'text':
				case 'tel':
				default:
					$value = sanitize_text_field( wp_unslash( $raw ) );
			}

			if ( '' !== $value && 0 !== $value ) {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Theme callback: attachment id => root-relative path.
	 *
	 * Root-relative for the reason lylyrose_hero_photo() gives — the site answers
	 * on more than one host, and an absolute URL would pin the photo to whichever
	 * one it was saved from.
	 */
	public static function filter_asset( $value, $key ) {
		$page = self::page_of( $key );
		if ( ! $page ) {
			return $value;
		}
		$saved = self::saved( $page );
		$id    = isset( $saved[ $key ] ) ? (int) $saved[ $key ] : 0;
		if ( ! $id ) {
			return $value;
		}
		$url = wp_get_attachment_image_url( $id, 'full' );
		if ( ! $url ) {
			return $value;
		}
		return wp_parse_url( $url, PHP_URL_PATH ) ?: $value;
	}

	public static function filter_text( $value, $key ) {
		$page = self::page_of( $key );
		if ( ! $page ) {
			return $value;
		}
		$saved = self::saved( $page );
		return isset( $saved[ $key ] ) && '' !== $saved[ $key ] ? $saved[ $key ] : $value;
	}

	/** "perfume.hero_title" => "perfume", else ''. */
	private static function page_of( $key ) {
		$dot  = strpos( $key, '.' );
		$page = false === $dot ? '' : substr( $key, 0, $dot );
		return isset( self::PAGES[ $page ] ) ? $page : '';
	}

	/** Read once per request: the filter fires for every image and string on a page. */
	private static function saved( $page ) {
		static $cache = array();
		if ( ! isset( $cache[ $page ] ) ) {
			$cache[ $page ] = (array) get_option( self::PAGES[ $page ], array() );
		}
		return $cache[ $page ];
	}

	/* ---------------- Admin ---------------- */

	/**
	 * The media library modal, and the small handler that fills the hidden inputs.
	 * The handler is inline: it is ~20 lines and a whole asset file for it would be
	 * one more thing to version and enqueue.
	 */
	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_' . ASC_Admin_Hub::PAGE !== $screen->id ) {
			return;
		}
		wp_enqueue_media();
		wp_add_inline_script( 'media-editor', self::picker_js() );
	}

	private static function picker_js() {
		return <<<'JS'
jQuery( function ( $ ) {
	$( document ).on( 'click', '.asc-pick', function ( e ) {
		e.preventDefault();
		var row   = $( this ).closest( '.asc-pick-row' );
		var input = row.find( 'input[type=hidden]' );
		var img   = row.find( 'img' );
		var frame = wp.media( { library: { type: 'image' }, multiple: false } );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			input.val( att.id );
			var src = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
			img.attr( 'src', src ).show();
			row.find( '.asc-remove' ).show();
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.asc-remove', function ( e ) {
		e.preventDefault();
		var row = $( this ).closest( '.asc-pick-row' );
		row.find( 'input[type=hidden]' ).val( '' );
		row.find( 'img' ).hide();
		$( this ).hide();
	} );
} );
JS;
	}

	/**
	 * One page's form. Rendered inside the hub, which drops the outer wrap and
	 * demotes the h1 — see ASC_Admin_Hub::unwrap().
	 */
	public static function render_admin( $page ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$option = self::PAGES[ $page ];
		$schema = self::schema( $page );
		$saved  = (array) get_option( $option, array() );

		$labels = self::labels();
		$title  = isset( $labels[ $page ] ) ? $labels[ $page ] : $page;

		echo '<div class="wrap" dir="rtl"><h1>' . esc_html( $title ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( $option );
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( $schema as $key => $field ) {
			$id  = esc_attr( 'asc-f-' . str_replace( '.', '-', $key ) );
			$val = isset( $saved[ $key ] ) ? $saved[ $key ] : '';

			echo '<tr><th scope="row"><label for="' . $id . '">' . esc_html( $field['label'] ) . '</label></th><td>';

			if ( 'image' === $field['type'] ) {
				$preview = '';
				if ( $val ) {
					$preview = (string) wp_get_attachment_image_url( (int) $val, 'medium' );
				}
				echo '<div class="asc-pick-row">';
				printf( '<input type="hidden" id="%s" name="%s[%s]" value="%s">', $id, esc_attr( $option ), esc_attr( $key ), esc_attr( $val ) );
				printf(
					'<p><img src="%s" style="max-width:220px;height:auto;border:1px solid #dcdcde;border-radius:4px;%s" alt=""></p>',
					esc_url( $preview ),
					$preview ? '' : 'display:none'
				);
				echo '<p>';
				echo '<button type="button" class="button asc-pick">' . esc_html__( 'انتخاب تصویر', 'lylyrose-core' ) . '</button> ';
				printf(
					'<button type="button" class="button-link asc-remove" style="%s">%s</button>',
					$val ? '' : 'display:none',
					esc_html__( 'حذف', 'lylyrose-core' )
				);
				echo '</p>';
				printf(
					'<p class="description">%s</p>',
					esc_html( sprintf( /* translators: %s: bundled asset path. */ __( 'اگر تصویری انتخاب نشود، فایل همراه تم استفاده می‌شود: %s', 'lylyrose-core' ), $field['default'] ) )
				);
				echo '</div>';
			} else {
				// URLs and phone numbers are Latin/digits and need ltr; Persian copy
				// stays auto so an RTL string is not mirrored.
				$ltr  = in_array( $field['type'], array( 'url', 'tel' ), true );
				$type = 'url' === $field['type'] ? 'url' : 'text';
				printf(
					'<input type="%s" id="%s" name="%s[%s]" value="%s" class="%s" dir="%s">',
					$type,
					$id,
					esc_attr( $option ),
					esc_attr( $key ),
					esc_attr( (string) $val ),
					$ltr ? 'regular-text code' : 'large-text',
					$ltr ? 'ltr' : 'auto'
				);
				if ( '' !== $field['default'] ) {
					printf(
						'<p class="description">%s</p>',
						esc_html( sprintf( /* translators: %s: the default copy. */ __( 'پیش‌فرض: %s', 'lylyrose-core' ), $field['default'] ) )
					);
				}
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		printf(
			'<p><label><input type="checkbox" name="%s[reset]" value="1"> %s</label></p>',
			esc_attr( $option ),
			esc_html__( 'بازگشت همهٔ فیلدها به پیش‌فرض تم', 'lylyrose-core' )
		);
		submit_button();
		echo '</form></div>';
	}
}
