<?php
defined( 'ABSPATH' ) || exit;
get_header();
if ( ! class_exists( 'ASC_Flash_Sales' ) || ! function_exists( 'WC' ) ) {
	get_footer();
	return;
}
$tab = absint( ASC_Flash_Sales::param( 'offer_cat', 0 ) );
$sort = ASC_Flash_Sales::param( 'sort', 'newest' );
$stock_only = ASC_Flash_Sales::param( 'in_stock' ) === '1';
$base = get_permalink();
$params = array( 'offer_cat' => $tab, 'sort' => $sort, 'in_stock' => $stock_only ? 1 : 0 );
$rows    = ASC_Flash_Sales::carousel_rows();
$offered = 0;
foreach ( $rows as $row ) {
	$offered += count( $row['ids'] );
}
?>
<main class="dk-incredible-page dk-container">
	<section class="dk-flash-hero">
		<svg width="68" height="68" viewBox="0 0 64 64" fill="none" aria-hidden="true"><path d="M10 23h44v32H10zM6 15h52v10H6zM32 15v40M32 15C12 15 16-2 25 6l7 9Zm0 0C52 15 48-2 39 6l-7 9Z" stroke="currentColor" stroke-width="3"/></svg>
		<div><h1>پیشنهادهای شگفت‌انگیز</h1><p>فرصت خرید عطرهای محبوب با قیمت ویژه</p></div>
	</section>
	<nav class="dk-flash-tabs" aria-label="دسته‌بندی پیشنهادها">
		<a class="dk-flash-tab <?php echo ! $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array_merge( $params, array( 'offer_cat' => 0 ) ), $base ) ); ?>" <?php echo ! $tab ? 'aria-current="page"' : ''; ?>><span class="dk-offers-all">٪</span>همه شگفت‌انگیزها</a>
		<?php foreach ( ASC_Flash_Sales::top_categories() as $term ) : ?>
		<a class="dk-flash-tab <?php echo $tab === $term->term_id ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array_merge( $params, array( 'offer_cat' => $term->term_id ) ), $base ) ); ?>" <?php echo $tab === $term->term_id ? 'aria-current="page"' : ''; ?>>
			<?php $image = lylyrose_term_image( $term ); ?>
			<img src="<?php echo esc_url( $image ? $image : wc_placeholder_img_src( "woocommerce_thumbnail" ) ); ?>" alt="" width="72" height="72" loading="lazy">
			<?php echo esc_html( $term->name ); ?>
		</a>
		<?php endforeach; ?>
	</nav>
	<div class="dk-offers-heading"><h2>همه شگفت‌انگیزها</h2><span><?php echo esc_html( number_format_i18n( $offered ) ); ?> کالا</span></div>
	<form method="get" action="<?php echo esc_url( $base ); ?>" class="dk-offers-toolbar">
		<input type="hidden" name="offer_cat" value="<?php echo esc_attr( $tab ); ?>">
		<label>مرتب‌سازی:
			<select name="sort">
			<?php foreach ( array( 'newest' => 'جدیدترین', 'popular' => 'پرفروش‌ترین', 'cheapest' => 'ارزان‌ترین', 'expensive' => 'گران‌ترین' ) as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $sort, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
			</select>
		</label>
		<label><input type="checkbox" name="in_stock" value="1" <?php checked( $stock_only ); ?>> فقط کالاهای موجود</label>
		<button type="submit">اعمال</button>
		<?php if ( $tab || $stock_only || $sort !== 'newest' ) : ?><a href="<?php echo esc_url( $base ); ?>">حذف فیلترها</a><?php endif; ?>
	</form>
	<?php if ( $rows ) : ?>
	<?php foreach ( $rows as $row ) : ?>
	<section class="dk-flash-row <?php echo $row['hero'] ? 'dk-flash-row--hero' : ''; ?>" data-row="<?php echo esc_attr( $row['key'] ); ?>">
		<div class="dk-flash-row-head">
			<h2 class="dk-flash-row-title"><?php echo esc_html( $row['title'] ); ?></h2>
			<?php if ( $row['href'] ) : ?><a class="dk-flash-row-more" href="<?php echo esc_url( $row['href'] ); ?>">مشاهده همه</a><?php endif; ?>
			<div class="dk-flash-row-nav">
				<button type="button" class="dk-flash-row-btn" data-dk-row-prev aria-label="قبلی"></button>
				<button type="button" class="dk-flash-row-btn" data-dk-row-next aria-label="بعدی"></button>
			</div>
		</div>
		<ul class="dk-flash-track" data-dk-row-track>
			<?php foreach ( $row['ids'] as $product_id ) :
				$product = wc_get_product( $product_id );
				if ( ! $product || ! $product->is_visible() ) { continue; }
				$end = ASC_Flash_Sales::sale_end( $product );
				$stock = ASC_Flash_Sales::stock_left( $product );
				$percent = lylyrose_discount_percent( $product );
			?>
			<li class="dk-flash-card" <?php echo $end ? 'data-end="' . esc_attr( $end ) . '"' : ''; ?>>
				<span class="dk-offer-label">شگفت‌انگیز</span>
				<a class="dk-offer-product" href="<?php echo esc_url( $product->get_permalink() ); ?>">
					<?php echo $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ); ?>
					<h3><?php echo esc_html( $product->get_name() ); ?></h3>
				</a>
				<div class="dk-offer-stock"><?php echo ! $product->is_in_stock() ? 'ناموجود' : ( $stock !== null && $stock > 0 && $stock < 10 ? esc_html( sprintf( 'تنها %s عدد در انبار باقی مانده', lylyrose_to_persian_digits( $stock ) ) ) : 'موجود در انبار' ); ?></div>
				<div class="dk-offer-prices">
					<?php if ( $percent ) : ?><span class="dk-offer-percent"><?php echo esc_html( lylyrose_to_persian_digits( $percent ) ); ?>٪</span><?php endif; ?>
					<div><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
				</div>
				<?php if ( $end ) : ?><time class="dk-flash-timer" aria-label="زمان باقی‌مانده پیشنهاد">در حال محاسبه</time><?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endforeach; ?>
	<?php else : ?>
	<section class="dk-no-products"><h2>پیشنهادی با این فیلترها پیدا نشد</h2><p>دسته‌بندی دیگری انتخاب کنید یا فیلترها را پاک کنید.</p><a href="<?php echo esc_url( $base ); ?>">مشاهده همه پیشنهادها</a></section>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
