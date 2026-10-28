<?php
/**
 * Skin care landing page (/skincare/, WordPress page 6546, «مراقبت از پوست»).
 *
 * This file is the page's own configuration; the shared markup lives in
 * care-landing.php. See docs/CARE_LANDING_PLAN.md.
 *
 * Facets come from pa_skin_type / pa_skin_concern, migrated from the products'
 * _needs_types / _needs_concerns meta by docker/migrate-care-attributes.php.
 * Both trees are single-brand St. Ives with no ratings and no sales, so there is
 * no brand facet, no "popular"/"rated" sort, and the closing rail is new
 * arrivals rather than a "best sellers" claim the data cannot support.
 *
 * @package lylyrose
 */

defined( 'ABSPATH' ) || exit;

$care = array(
	'slug'          => 'skincare',
	'root'          => 'skin-care',
	'type_tax'      => 'pa_skin_type',
	'concern_tax'   => 'pa_skin_concern',
	'type_label'    => __( 'نوع پوست', 'lylyrose' ),
	'concern_label' => __( 'نیاز پوست', 'lylyrose' ),
	'quiz_url'      => home_url( '/skin-needs/' ),

	'hero_title'    => __( 'پوست سالم، به سلیقه تو', 'lylyrose' ),
	'hero_copy'     => __( 'از پاک‌کننده و تونر تا مرطوب‌کننده و ضدآفتاب — روتین مناسب نوع پوستت رو پیدا کن.', 'lylyrose' ),
	'hero_cta'      => __( 'مشاهده همه محصولات', 'lylyrose' ),

	'quick_label'   => __( 'راه‌های پیدا کردن محصول', 'lylyrose' ),
	'quiz_card_title' => __( 'پوستت چه نیازی داره؟', 'lylyrose' ),
	'quiz_card_copy'  => __( 'با دو سوال ساده، محصول مناسب نوع پوست و نیازت رو پیشنهاد می‌دیم', 'lylyrose' ),
	'quiz_card_cta'   => __( 'شروع تست پوست', 'lylyrose' ),

	'tiles_title'   => __( 'خرید بر اساس دسته‌بندی', 'lylyrose' ),

	/* Merchandising order; any live subcategory not named here is appended. */
	'tile_order'    => array(
		'facial-cleanser',
		'face-foam',
		'exfoliant-toner',
		'moisturizer',
		'sunscreen',
		'body-scrub',
		'body-care',
	),

	'guide_title'   => __( 'راهنمای مراقبت از پوست', 'lylyrose' ),
	'guide_copy'    => __( 'نوع پوستت رو نمی‌دونی یا بین چند محصول مردد هستی؟ تست کوتاه ما راهنماییت می‌کنه.', 'lylyrose' ),
	'guide_cta'     => __( 'شروع تست پوست', 'lylyrose' ),

	'rail_title'    => __( 'تازه رسیده‌ها', 'lylyrose' ),
	'empty_title'   => __( 'محصولی مطابق فیلترهای شما پیدا نشد', 'lylyrose' ),
	'empty_cta'     => __( 'نمایش همه محصولات', 'lylyrose' ),
);

require __DIR__ . '/care-landing.php';
