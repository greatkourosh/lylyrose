<?php
/**
 * درباره ما — About page.
 *
 * @package Digikala
 */

get_header();
?>
<main class="dk-main">
	<div class="dk-container dk-page-narrow">
		<article class="dk-page-card">
			<h1 class="dk-page-title"><?php esc_html_e( 'درباره لیلی رز', 'lylyrose' ); ?></h1>

			<p class="dk-page-lead"><?php esc_html_e( 'لیلی رز فروشگاه اینترنتی عطر و ادکلن است؛ از عطرهای روزمره تا رایحه‌های خاص و لاکچری. ما مستقیم از تأمین‌کننده‌های معتبر تأمین می‌کنیم و هر سفارش را با ضمانت اصالت کالا به دست شما می‌رسانیم.', 'lylyrose' ); ?></p>

			<div class="dk-about-stats">
				<div class="dk-about-stat">
					<span class="num">+۱۰۰</span>
					<span class="lbl"><?php esc_html_e( 'تنوع عطر و ادکلن', 'lylyrose' ); ?></span>
				</div>
				<div class="dk-about-stat">
					<span class="num">٪۱۰۰</span>
					<span class="lbl"><?php esc_html_e( 'ضمانت اصالت کالا', 'lylyrose' ); ?></span>
				</div>
				<div class="dk-about-stat">
					<span class="num"><?php esc_html_e( '۷ روز', 'lylyrose' ); ?></span>
					<span class="lbl"><?php esc_html_e( 'ضمانت بازگشت کالا', 'lylyrose' ); ?></span>
				</div>
				<div class="dk-about-stat">
					<span class="num"><?php esc_html_e( 'ارسال سریع', 'lylyrose' ); ?></span>
					<span class="lbl"><?php esc_html_e( 'به سراسر ایران', 'lylyrose' ); ?></span>
				</div>
			</div>

			<h2><?php esc_html_e( 'داستان ما', 'lylyrose' ); ?></h2>
			<p><?php esc_html_e( 'لیلی رز با یک هدف ساده شروع شد: خرید عطر باید همان‌قدر مطمئن و لذت‌بخش باشد که استفاده از آن. در بازار پر از نمونه‌های تقلبی، ما تصمیم گرفتیم فقط کالای اورجینال بفروشیم، قیمت‌ها را شفاف اعلام کنیم و اگر کالا مطابق انتظار نبود، راه بازگشت ساده‌ای پیش روی مشتری بگذاریم.', 'lylyrose' ); ?></p>

			<h2><?php esc_html_e( 'چرا خرید از لیلی رز؟', 'lylyrose' ); ?></h2>
			<ul class="dk-page-list">
				<li><strong><?php esc_html_e( 'اصالت کالا:', 'lylyrose' ); ?></strong> <?php esc_html_e( 'تمام عطرها اورجینال هستند و از تأمین‌کننده‌های معتبر تهیه می‌شوند.', 'lylyrose' ); ?></li>
				<li><strong><?php esc_html_e( 'قیمت شفاف:', 'lylyrose' ); ?></strong> <?php esc_html_e( 'قیمت‌ها به‌روز است و تخفیف‌های واقعی، بدون قیمت‌های تزئینی.', 'lylyrose' ); ?></li>
				<li><strong><?php esc_html_e( 'ارسال سریع:', 'lylyrose' ); ?></strong> <?php esc_html_e( 'سفارش‌ها در سریع‌ترین زمان ممکن با بسته‌بندی امن ارسال می‌شوند.', 'lylyrose' ); ?></li>
				<li><strong><?php esc_html_e( 'پشتیبانی پاسخ‌گو:', 'lylyrose' ); ?></strong> <?php esc_html_e( 'تیم پشتیبانی از طریق گفتینو، تلفن و ایمیل پاسخ‌گوی شماست.', 'lylyrose' ); ?></li>
			</ul>

			<h2><?php esc_html_e( 'اعتماد شما، سرمایه ما', 'lylyrose' ); ?></h2>
			<p><?php esc_html_e( 'هر هفته مشتریان جدیدی به لیلی رز اضافه می‌شوند و نظرات ثبت‌شده روی صفحات کالا به خریداران بعدی کمک می‌کند انتخاب درستی داشته باشند. اگر تجربه خریدتان را دوست داشتید، ثبت یک نظر کوچک بهترین هدیه به ماست.', 'lylyrose' ); ?></p>
		</article>
	</div>
</main>
<?php
get_footer();
