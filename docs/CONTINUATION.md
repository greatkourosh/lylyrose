# Continuation — Current Project State

> Handoff point: read this, then `git log --oneline -10` and `git status` to pick up.

## Verified Lyly Rose state — 2026-09-17

- **Production target**: `https://lylyrose.ir`, addon domain on the vegacodex.ir hosting account, document-root folder `lylyroseir`. Domain registration was pending approval when requested; DNS, addon-domain configuration, TLS, and deployment have not yet been verified. No Lyly Rose subdomain has been provisioned or verified.
- **Repository**: GitHub repository creation was reported earlier, but local Git initialization, token access verification, commit, and push remain outstanding.
- **Local**: `http://localhost:8080`, phpMyAdmin on port 8081; active theme `lylyrose`, plugin `lylyrose-core`. Legacy themes remain unchanged. Database branding still needs verification independently of source-code branding.
- **Core compatibility**: WooCommerce 11.1.0 requires WordPress 7.0 or later. The original Docker image initializes WordPress 6.5.5, which caused a missing `WP_Block_Templates_Registry` fatal error. The existing local volume was upgraded to WordPress 7.1 using core files from the source project's container, excluding its configuration and wp-content; the database upgrade completed. A fresh-volume reproducible setup still needs correction.
- **Verified fixes**: gift-wrap rendering uses `wp_kses_post(wc_price(...))` instead of the removed theme helper. A real cookie-based add-to-cart request renders the coupon form and nonce. Shop returns 200. Generated JPEG and PNG uploads and their six sizes are WebP.
- **Testing**: a complete, untruncated `bash docker/run-tests.sh` run is in progress, with output at `/tmp/lylyrose-full-tests.log`. Earlier runs piped to `head` were incomplete and are not full-suite results. Actual SMS gateway resolves to `PW\PWSMS\Gateways\Logger`; ZarinPal is enabled in sandbox mode.
- **Safety**: the suite deletes local orders and wallet data; never run against production or Aroma Store. Pre-test database and pre-upgrade core backups are in `/tmp/lylyrose-before-tests.sql` and `/tmp/lylyrose-core-before-upgrade.tar.gz`, excluded from version control. WP-CLI is not currently installed at `/tmp/wp-cli.phar`.
- **Remaining**: resolve full-suite failures, browser verification, reproducible setup, production preparation, documentation cleanup, Obsidian update, GitHub access verification, commit, and push.

## Inherited Aroma Store history

The entries below were copied and mechanically rebranded from the source project. Their dates, deployment claims, credentials, fixture IDs, and test counts describe the source project's history, not verified Lyly Rose results. Do not execute legacy deployment workarounds or treat these entries as proof of production readiness.

## Dokan 5.1.1 upgrade + plugin ownership fix (2026-09-12)

- **Problem**: Plugin update «بهروزرسانی افزونه ناموفق بود» on local. Root cause: 21 plugins
  (including `dokan-lite`) were extracted by `docker/install-plugin.sh` running as **root**,
  leaving plugin dirs owned `root:root`. WordPress runs as `www-data` (uid 33), so the
  update's "delete old version" step failed.
- **Fix**: `chown -R 33:33` on all plugin dirs in `wp-content/plugins/`. Verified all 26
  plugins now `www-data:www-data`. Future updates unblocked.
- **Upgrade**: Manually fetched `dokan-lite.5.1.1.zip` (14 MB), extracted, activated.
  WP reports **5.1.1 active**, site health green.
