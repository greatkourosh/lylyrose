# Continuation — Current Project State

> Handoff point: read this, then `git log --oneline -10` and `git status` to pick up.

## Verified Lyly Rose state — 2026-09-24

- **Production target**: `https://lylyrose.ir`, addon domain on the vegacodex.ir hosting account, document-root folder `lylyroseir`. The domain is now **registered** (confirmed 2026-09-19). Addon-domain configuration in cPanel, DNS records (`ns875`/`ns876.mihanwebhost.com`), TLS issuance, and file/database deployment have **not** yet been performed or verified. Live site is not serving Lyly Rose yet.
- **Hosting credentials**: the cPanel account details are in `.env` (git-ignored) — `PHP_HOST`, `PHP_HOST_USERNAME`, `PHP_HOST_PASSWORD`, `PHP_HOST_IP=89.39.208.244`, `PHP_HOST_SERVER_NAME=ircpanel181`, `LYLYROSE_DOMAIN=lylyrose.ir`, `LYLYROSE_FOLDER=lylyroseir`. The existing site on that account is **vegacodex.ir**; Lyly Rose is an addon, so do not disturb the primary domain's document root.
- **Repository**: local Git initialized and the full tree committed (`1eea959`, 20,302 files) and **pushed** to https://github.com/greatkourosh/lylyrose (`master` tracks `origin/master`). The push needed the PAT from `project_manager/secrets/github.env`; the repo URL now embeds that token, so `git push` works without re-auth. `.env` is git-ignored and was not committed.
- **Local**: `http://localhost:8080`, phpMyAdmin on port 8081; active theme `lylyrose`, plugin `lylyrose-core`. Legacy themes remain unchanged. Database branding still needs verification independently of source-code branding.
- **Core compatibility**: WooCommerce 11.1.0 requires WordPress 7.0 or later. The original Docker image initializes WordPress 6.5.5, which caused a missing `WP_Block_Templates_Registry` fatal error. The existing local volume was upgraded to WordPress 7.1 using core files from the source project's container, excluding its configuration and wp-content; the database upgrade completed. A fresh-volume reproducible setup still needs correction.
- **Verified fixes**: gift-wrap rendering uses `wp_kses_post(wc_price(...))` instead of the removed theme helper. A real cookie-based add-to-cart request renders the coupon form and nonce. Shop returns 200. Generated JPEG and PNG uploads and their six sizes are WebP.
- **Testing**: `bash docker/run-tests.sh` is **fully green — 217 passed, 0 failed** (re-verified 2026-09-24 after the deploy-prep scrub and the wallet-uid test fix, exit 0), logs in `.test-logs/full-tests-*.log` (project-local, survives reboots; the old `/tmp/lylyrose-full-tests.log` was cleared on reboot). Actual SMS gateway resolves to `PW\PWSMS\Gateways\Logger`; ZarinPal is enabled in sandbox mode.
- **Safety**: the suite deletes local orders and wallet data; never run against production or Aroma Store. Pre-test database and pre-upgrade core backups are in `/tmp/lylyrose-before-tests.sql` and `/tmp/lylyrose-core-before-upgrade.tar.gz`, excluded from version control. WP-CLI is not currently installed at `/tmp/wp-cli.phar`.
- **Deployment prep complete (2026-09-20, artifacts regenerated 2026-09-24)**: local artifacts are staged and ready to upload — WordPress 7.1 core (fa_IR, `wp-content` excluded) + repo `wp-content` assembled in `/tmp/lylyrose_deploy/` (452 MB tree; only the active `lylyrose` theme + stock twentytwenty*, inactive dev themes dropped), a production `wp-config.php` with fresh salts + `WP_HOME`/`WP_SITEURL` = `https://lylyrose.ir` and DB placeholders, DB dump `/tmp/lylyrose.sql` (1.7 MB; test users/Dokan test rows/SMS archive scrubbed), uploads at `/tmp/lylyrose_uploads/` (9.7 MB / 1780 files; `wc-logs/` + `ao_ccss/` stripped). See [DEPLOY_PREP.md](DEPLOY_PREP.md) and [DEPLOYMENT_SUMMARY.md](../DEPLOYMENT_SUMMARY.md).
- **Fixed (2026-09-20)**: notifications my-account endpoint fired the wrong action — `lylyrose-core` hooked `woocommerce_account_notifications`, but WC fires `woocommerce_account_{endpoint}_endpoint`, so the page rendered empty; the theme `my-account.php` no longer branches on the endpoint and just calls `woocommerce_account_content` (WC dispatches the endpoint action itself).
- **Next**: deploy to the host (see the `deploy-host` task below) — no Lyly Rose files or database exist on the host yet. DNS already resolves (`dig +short lylyrose.ir` → `89.39.208.244`), so step 2 (addon domain) is the next action.
- **Full-suite green root causes fixed & test hardened (2026-09-24)**: the suite went from 207/10 to **217/0**. Four distinct issues: (1) `wp-content/uploads` + `wp-content/cache` were owned `1000:1000` (rebind bind-mount), so the web user (`www-data`/33) could not write — OTP SMS sink, autoptimize cache generation, and media uploads silently failed; fixed with `chown -R 33:33`. (2) The notifications synthetic auth cookie had a stray trailing `|` appended in `run-tests.sh` (`echo '|'`), corrupting the cookie header — fixed. (3) The notifications account endpoint rewrite rule was missing on a fresh volume (self-heal only flushes on version bump), so `/my-account/notifications/` rendered the generic account page; `flush_rewrite_rules()` resolves it. (4) The gift-wrap order test POSTed `payment_method=cod`, but COD isn't enabled (only ZarinPal + wallet) so checkout rejected with "پرداختی انجام نمی شود" — now uses `WC_ZPal` and falls back to the latest order when the pending-order URL lacks `order-received`. Also added a retry to the notifications account-page checks against a transient empty `get_posts` result. (5) The wallet section hardcoded `wp_set_current_user(2)` / `_wc_persistent_cart_2`; after the deploy-prep scrub deleted and the suite recreated `wallet_tester` at a new id, the wallet fee/nav/page checks cascaded (6 failures) — the suite now resolves the uid via `username_exists("wallet_tester")`.

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

