<?php
/**
 * Hair care landing page (/haircare/, WordPress page 6547, «مراقبت از مو»).
 *
 * This file is the page's own configuration; the shared markup lives in
 * care-landing.php. See docs/CARE_LANDING_PLAN.md.
 *
 * Facets come from pa_hair_type / pa_hair_concern, migrated from the products'
 * _needs_types / _needs_concerns meta by docker/migrate-care-attributes.php.
 * Both trees are single-brand St. Ives with no ratings and no sales, so there is
 * no brand facet, no "popular"/"rated" sort, and the closing rail is new
 * arrivals rather than a "best sellers" claim the data cannot support.
 *
 * @package lylyrose
 */

defined( 'ABSPATH' ) || exit;

$care = array(
	'slug'          => 'haircare',
	'root'          => 'hair-care',
	'type_tax'      => 'pa_hair_type',
	'concern_tax'   => 'pa_hair_concern',
	'type_label'    => __( 'بافت مو', 'lylyrose' ),
	'concern_label' => __( 'نیاز مو', 'lylyrose' ),

	'quiz_url'      => home_url( '/hair-needs/' ),

	'hero_title'    => __( 'موی سالم، از ریشه تا نوک', 'lylyrose' ),
	'hero_copy'     => __( 'شامپو، ماسک، روغن و محصولات حالت‌دهنده — برای هر بافت و هر نیاز مو.', 'lylyrose' ),
	'hero_cta'      => __( 'مشاهده همه محصولات', 'lylyrose' ),

	'quick_label'   => __( 'راه‌های پیدا کردن محصول', 'lylyrose' ),
	'quiz_card_title' => __( 'موهات چه نیازی داره؟', 'lylyrose' ),
	'quiz_card_copy'  => __( 'با دو سوال ساده، محصول مناسب بافت و نیاز موهات رو پیشنهاد می‌دیم', 'lylyrose' ),
	'quiz_card_cta'   => __( 'شروع تست مو', 'lylyrose' ),

	'tiles_title'   => __( 'خرید بر اساس دسته‌بندی', 'lylyrose' ),

	/* Merchandising order; any live subcategory not named here is appended. */
	'tile_order'    => array(
		'hair-treatment',
		'hair-styling',
		'hair-oil',
	),

	'guide_title'   => __( 'راهنمای مراقبت از مو', 'lylyrose' ),
	'guide_copy'    => __( 'بافت موهات رو نمی‌دونی یا بین چند محصول مردد هستی؟ تست کوتاه ما راهنماییت می‌کنه.', 'lylyrose' ),
	'guide_cta'     => __( 'شروع تست مو', 'lylyrose' ),

	'rail_title'    => __( 'تازه رسیده‌ها', 'lylyrose' ),
	'empty_title'   => __( 'محصولی مطابق فیلترهای شما پیدا نشد', 'lylyrose' ),
	'empty_cta'     => __( 'نمایش همه محصولات', 'lylyrose' ),
);

require __DIR__ . '/care-landing.php';