- **Live**: not yet updated — production uses cPanel shared host (plugin dirs owned by
  cPanel user, so ownership issue doesn't apply). Update via WP admin at `/secure-login`
  or FTP chunk+assembler deploy of the new `dokan-lite/` folder.

## Review incentive gotchas (P1 #8)

## Review incentive gotchas (P1 #8)

- `WC_Coupon::set_expiry_date()` does NOT exist in WC 11 — use `set_date_expires( int $timestamp )`.
- woo-wallet registers `comment_post` with **3 args**; firing `do_action("comment_post", $id, 1)` with only 2 in tests causes `ArgumentCountError` (WP core always passes `$commentdata` array as 3rd). Use `do_action("comment_post", $c, 1, get_comment($c, ARRAY_A))`.
- Coupon handler needs the order **actually completed** (`$order->update_status("completed")`) — a bare `do_action("woocommerce_order_status_completed", …)` on a pending order passes the hook but the coupon lookup (`wc_get_orders(status=>completed)`) finds nothing.
- **Local perf quirk**: uncached local pages can take ~18s — Docker→Windows bind-mount
  `stat()` latency (full WP bootstrap ~17s; Super Cache pages unaffected). Live host is
  fine. The suite now uses `--max-time 120 --retry 2` on all curls and section 18
  restores fixture stock before add-to-cart, so runs are deterministic; if something
  still flakes, re-run. Never use `grep -q` on large page HTML in this script (SIGPIPE
  exit 141 under `pipefail`) — use the `html_has` helper.
- **Loyalty wallet**: woo-wallet plugin (fa_IR pack), 2% cashback on completed orders
  (cap 5M, min cart 500k), review credit 50k, topup product 100k–20M Toman; theme adds
  «کیف پول» to the my-account sidebar (endpoint `my-wallet`).
- **OTP login (P0 #1)**: lylyrose-core `ASC_OTP` + theme v1.6.0 two-tab login —
  ورود سریع (mobile + SMS code, auto-registers customers) / ورود با گذرواژه. SMS via
  PWSMS (Logger sink `wc-logs/pwsms.log` until real gateway credentials are set on the
  host). Rate limits: 5/hour per mobile, 60s resend, 5 verify attempts, 120s code TTL.
- **ZarinPal payment (P0 #5)**: gateway `WC_ZPal` configured locally (sandbox yes,
  dummy UUID merchant — sandbox accepts any), section 21 proves the full chain
  checkout → order-pay → receipt → StartPay → callback → processing/paid. On live the
  plugin is present but **disabled** — production requires a real merchant code +
  `sandbox: no` in WP admin (درگاه‌ها → زرین‌پال) before launch.
- **Cart abandonment (P0 #4)**: woo-cart-abandonment-recovery v2.1.3 captures email +
  phone at checkout into `wp_cartflows_ca_cart_abandonment`; cron flips stale carts
  (30-min cut-off) to `abandoned`; lylyrose-core `ASC_Cart_Abandonment` sends the
  Persian SMS reminder with tokenized recovery link via PWSMS at that moment; 3
  Persian follow-up email templates (1h/6h/1d) activated; unsubscribed carts skipped.

## Completed milestones
- Review incentive P1 #8 (2026-09-04): lylyrose-core v2.1.0 `ASC_Reviews` —
  on order `completed` schedules Persian review-request email + SMS 3–7 days out
  (Action Scheduler `asc_send_review_request`, once per order via
  `_asc_review_requested`); an approved review by a verified purchaser (email
  matched to a completed order containing that product) earns a single-use 10%
  coupon `REVIEW-XXXXXXXX` (30-day expiry), once per order+product via
  `_asc_review_coupon_{product_id}`; non-purchaser reviews earn nothing. Tests
  section 22 (7 checks). Gotchas: `set_date_expires()` not `set_expiry_date()`;
  `comment_post` needs 3 args (woo-wallet); order must be truly completed for the
  coupon lookup.
- ZarinPal sandbox e2e payment P0 #5 (2026-09-03): gateway configured
  (sandbox yes, dummy UUID merchant accepted by sandbox, IRT ×10 Rial);
  full headless HTTP flow proven — checkout (PWS numeric state 312/city 322 +
  national-ID 0012345679) → order-pay → pay POST → receipt → 302 StartPay →
  payment simulated via verify-token GET on sandbox → callback `/wc-api/WC_ZPal`
  → order processing/paid with transaction id + stock decrement; NOK leaves
  pending. Tests section 21 (10 checks). Live: plugin present but disabled —
  needs real merchant code + `sandbox: no` in WP admin before production.
- Cart abandonment recovery P0 #4 (2026-09-03): woo-cart-abandonment-recovery
  v2.1.3 + `ASC_Cart_Abandonment` SMS glue (hooks `wcf_ca_process_abandoned_order`,
  normalizes phone via `ASC_OTP::normalize_mobile`, sends recovery link + coupon);
  Persian templates replace the English samples; from-name لیلی رز; GDPR notice in
  Persian. Tests section 20 (11 checks). Live needs real SMS gateway credentials.
- Mobile OTP login & registration P0 #1 (2026-09-02): in-house `ASC_OTP` (AJAX
  `asc_otp_request`/`asc_otp_verify`, hashed 6-digit code in a 120s transient,
  60s resend cooldown, 5 attempts, 5 req/hour, auto-register customer with
  `billing_phone`); theme v1.6.0 two-tab login (ورود سریع default) + otp-login.js;
  registration option now `yes`. Tests section 19. Live needs real SMS gateway
  credentials in پیامک settings before production OTP works.
- Loyalty wallet P2 #11 (2026-09-02): woo-wallet plugin v1.5.1 + fa_IR language pack,
  configured via three `_wallet_settings_*` option groups — 2% cashback on completed
  orders (capped 5M, min cart 500k, refund clawback), review credit 50k (first review
  per product per user), topup product «شارژ کیف پول لیلی رز» (100k–20M, partial
  payment with auto-deduct, wallet gateway hidden on topup-only carts); theme v1.5.1
  my-account sidebar gains «کیف پول» gated on `class_exists('Woo_Wallet_Frontend')`;
  endpoint is `my-wallet` (the plugin's menu slug `woo-wallet` 404s on this theme).
  Live deploy done (rewrite flush required after scripted activation); tests section 18.
- Sales reports P2 #15 (2026-09-02): lylyrose-core v2.0.0 `ASC_Reports` — «گزارش فروش» admin page (date-range stats, top products) + CSV export with Persian headers and UTF-8 BOM; signed day-scoped export token instead of nonce (sessions rotate per request); tests section 17.
- Instagram strip P2 #14 (2026-09-02): lylyrose-core v1.9.0 `ASC_Instagram` — `asc_instagram` option (handle + 6 image/post URLs) edited via Persian Settings page; theme v1.5.0 lazy-loaded grid on homepage; no IG API by design; silent until configured; tests section 16.
- Frequently bought together P2 #12 (2026-09-02): lylyrose-core v1.8.0 `ASC_Frequently_Bought` co-purchase query over last 500 completed orders (transient-cached 1 day, invalidated inline on order completion); theme v1.4.0 renders «اکثراً با هم خریداری شده‌اند» on single product with buy-both button + partner cards; silent when no order data (live shows nothing until real orders land); tests section 15.
- Coupon surfaces P1 #10 (2026-09-01): theme v1.3.0 cart shows always-visible Digikala-style coupon field in payment summary (posts to WC core handler with woocommerce-cart nonce); lylyrose-core v1.7.0 `ASC_Coupons` renders `asc_campaign_banner` option as red strip on every page; WELCOME10 (10% individual) seeded; tests section 14.
- WebP image delivery P0 #2 (2026-09-01): library already 100% WebP (Digikala import) so no converter plugin; lylyrose-core v1.6.0 `ASC_Images` converts future JPEG/PNG uploads to WebP via WP core (`image_editor_output_format`), 2560px cap; tests section 13 via docker/image-check.php sideloads a JPEG+PNG and asserts WebP.
- Persian transactional emails P1 #9 (2026-09-01): lylyrose-core v1.5.0 `ASC_Emails` — gettext_woocommerce map (~40 English email_improvements-era default strings → Persian; fa_IR pack misses them), woocommerce_email_styles filter injects `direction:rtl`, email options branded (base #ef394e, Tahoma, header right, from-name لیلی رز, Persian footer); tests section 12 renders a real email via docker/email-check.php.
- Store pages P1 #6 (2026-09-01): about/contact/track-order/faq self-created by lylyrose-core v1.4.0 (ASC_Store_Pages), Persian templates in theme v1.2.0, footer links live; order-lookup + contact-form handlers with rate limit; tests section 11.

- Full Digikala-style UI: home, shop/category (URL-driven filters), single product
  (sku-code URLs via `ASC_Product_Code`), cart, checkout (national-ID field),
  my-account, wishlist, demo-mode banner on every page.
- Rebrand: all user-visible دیجی‌کالا labels → لیلی رز (templates + 100 product
  descriptions in DB, local and live).
- Deployment to cPanel host complete; demo-mode banner, home-page real data,
  `sort=discount` all live.

## Known open items

- **Live Dokan update pending**: live site still on Dokan 5.0.16. Upgrade to 5.1.1 via
  WP admin (`/secure-login`) or FTP chunk+assembler deploy. Verify live site health
  (home 200, shop 200) after.
- See [FEATURES_ROADMAP.md](FEATURES_ROADMAP.md) for prioritized next features.

## 2026-09-14 — Dokan add-to-cart fix + suite re-green (in progress)

- **Dokan own-product bug**: imported products have `post_author = 0`; Dokan's
  `dokan_vendor_own_product_purchase_restriction` matches author 0 to the guest (0)
  and force-marks every product non-purchasable → add-to-cart broken for everyone.
  Fixed by filtering `dokan_is_product_author` (return -1 for author-0) in
  lylyrose-core (single copy; the duplicated theme copy was removed).
- **Test fixtures made dynamic**: run-tests.sh now resolves FP_MAIN (price 9,940,000 =
  old fixture 137 / SKU DIGIKALA-20599667; 2% cashback = 198,800), FP_OOS, FP_ALT,
  FP_NEG at runtime instead of hardcoding 137/138/139/17/11/242. FP_MAIN gets
  manage_stock=true so the stock assertions work.
- **State/city validation**: rebuilt DB lost PWS numeric state/city terms — checkout
  POSTs now use `billing_state=THR` + `billing_city=تهران` (free text).
- **Post-rebuild config wave 2 restored**: WC email branding (base #ef394e / Tahoma /
  from-name لیلی رز), cartflows 3 Persian abandonment templates + admin/GDPR settings,
  woo-wallet `is_auto_deduct_for_partial_payment=on` (partial fee -248800 verified).
- **Redis ext**: pecl.php.net reachable again — redis 6.3.0 installed in container,
  `RUN pecl install redis` added to docker/Dockerfile; object cache truly connects now.
- **Theme fix**: `lylyrose_stock_notifier_script` used `global $product` which is empty
  at `wp_enqueue_scripts` (loop runs after get_header) — now resolves via
  `get_queried_object()`. stock-notifier.js enqueues on OOS pages again.
- **Theme/plugin version**: lylyrose-core header synced to 2.3.0 (const) — WP now
  reports the real version.
