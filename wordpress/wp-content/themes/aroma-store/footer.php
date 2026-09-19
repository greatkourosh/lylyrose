</div><!-- #content -->

<footer class="dk-footer">
<div class="dk-container">
<div class="dk-footer-grid">
<div class="dk-footer-col">
<h4>درباره عطر استور</h4>
<p class="dk-footer-about">عطر استور، فروشگاه تخصصی عطر و ادکلن با ضمانت اصالت کالا. ارائه‌دهنده بهترین برندهای جهانی با قیمت مناسب و ارسال سریع به سراسر ایران.</p>
</div>
<div class="dk-footer-col">
<h4>دسترسی سریع</h4>
<ul>
<li><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>">فروشگاه</a></li>
<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">درباره ما</a></li>
<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">تماس با ما</a></li>
<li><a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">مجله عطر</a></li>
</ul>
</div>
<div class="dk-footer-col">
<h4>خدمات مشتریان</h4>
<ul>
<li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">پیگیری سفارش</a></li>
<li><a href="#">شرایط بازگشت</a></li>
<li><a href="#">راهنمای خرید</a></li>
<li><a href="#">سوالات متداول</a></li>
</ul>
</div>
<div class="dk-footer-col">
<h4>دسته‌بندی‌ها</h4>
<ul>
<li><a href="<?php echo esc_url( add_query_arg( 'product_cat', 'men', home_url( '/shop/' ) ) ); ?>">عطر مردانه</a></li>
<li><a href="<?php echo esc_url( add_query_arg( 'product_cat', 'women', home_url( '/shop/' ) ) ); ?>">عطر زنانه</a></li>
<li><a href="<?php echo esc_url( add_query_arg( 'product_cat', 'eau-de-parfum', home_url( '/shop/' ) ) ); ?>">ادو پرفیوم</a></li>
<li><a href="<?php echo esc_url( add_query_arg( 'product_cat', 'gift-sets', home_url( '/shop/' ) ) ); ?>">ست هدیه</a></li>
</ul>
</div>
</div>
</div>

<div class="dk-trust">
<div class="dk-container">
<div class="dk-trust-item">
<div class="dk-trust-icon">&#x1F69A;</div>
<span class="dk-trust-label">ارسال سریع</span>
<span class="dk-trust-desc">به سراسر کشور</span>
</div>
<div class="dk-trust-item">
<div class="dk-trust-icon">&#x2705;</div>
<span class="dk-trust-label">ضمانت اصالت</span>
<span class="dk-trust-desc">کالای ۱۰۰٪ اصل</span>
</div>
<div class="dk-trust-item">
<div class="dk-trust-icon">&#x21A9;</div>
<span class="dk-trust-label">۷ روز ضمانت بازگشت</span>
<span class="dk-trust-desc">در صورت عدم رضایت</span>
</div>
<div class="dk-trust-item">
<div class="dk-trust-icon">&#x1F4B3;</div>
<span class="dk-trust-label">پرداخت امن</span>
<span class="dk-trust-desc">درگاه معتبر بانکی</span>
</div>
</div>
</div>

<div class="dk-footer-bottom">
<div class="dk-container">
<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?> &copy; <?php echo date_i18n( 'Y' ); ?>. کلیه حقوق محفوظ است.</p>
</div>
</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>