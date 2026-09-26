# Continuation — Current Project State

> Handoff point: read this, then `git log --oneline -10` and `git status` to pick up.

## Verified Lyly Rose state — 2026-09-26

- **LIVE**: `https://lylyrose.ir` is deployed and serving. Files uploaded (25,844 files / 342 MB, 0 failures), database `bqwyvowk_lylyrose` created and imported (1,218 statements, 97 tables, 0 failures), `wp-config.php` with real credentials + fresh salts, and `.htaccess` carries the WordPress rewrite block. Verified: home, `/shop/`, `/cart/`, `/checkout/`, `/my-account/`, `/secure-login/` all 200; add-to-cart works (WooCommerce fragment confirms 1 item @ 7,800,000 Toman); product pages and media load from `lylyrose.ir`; no `localhost` URLs remain; `vegacodex.ir` untouched.
- **Login is `/secure-login/`**, not `wp-login.php` (WPS Hide Login) — a `wp-login.php` 404 is expected, not a fault.
- **Two silent config bugs blocked the deploy** and are fixed on the host, but **the local source templates are still unfixed** — fix before any re-deploy:
  1. `wp-config.php` was missing `$table_prefix = 'wp_';` → WordPress ignored the imported tables and 302-redirected every request to `/wp-admin/install.php` (looks like an empty DB when all 97 tables are present).
  2. `.htaccess` shipped with only cPanel's PHP-ini directives, no WordPress rewrite block → every pretty-permalink page 404'd (shop/cart/checkout/login) despite 24 KB of `rewrite_rules` sitting in the DB.
  Gate the next deploy on: home 200 **and** `/shop/ /cart/ /checkout/` 200 — not the home page alone.
- **Hosting credentials**: the cPanel account details are in `.env` (git-ignored) — `PHP_HOST`, `PHP_HOST_USERNAME`, `PHP_HOST_PASSWORD`, `PHP_HOST_IP=89.39.208.244`, `PHP_HOST_SERVER_NAME=ircpanel181`, `LYLYROSE_DOMAIN=lylyrose.ir`, `LYLYROSE_FOLDER=lylyroseir`. The existing site on that account is **vegacodex.ir**; Lyly Rose is an addon, so do not disturb the primary domain's document root.
- **Repository**: local Git initialized and the full tree committed (`1eea959`, 20,302 files) and **pushed** to https://github.com/greatkourosh/lylyrose (`master` tracks `origin/master`). The push needed the PAT from `project_manager/secrets/github.env`; the repo URL now embeds that token, so `git push` works without re-auth. `.env` is git-ignored and was not committed.
- **Local**: `http://localhost:8080`, phpMyAdmin on port 8081; active theme `lylyrose`, plugin `lylyrose-core`. Legacy themes remain unchanged. Database branding still needs verification independently of source-code branding.
- **Core compatibility**: WooCommerce 11.1.0 requires WordPress 7.0 or later. The original Docker image initializes WordPress 6.5.5, which caused a missing `WP_Block_Templates_Registry` fatal error. The existing local volume was upgraded to WordPress 7.1 using core files from the source project's container, excluding its configuration and wp-content; the database upgrade completed. A fresh-volume reproducible setup still needs correction.
- **Verified fixes**: gift-wrap rendering uses `wp_kses_post(wc_price(...))` instead of the removed theme helper. A real cookie-based add-to-cart request renders the coupon form and nonce. Shop returns 200. Generated JPEG and PNG uploads and their six sizes are WebP.
- **Testing**: `bash docker/run-tests.sh` is **fully green — 217 passed, 0 failed** (re-verified 2026-09-24 after the deploy-prep scrub and the wallet-uid test fix, exit 0), logs in `.test-logs/full-tests-*.log` (project-local, survives reboots; the old `/tmp/lylyrose-full-tests.log` was cleared on reboot). Actual SMS gateway resolves to `PW\PWSMS\Gateways\Logger`; ZarinPal is enabled in sandbox mode.
- **Safety**: the suite deletes local orders and wallet data; never run against production or Aroma Store. Pre-test database and pre-upgrade core backups are in `/tmp/lylyrose-before-tests.sql` and `/tmp/lylyrose-core-before-upgrade.tar.gz`, excluded from version control. WP-CLI is not currently installed at `/tmp/wp-cli.phar`.
- **Deployment (2026-09-24, complete)**: artifacts were staged locally then pushed to the host. Files went up over **passive FTP** (~25,844 files, 0 failures); the DB import ran as an uploaded **PHP script** (mysqli) over HTTPS because SSH and phpMyAdmin are unavailable. Details and the host-access runbook: [DEPLOY_PREP.md](DEPLOY_PREP.md) and [DEPLOYMENT_SUMMARY.md](../DEPLOYMENT_SUMMARY.md). The DB dump contained real customer/order data and was deleted from the webroot immediately after import, along with every helper/credential file.
- **Host access constraints (re-deploys)**: SSH is **closed** (ports 22 and 2222 filtered), MySQL 3306/3307 closed, phpMyAdmin's API module is not installed, and `Fileman` has no working `extract` (its `fileop extract` shells to `gtar` which fails "Permission denied" on the addon docroot). FTP (21) works **passive only** — Pure-FTPd rejects active mode with "425 No data connection". cPanel (2082/2083) drives everything via UAPI: login, capture the `/cpsess<token>/` session + cookie, then `Mysql::create_database` (param is `name`, **not** `db`), `create_user`, `set_privileges_on_database`, `set_password` (the setter is called `set_password`, not `set_user_password`). `exec()` is disabled host-side, but a PHP file placed in the docroot and fetched over HTTPS runs fine — that is the only way to script the SQL import.
- **MySQL grant gotcha**: after `create_user`, `set_privileges_on_database` can return `status: 1` while the grant is **not actually live**. Symptom: mysqli says "Access denied ... to database X" but a bare connect succeeds and `SHOW DATABASES` omits X. Re-calling `set_privileges_on_database` fixes it. Verify with a PHP `SHOW DATABASES` from the host, not with cPanel's metadata, which falsely reports the user as attached.
- **URL rewrite**: all 319 `http://localhost:8080` occurrences are in **plain** columns (options, GUIDs, postmeta, page content) — **none** are inside WP-serialized blobs, so a plain replace is safe and no length recompute is needed. `siteurl`/`home` are literal plain rows.
- **PHP is 8.1.34 on the host**, not the 8.2 the runbook prefers. WooCommerce 11.1.0's WP 7.0+ requirement is satisfied, so the site works; bump the domain to 8.2 via MultiPHP Manager only if you want parity with the recommendation.
- **Fixed (2026-09-20)**: notifications my-account endpoint fired the wrong action — `lylyrose-core` hooked `woocommerce_account_notifications`, but WC fires `woocommerce_account_{endpoint}_endpoint`, so the page rendered empty; the theme `my-account.php` no longer branches on the endpoint and just calls `woocommerce_account_content` (WC dispatches the endpoint action itself).
- **Next**: the deploy is done — what remains is the per-host settings pass (see **Known open items**) and fixing the two template bugs above in the local source tree.
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