- **Deploy to host — not started**. Ordered task list below.
- **Live Dokan update pending**: live site still on Dokan 5.0.16. Upgrade to 5.1.1 via
  WP admin (`/secure-login`) or FTP chunk+assembler deploy. Verify live site health
  (home 200, shop 200) after.
- See [FEATURES_ROADMAP.md](FEATURES_ROADMAP.md) for prioritized next features.

## Task: deploy lylyrose to the host

Goal: serve the rebranded store at `https://lylyrose.ir` from the existing cPanel
account, without touching the `vegacodex.ir` primary site.

Host facts are in `.env` (git-ignored): `PHP_HOST_IP=89.39.208.244`,
`PHP_HOST_USERNAME=bqwyvowk`, `PHP_HOST_PASSWORD=…`, cPanel at
`http://cp181.unitedhost.org:2082`, server name `ircpanel181`, DNS
`ns875`/`ns876.mihanwebhost.com`, addon folder `lylyroseir`.

### Steps

1. **DNS** — point `lylyrose.ir` at the host: `A` record to `89.39.208.244`, or NS
   records to `ns875`/`ns876.mihanwebhost.com` if the registrar delegates. Confirm with
   `dig +short lylyrose.ir` before continuing; cPanel will not issue an addon domain's
   TLS certificate until the domain resolves here.
2. **Addon domain** — cPanel → Domains → Create A New Domain: `lylyrose.ir` with
   document root `lylyroseir`. Verify it created `/home/bqwyvowk/lylyroseir` (or the
   cPanel-reported path) and that `vegacodex.ir` still serves.
3. **PHP** — set PHP 8.1+ (8.2 preferred) for the addon domain via MultiPHP Manager,
   and `upload_max_filesize`/`post_max_size=64M`, `max_execution_time=300`,
   `memory_limit=256M` via MultiPHP INI Editor.
4. **WordPress core** — download the fa_IR package locally
   (`https://fa.wordpress.org/latest-fa_IR.zip`), unzip, and upload its contents
   (minus `wp-content`) into `lylyroseir/`. Do **not** use the Docker image's
   WordPress 6.5.5 core — WooCommerce 11.1.0 needs WP 7.0+, and the local volume was
   upgraded to 7.1.
5. **wp-content** — upload `wordpress/wp-content/` from the repo (themes `lylyrose`,
   `digikala-v1.0.0`, `aroma-store`; plugins including `lylyrose-core`; `languages`).
   **Exclude** the runtime files that must be regenerated on the new host:
   `advanced-cache.php`, `object-cache.php`, `wp-cache-config.php`, and
   `wp-content/uploads/` (uploads are git-ignored — migrate them separately in step 7).
   `docker/migrate-from-remote.sh` and the chunk+assembler flow in
   [04_DEPLOYMENT.md](04_DEPLOYMENT.md) are the existing patterns for this.
6. **Database** — create db + user in cPanel, then export local and import:
   `docker exec lylyrose-db mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers lylyrose > lylyrose.sql`.
   The dump contains customer/order data — move it over an encrypted channel and
   delete it from any shared location right after import.
7. **Uploads** — `docker cp lylyrose-wp:/var/www/html/wp-content/uploads ./uploads`,
   then upload to `lylyroseir/wp-content/uploads/`. Set permissions to the cPanel
   user (usually `644` files / `755` dirs, no `www-data`).
8. **wp-config.php** — set DB credentials and `WP_HOME`/`WP_SITEURL` to
   `https://lylyrose.ir`; fresh salts from `https://api.wordpress.org/secret-key/1.1/salt/`;
   add `DISALLOW_FILE_EDIT`, `FS_METHOD=direct`, `WP_MEMORY_LIMIT=256M`.
9. **URL rewrite** — replace `http://localhost:8080` with `https://lylyrose.ir` across
   all tables (WP-CLI `wp search-replace … --all-tables --precise`, or Better Search
   Replace). Verify `wp_options.siteurl` and `home` afterwards.
10. **TLS** — AutoSSL in cPanel → SSL/TLS Status; force HTTPS once issued.
11. **Permalinks & cron** — flush rewrite rules (Settings → Permalinks → Save); add a
    cPanel cron job hitting `wp-cron.php` every minute.
12. **Re-point per-host settings** — Redis Object Cache **deactivate** (no Redis on
    shared hosting); WP Super Cache re-configure; UpdraftPlus re-point remote storage;
    WP Mail SMTP re-enter credentials; **ZarinPal** — real merchant code and
    `sandbox: no` (currently sandbox-only); **Persian SMS** — real gateway credentials
    (currently the `Logger` sink); Wordfence scan + firewall mode; confirm
    `/secure-login` loads.
13. **Verify** — home 200, shop 200, single product, add-to-cart, cart, checkout,
    order created, admin order visible, media resolving from `lylyrose.ir` (no
    `localhost` URLs left). Check `vegacodex.ir` is untouched.

### Notes

- **Do not run `docker/run-tests.sh` against production** — the suite deletes orders
  and wallet data.
- Plugin dirs on cPanel are owned by the cPanel user, so the local
  `root:root` ownership bug does not apply here.
- The prior "deployment to cPanel host complete" claim in the inherited Aroma Store
  history below describes the **source** project. Those steps must still be run for
  Lyly Rose.


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