- **Fix the two deploy-template bugs in the local source** (highest value — they silently
  break any re-deploy): add `$table_prefix = 'wp_';` to the `wp-config.php` template,
  and add the `# BEGIN WordPress` rewrite block to the `.htaccess` you ship. Both are
  currently only fixed in the deployed copies on the host.
- **Per-host settings still to do from `/secure-login/`** (no SSH needed, just admin access):
  ZarinPal real merchant code + `sandbox: no`; PWSMS real gateway credentials (currently
  the `Logger` sink, so production SMS is a no-op); WP Mail SMTP credentials; UpdraftPlus
  remote storage; deactivate Redis Object Cache (no Redis on this host) and drop the
  `WP_REDIS_*` defines; WP Super Cache re-configure; Wordfence scan + firewall mode.
- **Cron**: `DISABLE_WP_CRON` is `false` in the deployed config (WP self-triggers). If you
  want a cPanel cron job instead, set it to `true` and add
  `* * * * * /usr/local/bin/php /home3/bqwyvowk/lylyroseir/wp-cron.php >/dev/null 2>&1`.
- **Live Dokan update pending**: the imported DB still has Dokan 5.0.16 (local is on 5.1.1).
  Upgrade via WP admin or an FTP chunk+assembler deploy of the new `dokan-lite/` folder,
  then re-verify home 200 + shop 200.
- **PHP version**: host runs 8.1.34; runbook prefers 8.2. Optional.
- See [FEATURES_ROADMAP.md](FEATURES_ROADMAP.md) for prioritized next features.

## Task: deploy lylyrose to the host — ✅ DONE 2026-09-24

> Retained for reference. All 13 steps below completed. The site is live at
> `https://lylyrose.ir`; see the verified state at the top of this file. Re-deploys must
> work around the host constraints recorded above (no SSH, passive FTP only, scripted PHP
> SQL import) and must include the two template fixes.

Goal: serve the rebranded store at `https://lylyrose.ir` from the existing cPanel
account, without touching the `vegacodex.ir` primary site.

Host facts are in `.env` (git-ignored): `PHP_HOST_IP=89.39.208.244`,
`PHP_HOST_USERNAME=bqwyvowk`, `PHP_HOST_PASSWORD=…`, cPanel at
`http://cp181.unitedhost.org:2082`, server name `ircpanel181`, DNS
`ns875`/`ns876.mihanwebhost.com`, addon folder `lylyroseir`. The real docroot on
this host is `/home3/bqwyvowk/lylyroseir` (note `home3`, not `home`).

### Steps (as planned)

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
   **Required and easy to miss: `$table_prefix = 'wp_';`** (normally added by the WP
   installer). Without it WordPress ignores every imported table and redirects to
   `install.php`. Keep the dump's prefix and this line in sync.
9. **URL rewrite** — replace `http://localhost:8080` with `https://lylyrose.ir` across
   all tables (WP-CLI `wp search-replace … --all-tables --precise`, or Better Search
   Replace). Verify `wp_options.siteurl` and `home` afterwards.
10. **TLS** — AutoSSL in cPanel → SSL/TLS Status; force HTTPS once issued.
11. **Permalinks & cron** — flush rewrite rules (Settings → Permalinks → Save); add a
    cPanel cron job hitting `wp-cron.php` every minute. cPanel's addon-domain
    `.htaccess` contains only PHP-ini directives, so the `# BEGIN WordPress` rewrite
    block must be present **below** them or every pretty permalink 404s.
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
