# DEVELOPMENT LOG - Phase 1 Complete

> **Upstream:** this project is **downstream** of `aroma_store`, which is the source of
> truth. Develop and test features upstream first; mirror here only when needed.
> See [UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md).


## Project: LYLY ROSE - Persian RTL E-commerce Perfume Store
**Date:** 2026-08-20
**Status:** Phase 1 Complete - Core Store Operational (Digikala-style UI)

---

## ✅ Completed Implementation

### Phase 0: Foundation
- [x] Docker Compose environment (WordPress + MariaDB + phpMyAdmin)
- [x] WordPress core installed
- [x] WooCommerce plugin activated
- [x] Custom `aroma-store` theme activated
- [x] `lylyrose-core` plugin with perfume taxonomies
- [x] Iranian Toman (IRR) currency configured

### Phase 1: Core Store (Digikala-style Design)
- [x] **Theme Architecture**: Complete RTL Persian theme with Digikala-inspired visual identity
  - Header: Top bar (location, links), Main row (logo, prominent search, cart/wishlist/account), Category nav (red)
  - Mobile: Hamburger menu drawer, collapsible search, responsive breakpoints (1024/768/480px)
- [x] **CSS Framework** (`style.css`):
  - Color palette: Primary red #ef4056, Dark #333, Grey bg #f5f5f5, White cards
  - Product cards with hover effects, rating stars, price badges, discount tags
  - Category grid (6-col), Brand strip, Trust bar, Newsletter, Footer
  - Full WooCommerce integration (shop, product, cart, checkout, tabs)
- [x] **Templates**:
  - `front-page.php`: Banner, categories, best sellers, brands, new products, offers, fragrance discovery
  - `header.php` / `footer.php`: Digikala-style layout with trust bar, newsletter, certification badges
  - `woocommerce/content-product.php`: Product card with image, title, rating, price, badges
  - Archive / Single product: Custom styling, fragrance pyramid support
- [x] **Product Catalog**: 10 realistic perfume products created with:
  - Proper categories (men/women/unisex/concentration/volume)
  - Brand taxonomies (Chanel, Dior, Gucci, Versace, Hermes, etc.)
  - Fragrance notes (top/heart/base) stored in custom meta
  - Sale pricing, stock management, SKUs
- [x] **UI Polish**:
  - Vazirmatn Persian font loaded via @font-face
  - Block library CSS conflicts resolved
  - Gradient banner with animation
  - Category icons with hover effects
  - Product card hover lift + shadow
  - Trust bar with icons
  - Brand strip with hover
- [x] **Functionality Verified**:
  - Homepage: 200 OK, renders all sections
  - Shop page: 200 OK, 6 products displayed
  - Product pages: 200 OK, add-to-cart working
  - Cart: 200 OK
  - Checkout: 200 OK
  - Admin: Accessible

---

## 📁 Key Files Created/Modified

```
wordpress/wp-content/themes/aroma-store/
├── style.css              # 2000+ lines Digikala-style RTL CSS
├── header.php             # Top bar, logo, search, nav, mobile drawer
├── footer.php             # Trust bar, newsletter, footer columns
├── front-page.php         # Homepage with all sections
├── index.php              # Fallback
├── functions.php          # Theme setup, WooCommerce support
├── archive-product.php    # Shop archive
├── woocommerce/
│   ├── content-product.php  # Product card override
│   ├── single-product.php   # Single product template
│   ├── cart.php
│   └── checkout.php
└── template-parts/
    ├── content.php
    └── content-none.php

wordpress/wp-content/plugins/lylyrose-core/
├── lylyrose-core.php    # Taxonomy + attribute registration
└── scripts/create_products.php  # 10 sample products generator
```

---

## 🎨 Visual Identity (Original LYLY ROSE)

- **Primary Red**: #ef4056 (Digikala-inspired but distinct)
- **Typography**: Vazirmatn / Tahoma for Persian
- **Layout**: White cards on #f5f5f5 background, 8px radius, subtle shadows
- **Icons**: Inline SVG (search, cart, heart, menu, categories)
- **Product Cards**: Image hover zoom, rating stars, Persian price formatting
- **Mobile-First**: 3 breakpoints, drawer nav, collapsible search

---

## 🔧 Technical Stack

- **WordPress 6.5** + **WooCommerce 11.0**
- **PHP 8.2** (Apache in Docker)
- **MariaDB 10.11**
- **Docker Compose** for local dev
- **Custom Plugin**: `lylyrose-core` for perfume taxonomies
- **Persian RTL**: Full direction, text-align, flex RTL

---

## 📋 Remaining for Phase 2 (Optional Enhancements)

1. **Mini-cart** in header (AJAX)
2. **Product comparison** page
3. **Wishlist** functionality
4. **Advanced search** with autocomplete
5. **Persian calendar** for dates
6. **SEO schema** markup
7. **Image optimization** (WebP, lazy load)
8. **Unit/integration tests**

---

## 🚀 Quick Start

```bash
# Start
docker-compose up -d

# Access
http://localhost:8080          # Storefront
http://localhost:8080/wp-admin # Admin (admin / admin123)
http://localhost:8081          # phpMyAdmin

# Admin credentials
Username: admin
Password: admin123
```

---

## 📦 Deployment Notes

1. Export DB: `docker exec lylyrose-db mysqldump -u root -p lylyrose > dump.sql`
2. Backup wp-content: `tar -czf wp-content.tar.gz wordpress/wp-content`
3. On production: Import DB, upload files, update URLs (`wp search-replace`), set permalinks
4. Ensure `.env` is never committed (in .gitignore)

---

**Phase 1 Status: ✅ COMPLETE - Production-ready Persian perfume e-commerce store operational**
---

## Update — 2026-08-23: Digikala Theme + Test Suite

### New Active Theme: `digikala`
- Built from live Playwright captures of digikala.com (homepage, category, product pages)
- Design tokens from real site: red `#ef394e`, badge red `#d32f2f`, page bg `#f0f0f1`, headings `#23254e`, IRANYekan font stack (Vazirmatn fallback via CDN)
- Templates: header (announcement bar, topbar, search header, category nav, mobile drawer), front-page (hero slider, service row, story circles, شگفت‌انگیز offers carousel with progress bars, banner grid, product rails, category circles, brand strip, magazine), footer, archive with filter sidebar, product card with discount chips, single product with sticky buy box, cart, checkout
- No copyrighted assets copied — layout/colors/structure only
- Activated as default theme (template + stylesheet = digikala)

### Test Suite: `docker/run-tests.sh`
- Section 1: `php -l` on all custom PHP files (34 files) inside the wp container
- Section 2: active theme check (DB query on wp_options)
- Section 3: required plugins active (woocommerce, lylyrose-core)
- Section 4: WooCommerce currency (IRT) and locale (fa_IR) checks
- Section 5: HTTP smoke tests (6 pages -> 200) + theme markup assertions (RTL, announcement bar, Digikala sections, product cards, Toman currency)
- Known MSYS/Git Bash quirk documented in-script: `grep -q` exits 141 (SIGPIPE) on large inputs; use `grep -c` wrapper instead

### Current Status (2026-08-23)
- Tests: 17 passed / 1 failed (currency still USD — Toman conversion is the next work item)

---

## Update — 2026-08-23 (part 2): Persian Plugins + Toman Currency

### Plugins Installed
- **persian-woocommerce** (ووکامرس فارسی) — Persian WooCommerce localization, IRT/تومان currency symbol, Iran states/cities
- **wp-parsidate** — Jalali dates + Persian digit conversion
- **wp-jalali REMOVED** — incompatible with PHP 8.2 (curly-brace string offsets caused a fatal error that broke the entire site)

### Key incident: serialized option corruption
A manual SQL update to `active_plugins` wrote wrong string lengths in the PHP-serialized value (`s:47` instead of `s:43`), causing `unserialize()` to fail and WordPress to silently load zero plugins. Fixed by writing the correct serialization. Lesson: never hand-edit serialized WP options — use `update_option()` or compute lengths precisely.

### Toman Currency Configuration
- `woocommerce_currency` = `IRT` (Toman via persian-woocommerce)
- `woocommerce_currency_pos` = `right_space` (23,000,000 تومان)
- `woocommerce_price_num_decimals` = `0`
- Thousand separator `,`, decimal separator `.`
- Persian digits on all prices via theme filters (`wc_price`, `formatted_woocommerce_price`, `number_format_i18n`) added in digikala/functions.php
- Note: OPcache cached the old functions.php — required `opcache_reset()` + container restart for changes to take effect

### Test Suite Status
18 passed / 0 failed — all green.

---

## Update — 2026-08-23 (part 3): Recommended Plugin Stack

Installed one-by-one with lint → activate → smoke test → commit workflow (`docker/install-plugin.sh`):

| Plugin | Slug | Purpose |
|---|---|---|
| Limit Login Attempts Reloaded | limit-login-attempts-reloaded | Brute-force protection |
| WPS Hide Login | wps-hide-login | Login page moved to /secure-login (wp-login.php 301s away) |
| Wordfence | wordfence | Firewall + malware scanner |
| UpdraftPlus | updraftplus | Backups |
| WP Super Cache | wp-super-cache | Page cache (Apache-compatible; LiteSpeed skipped) |
| Rank Math | seo-by-rank-math | SEO + product schema (PW has IRT schema fixes for it) |
| TI WooCommerce Wishlist | ti-woocommerce-wishlist | Heart icon on product cards |
| ZarinPal Gateway | zarinpal-woocommerce-payment-gateway | Iranian payment gateway |
| Persian WooCommerce Shipping | persian-woocommerce-shipping | پست پیشتاز / تیپاکس rates |
| Dokan Lite | dokan-lite | Multi-vendor marketplace (Digikala's core model) |

Notes:
- install-plugin.sh lints every PHP file against PHP 8.2 BEFORE activating; incompatible plugins are auto-removed (would have caught wp-jalali).
- If activation breaks the site (non-200/fatal), the script auto-deactivates.
- whl_page option set to "secure-login"; run-tests.sh reads it dynamically.
- Full suite: 19 passed / 0 failed with all 14 active plugins.

---

## Update — 2026-08-24: Setup Completion (Wordfence, TI Wishlist, Action Scheduler)

### 1. Wordfence installation completed
The plugin was active but activation had never finished (no API key, no onboarding flags, WAF not enabled). Completed:

- **WAF auto-prepend installed**: generated `wordfence-waf.php` in web root (`/var/www/html/`, NOT wp-content — the file resolves `__DIR__ . '/wp-content/plugins/...'`), added the `# Wordfence WAF` block to `.htaccess` including a `<IfModule mod_php.c>` section for PHP 8 (Wordfence only writes mod_php5/mod_php7 blocks; this container runs php_module with PHP 8.2)
- **WAF mode set to enabled** (was learning-mode) via wflogs config
- Onboarding banners cleared (`onboardingAttempt1=license`, `onboardingAttempt3=skipped`), alert email set to admin@example.com, scheduled scans enabled

**Known limitation — no API key on localhost**: Wordfence's NOC refuses to issue free keys to sites reporting `localhost` URLs (`get_anon_api_key` returns "A premium license must be provided for license downgrade requests" [400]). Consequences:
- WAF rules file (`wflogs/rules.php`) stays empty — request-blocking rules are NOT downloaded
- Scanner still works (core file scan), login security works, hit logging works
- To get full WAF rules: run the site under a real domain, or register at wordfence.com and paste the key manually (admin → Wordfence → Install)

### 2. TI WooCommerce Wishlist setup completed
- Wizard notice ("You're almost ready to start") was shown because option `ti-woocommerce-wishlist_wizard` was never set
- Wishlist page already existed (id 242, slug `/wishlist/`, `[tinvwl_wishlist]` shortcode) but was unlinked in general settings — linked it (`tinvwl-general.page_wishlist = 242`)
- Set `ti-woocommerce-wishlist_wizard = 1`, enabled `woocommerce_product_wishlist_enabled`
- Page verified rendering at http://localhost:8080/wishlist/

### 3. "4 overdue scheduled actions" fixed (زمان‌بندی اقدام)
The Persian warning came from WP Crontrol / Action Scheduler health check: 4 pending actions had missed their schedule dates (site was off / cron didn't run):
- `action_scheduler/migration_hook`, `wc-admin_process_pending_orders_batch`, `woocommerce_update_marketplace_suggestions`, `action_scheduler_run_recurring_actions_schedule_hook`
- Processed all 4 via `ActionScheduler_QueueRunner::instance()->process_action()` — all complete
- Ran WP-Cron `--due-now`; queue now has 0 overdue pending actions (12 pending, all future-dated — normal)

### Tooling note
WP-CLI is not baked into the container; installed ad-hoc to `/tmp/wp-cli.phar` (ephemeral — lost on container rebuild).

### Test Suite Status
19 passed / 0 failed.

---

## Update — 2026-08-24 (part 2): Performance & Iran-specific Stack

Prioritized install pass, one-by-one with lint → activate → smoke test:

### 1. Reliable system cron (no plugin)
- `DISABLE_WP_CRON=true` added to wp env in docker-compose.yml
- New sidecar service `cron` (curlimages/curl) hits `http://wordpress/wp-cron.php?doing_wp_cron` every 60s
- Root-cause fix for the Action Scheduler backlog from earlier today

### 2. WP Mail SMTP — installed & active
- Defaults set (mailer=smtp, from "Lyly Rose" <admin@example.com>)
- **User action needed**: enter real SMTP host/credentials at WP admin → WP Mail SMTP → Settings

### 3. Persian WooCommerce SMS (افزونه پیامک ووکامرس) — installed & active
- Supports Kavenegar, SMS.ir, Melipayamak and other Iranian gateways
- **User action needed**: pick gateway + API key under پیکربندی → پیامک

### 4. National ID (کد ملی) checkout field — implemented natively
- Added to lylyrose-core: `includes/class-national-id.php`
- Required billing field on checkout, saved to order meta `_billing_national_id`, shown in admin + emails
- Full mod-11 checksum validation (`ASC_National_ID::is_valid()`); invalid codes blocked with Persian error
- No third-party plugin needed

### 5. Redis object cache — live
- New `redis` service (redis:7-alpine, 128MB LRU) in docker-compose.yml
- PHP redis extension now baked into a custom image via new `docker/Dockerfile` (pecl install; ad-hoc pecl installs are lost on container rebuild)
- `WP_REDIS_HOST=redis` constant added; Redis Object Cache plugin installed, drop-in enabled (~1.8k keys after two page loads)

### 6. Autoptimize — installed & active
- CSS/JS/HTML minify+aggregate master switches enabled programmatically
- Assets now served from `wp-content/cache/autoptimize/`

### 7. Image optimization — not needed
All existing uploads are already WebP; WebP Express plugin was installed then removed.

### 8. Torob product feed (افزونه رسمی ترب) — installed & active
- products-extractor-for-woocommerce; configure feed URL inside the plugin when ready to list on torob.com

### 9. Goftino live chat (گفتینو) — installed & active
- **User action needed**: paste Goftino site code in its settings

### 10. Analytics — installed & active
- Microsoft Clarity (needs Clarity project ID)
- Google Site Kit (needs Google account sign-in flow for GA4)

### Incidents fixed during this pass
1. **Permalinks wiped** (all pages 404): `permalink_structure` option and the `.htaccess` WordPress block were emptied (likely during a container recreate). Restored structure `/%postname%/` + rewrote the rewrite block manually.
2. **WooCommerce Coming Soon mode was ON**: shop page rendered "چیزهای بزرگ در افق هستند" placeholder instead of products. Disabled via `woocommerce_coming_soon=no`.
3. **Checkout 302 to /cart/**: WC redirects checkout when cart is empty. Fixed by hooking `woocommerce_checkout_redirect_empty_cart → __return_false` late (plugins_loaded@99) in lylyrose-core. Note: the same filter added earlier (plugins_loaded@20 inside class constructor) did NOT take effect on web requests until a full container restart cleared stale state.
4. **wp-login.php now 404s instead of 301**: current WPS Hide Login versions render a 404 template (stronger hiding). Test suite updated to accept 301/302/404.
5. **my-account page id unassigned**: fixed `woocommerce_myaccount_page_id = 9`.

### Test Suite Status
19 passed / 0 failed.

---

## Update — 2026-08-24 (part 3): Test Suite Expanded to 46 Tests

`docker/run-tests.sh` grew from 19 to 46 tests across 10 sections. New coverage:

| Section | What it verifies |
|---|---|
| 3 (expanded) | All 10 stack plugins active (Redis cache, SMTP, SMS, Autoptimize, Torob, Goftino, Clarity, Site Kit) |
| 6 Cron | lylyrose-cron sidecar up, DISABLE_WP_CRON defined, sidecar reaches wp-cron.php internally, zero overdue Action Scheduler actions |
| 7 Redis | PONG response, object-cache.php drop-in, PHP redis ext, wp_cache round-trip |
| 8 Checkout/National ID | checkout renders real checkout UI, field registered for block + classic checkout, label کد ملی, 4 checksum unit cases, empty-cart checkout reachable |
| 9 Autoptimize | assets served from cache/autoptimize, cache files exist |
| 10 Site integrity | permalink /%postname%/, coming-soon off, wishlist page + wizard flag |

### Bugs the new tests caught and fixed
1. **Missing page.php in digikala theme**: checkout/cart/my-account fell through to index.php's blog-card layout — WooCommerce pages rendered as editorial article listings with no functional UI. Added `wordpress/wp-content/themes/digikala/page.php` rendering `the_content()` full-width.
2. **Block checkout ignores classic fields**: WooCommerce now renders checkout as a React block; `woocommerce_checkout_fields` filters don't apply. Registered the کد ملی field via `woocommerce_register_additional_checkout_field()` (id `lylyrose-core/national-id`, required, address location). Classic shortcode path keeps its own registration.

### Test Suite Status
46 passed / 0 failed.

---

## Update — 2026-08-25: Product Page v1.1 + Performance Incident

### Digikala-style product page (theme v1.0.2, lylyrose-core v1.1.0)
`single-product.php` rebuilt out to a fuller Digikala clone:
- Latin subtitle extracted from product excerpt, rendered LTR under the title
- Discount badge (٪ off) in info column and mobile bar when a sale price is active
- Product code via new `ASC_Product_Code` class (`includes/class-product-code.php`) — falls back to SKU
- Reviews histogram (5→1 star bars with Persian numerals), Q&A card placeholder
- Mobile sticky purchase bar: price + add-to-cart form (simple) or jump link (variable)
- ~140 lines of matching CSS in `style.css`

### Performance incident — pages timing out at 15s (root cause: opcache config flip)
HTTP smoke tests intermittently returned `000` because uncached pages took 15s+ (curl's
15s timeout). Diagnosis path:

1. All pages actually returned 200 server-side; only *uncached* pages were slow.
2. Homepage fast (WP Super Cache full-page hit), every WP bootstrap slow (~15-20s).
3. Per-plugin timing mu-plugin: plugin includes only ~2.3s; bulk of time spread across
   core load + init hooks — consistent with per-file filesystem stat overhead, not any
   single plugin.
4. **Root cause**: uncommitted edit had flipped `opcache.validate_timestamps = 1` in
   `docker/uploads.ini`. On Docker Desktop for Windows bind mounts, stat() on every PHP
   include is brutally slow. Reverted to committed value (`validate_timestamps = 0`,
   `revalidate_freq = 0`) — opcache never re-checks files; container restart required
   after code edits.

Lesson: with `validate_timestamps=0`, PHP file changes need `docker restart lylyrose-wp`
to take effect. That trade-off is intentional for this dev setup (bind-mount I/O is the
bigger cost).

Secondary incident during diagnosis: hand-editing the serialized `active_plugins` option
in MySQL produced length-mismatched serialized data → `unserialize()` failed → WordPress
saw zero plugins active. Restored programmatically via `update_option()` with the full
22-plugin array. Never hand-write serialized PHP values.

### Test Suite Status
46 passed / 0 failed.

## 2026-08-31 — Rebrand: visible Digikala labels -> Aromaland (لیلی رز)

The UI borrowed digikala.com's layout as a UX reference, but user-visible brand
strings still said دیجی‌کالا. Replaced all of them with لیلی رز / لیلی رز:

- header.php: logo wordmark (header + mobile), app-download announce bar,
  search placeholder, and the fallback category menu (also fixed its fake
  slugs -> real product_cat slugs).
- single-product.php: دیجی‌کلاب points line -> باشگاه مشتریان.
- footer.php: shop description + copyright line.
- DB: wp-search-replace دیجی‌کالا -> لیلی رز in 100 product descriptions.
- No image/logo assets exist (logo is a text wordmark), so nothing else to swap.
- Internal names kept: theme folder `digikala`, `lylyrose_*` functions,
  `lylyrose-theme` body class, text domain — they are code identifiers, not UI.

Tests: 46 passed / 0 failed.

## 2026-09-01 — Theme versioning snapshots + admin login fix

- Theme snapshots (copies, originals untouched):
  - `aroma-store-old/` — archive copy of the inactive `aroma-store` theme
    (style.css renamed to "Lyly Rose Old", Version 2.0.0-old).
  - `digikala-v1.0.0/` — frozen snapshot of the active `digikala` theme at
    v1.0.0 ("LYLY ROSE — Digikala v1.0.0 (RTL)"), kept for rollback; header
    comment says do-not-develop-here.
- Active `digikala` theme versioning:
  - New `lylyrose_version()` helper reads Version from style.css (single
    source of truth) and replaces the hardcoded 1.0.7 asset version, so
    cache-busting follows the style.css header.
  - Admin-bar node `قالب digikala <ver>` for admins only; invisible to
    visitors.
- Admin login fixed: wp-cli `user update admin --user_pass=Aroma2026!`.
  Verified: POST /secure-login -> 200 dashboard (پیشخوان). Note the login
  page POST shows an LLAR (Limit Login Attempts Reloaded) flow cookie;
  curl -L lands on wp-admin with 200.
- Theme `digikala` remains the active theme; all pages smoke-tested 200.

Tests: 46 passed / 0 failed.

## 2026-09-01 — Live admin login fix (password + wp-admin 403)

Two separate faults made the live admin unreachable:

1. Password: the earlier reset only touched the LOCAL Docker database. Reset the
   live one too via a one-off token-guarded PHP script deployed with the
   chunk+assembler FTP pipeline (no wp-cli on host, exec() disabled), then
   deleted the script and all deploy artifacts. Live admin is now
   `admin` / `Aroma2026!`, verified with wp_check_password.
2. `/wp-admin/` returned 403 from BitNinja-WafPro even with a valid auth
   cookie, while `/wp-admin/index.php` returned 200 — the same directory-index
   quirk that previously broke `/`. Added to the subdomain .htaccess:
   `RewriteRule ^wp-admin/$ /wp-admin/index.php [L]`

Verified: POST /secure-login -> 200 dashboard (پیشخوان) on the live site; all
public pages still 200. Anonymous /wp-admin/ 302s to /301/ (WPS Hide Login's
redirect slug), which is the plugin's intended behaviour.

## 2026-09-01 — Ambiguous product codes no longer 404

`ASC_Product_Code` previously required exactly one SKU match for a digit run and
404'd shared runs (e.g. three seed copies of `GUCCI-BLOOM-75` all linked to
`/product/sku-75/…`, which 404'd — 7 runs / ~30 products affected).

New deterministic scheme in class-product-code.php:

- Ownership rule: the OLDEST product (lowest ID) with a given first digit run
  keeps the run-based code (`resolve()` sorts matches). `get_code()` emits the
  run code only for the owner; every other product falls back to its unique
  ID-based code (`sku-<id>`).
- ID fallback in `resolve()`: pure-digit codes with no SKU match resolve to a
  published product whose ID equals the code — siblings become reachable.
- Slug disambiguation in `parse_request()`: an ID-based code can coincide with
  another product's digit run (e.g. ID 30 vs the VERSACE-30 run). When the URL
  slug names a product whose own code equals the requested path, that product
  is served instead of a redirect to the default owner. Added the missing
  `is_main_query()` guard (its absence caused infinite recursion through
  `get_posts()` → 500 memory exhaustion).
- Plugin version 1.2.0 → 1.3.0 (self-healing rewrite flush).

Verified locally: shared-run shorts (75/100/50/30/10/5) 301 to their owner's
canonical; sibling ID codes (sku-18/21/25/31/40) 301/200 to their own product;
the sku-30 collision serves the ID-30 product (Davidoff Cool Water) at its own
slug URL; unknown codes still 404; legacy slug URLs still 301.

Tests: 46 passed / 0 failed.

## 2026-09-01 — Shop faceted filters: gender / concentration / volume

Extended the shop/category filter rail (which already had brand/price/
availability/discount) with the three remaining product-attribute facets,
closing FEATURES_ROADMAP P0 #3:

- lylyrose_filter_facets() maps GET params dk_brands / dk_gender /
  dk_concentration / dk_volume to the pa_* taxonomies + Persian labels;
  lylyrose_facet_selection() reads a facet from the query string.
- lylyrose_shop_filters() now applies all four facets as tax_query terms
  (previously brand-only).
- archive-product.php renders the generic facet groups (same checkbox UI as
  brands, ordered by product count desc), preserves all other filters in
  each group's hidden fields, auto-submits per-form, and shows «حذف همه»
  when any facet is active.
- New wp_robots filter: any filtered shop/category URL gets
  noindex,follow (facet permutations stay out of the index).
- style.css: .dk-attr-list reuses the brand checkbox styling; theme
  Version 1.0.0 -> 1.1.0 (dynamic asset cache-busting picks it up).

Verified locally: dk_gender/dk_volume/dk_concentration each return the
expected product sets (term counts match), combined facets intersect
correctly (men+100ml=3, woman+100ml=0 which matches the data), filters
work on category archives, robots meta correct on clean vs filtered
pages. Tests: 46 passed / 0 failed.

## 2026-09-01 — Search a product code lands on the product

FEATURES_ROADMAP P1 #7: ASC_Product_Code::search_redirect() hooks
pre_get_posts — a site search of "sku-<digits>", "sku <digits>" or bare
digits that resolve to a product now 301s straight to that product's
canonical URL. Non-numeric searches and unmatched codes fall through to
the normal search results. Plugin version 1.3.1 (rewrite version bump is
harmless; no rules changed).

Verified locally: ?s=sku-75, ?s=75 (shared run -> owner), ?s=25 (ID-code
sibling), ?s=10479648 (full DIGIKALA SKU digits) all 301 to the right
canonical; ?s=999999 and Persian text searches still 200 on the results
page. Tests: 46 passed / 0 failed.

## 2026-09-01 — Store pages: about / contact / track-order / FAQ (P1 #6)

Four trust/service pages, self-healing from code so the live host's own DB gets them on deploy:

- **Plugin** (`lylyrose-core` v1.4.0): new `includes/class-store-pages.php` — `ASC_Store_Pages::ensure_pages()` creates any missing page (about/contact/track-order/faq, publish) once per plugin version via `asc_store_pages_version` option; existing slugs never overwritten, admin-trashed pages stay trashed until version bump. Also owns:
  - Order lookup (پیگیری سفارش): POST order number + email/phone; matches billing email (case-insensitive) or last-9-digits phone (Persian/Arabic digits normalized, +98/0/9xx all equal). Generic error for both "no order" and "wrong contact" (no enumeration oracle). Per-IP transient rate limit 12/15min, nonce required. Result card: status chip, 3-step progress (ثبت/پردازش/تحویل), date/total/payment-method, Persian digits.
  - Contact form: name/email/subject/message -> wp_mail to admin with Reply-To; honeypot field; validation + rate limit; Persian notices.
- **Theme** (digikala v1.2.0): new page-about.php (hero stats + story), page-contact.php (info cards + form), page-track-order.php (form + status card + help), page-faq.php (17 CSS-only details/summary accordions in 5 sections). Shared styles appended to style.css (`.dk-page-narrow/card/lead`, forms, notices, track steps, FAQ). Footer: پیگیری سفارش and سوالات متداول now point at real pages (were my-account/orders and #). All four footer store-page links resolve via ASC_Store_Pages::url().

Verified locally: all 4 pages 200; footer links correct after clearing WP Super Cache + Autoptimize (stale cached homepage was showing old footer); order lookup success (order #261 + 09121234567 -> status card در حال انجام), wrong phone rejected, bad nonce rejected, nonexistent order rejected with same generic message; contact validation paths unit-checked directly (invalid email/empty message/honeypot). Note: local contact send can't complete (no sendmail in container; WP Mail SMTP has mailer=smtp but no host configured) — sending works on live via site SMTP or needs SMTP creds; the failure path shows the Persian fallback error. Tests: added section 11 (5 new checks incl. rejection + accordion count); fixed grep -c line-counting bug (grep -o | wc -l). 55 passed / 0 failed.

## 2026-09-01 — Persian/branded transactional emails (P1 #9)

WooCommerce's `email_improvements` feature flag is ON (WC 11 default), which switched
default email subjects/headings to newer English strings the installed fa_IR pack does
not cover — so order emails shipped in English despite the site being Persian. Root
cause of the earlier "controller has_translation() false" mystery: the improvement-era
strings simply have no loaded translation; the .mo on disk does contain some of them but
WP 7.1's translation controller refuses to serve them (exact internals unresolved —
worked around entirely).

- **Plugin** (`lylyrose-core` v1.5.0): new `includes/class-emails.php` — `ASC_Emails`:
  - `gettext_woocommerce` filter (prio 100) maps ~40 byte-exact English default strings
    (subjects/headings/additional content for new-order, cancelled, failed, completed,
    refunded, customer-note, reset-password, verify-email, gateway-enabled) to Persian.
    Strings verified collision-free outside email templates, so front-end text is
    untouched. Placeholders {site_title}/{order_number} pass through untouched.
  - `woocommerce_email_styles` filter appends `#wrapper { direction: rtl; }` when
    is_rtl() — the dir="rtl" attribute alone doesn't fully right-align in Gmail/Outlook.
- **Email options** (DB): base_color #8526ff→#ef394e (brand red), font_family→Tahoma
  (WC safe list, best Persian rendering in email clients), header_alignment→right,
  from_name→لیلی رز, footer_text Persian with {site_title}/{store_address}.

Verified locally by rendering a real completed-order email through WC's own pipeline
(docker/email-check.php, copied into the container by the test suite): Persian subject
«سفارش شما از ... در راه است!», Persian heading «خبرهای خوب در راه است!», Persian
additional content, dir="rtl", direction:rtl CSS, Tahoma stack, #ef394e all present in
the styled HTML; admin new-order subject «[Lyly Rose]: سفارش جدید: #261» also Persian.
Tests: new section 12 (6 checks), fixed Git Bash pwd → pwd -W for docker cp. 61 passed /
0 failed. Note: emails aren't actually sent locally (no SMTP in container) — rendering
pipeline is what's verified; live sending uses the host's mail.

## 2026-09-01 — WebP image delivery (P0 #2)

Roadmap item 2 called for installing Converter for Media. Investigation changed the plan:
the media library is **already 100% WebP** — all 100 attachments from the Digikala product
import were stored as WebP (largest image file is under 40 KB), and pages ship `srcset`
with 100/150/300px variants plus `loading="lazy"`, `decoding="async"`, and
`fetchpriority="high"` on the LCP image. Converting an all-WebP library adds nothing, and
the plugin would have meant ~100 files to FTP-deploy (the >13 KB chunk+assembler dance for
several of them) plus .htaccess rewrite rules on a BitNinja-guarded host — real risk,
zero gain.

- **Plugin** (`lylyrose-core` v1.6.0): new `includes/class-images.php` — `ASC_Images`:
  - `image_editor_output_format` → JPEG *and* PNG uploads are converted to WebP by WP
    core's image editor (original kept). Closes the real gap: future manual/vendor uploads
    would otherwise have reintroduced heavy raster formats.
  - `big_image_size_threshold` → 2560px cap on the longest side.
  - `wp_generate_attachment_metadata` → debug-log line per converted attachment for
    deploy verification.
- **No new plugin installed**; no .htaccess/nginx changes; nothing for Super Cache or
  Autoptimize to interact with.

Verified locally (docker/image-check.php, copied in by the test suite): a generated JPEG
and a generated PNG each sideloaded through `media_handle_sideload` land as
`image/webp` with all 6 generated sizes WebP; library probe 100 images / 0 non-WebP;
threshold probe returns 2560. Probe attachments deleted after each run. Tests: new
section 13 (5 checks). Local-page slowness during this session (~18s uncached pages) was
traced to Docker→Windows bind-mount `stat()` latency (deep plugin dirs ~1.5ms/stat,
~17s full bootstrap; home unaffected because Super Cache serves static HTML) —
pre-existing dev-environment artifact, unrelated to this change; see section 8 note in
CONTINUATION.md. One suite run had 2 curl-timeout flakes from that; subsequent full runs
green.

## 2026-09-01 — Coupon surfaces: cart field + campaign banner (P1 #10)

Coupons were enabled with zero entry points (the custom cart template never rendered
`woocommerce/cart/coupon.php`) and zero coupons in the DB.

- **Cart coupon field** (theme `woocommerce/cart/cart.php`): always-visible
  Digikala-style field in the payment summary card, above the checkout button —
  `.dk-coupon-form/.dk-coupon-row/.dk-coupon-input/.dk-coupon-btn` in style.css
  (teal button, LTR input for Latin codes). Posts `coupon_code` + `apply_coupon` with
  the `woocommerce-cart-nonce` exactly as `WC_Form_Handler::update_cart_action()`
  expects — WC core handles validation, success/error notices, and the discount row.
  Hidden once a discount is applied (`WC()->cart->get_coupons()`).
- **Campaign banner** (plugin `lylyrose-core` v1.7.0 `includes/class-coupons.php`):
  `ASC_Coupons` renders a red gradient strip (`dk-campaign`) on `wp_body_open` prio 20 —
  under the announcement bar on every page. Text/URL/active come from the
  `asc_campaign_banner` option so the owner can publish a code from wp-admin
  (options screen or code) without touching templates.
- **WELCOME10 coupon** seeded: 10% off, individual use, 1× per user, no expiry.
- **Gotcha fixed**: first template draft used `WC()->cart->has_discounts()` which does
  not exist in WC 11 → fatal on every cart render (500). Replaced with
  `get_coupons()` emptiness check.
- **Gotcha**: stale `coupon_id_from_code_<md5>` object-cache entry after deleting a
  probe coupon made `new WC_Coupon('welcome10')` throw «شناسه کوپن نامعتبر است» —
  flushing the cache fixed it. If coupons ever "vanish" after cleanup scripts,
  flush Redis.

E2E over HTTP: add-to-cart → cart page renders form (nonce present) → POST WELCOME10 →
200 with «کد کوپن با موفقیت اعمال شد», `cart-discount coupon-welcome10` row
(-۹۹۴,۰۰۰ تومان on a ۹,۹۴۰,۰۰۰ total), remove link `[پاک]`. Invalid code POST
produces no discount row. Tests: new section 14 (8 checks).

## 2026-09-02 — Frequently bought together (P2 #12)

Digikala's co-purchase section on the product page, driven by real order data —
no plugin, ~140 lines total.

- **Plugin** (lylyrose-core v1.8.0 `includes/class-frequently-bought.php`):
  `ASC_Frequently_Bought::get_partners($product_id)` scans the last 500 completed
  orders via `wc_get_orders` (works under HPOS — `wc_get_orders` queries the
  orders table), scores how often each other product line-item co-occurs with the
  target, filters score ≥ 1, caps at 8 partners, and caches the ID list in a
  1-day transient `asc_fbt_<pid>`. On `woocommerce_order_status_completed` the
  transients for that order's product IDs are deleted inline (no WP-Cron
  dependency — see gotcha below).
- **Theme** (`woocommerce/single-product.php` + style.css, theme v1.4.0): section
  `.dk-fbt` between extras and related — «اکثراً با هم خریداری شده‌اند» heading,
  pair row (target + top partner images, prices, red «خرید هر دو» button that
  POSTs `add-to-cart` for the target to the cart), then up to 7 more partner
  cards in `.dk-fbt-more`. Renders **nothing** when a product has no co-purchase
  data — important because live has zero orders, so nothing shows there yet.
- **Gotcha — don't rely on WP-Cron for invalidation**: the first draft scheduled
  `asc_fbt_invalidate` via `wp_schedule_single_event` on order completion, but
  WP-Cron doesn't fire during CLI/eval probes (and is generally unreliable on
  shared hosts), leaving stale empty caches. Inline `delete_transient()` in the
  status hook is trivially cheap for ≤ item-count keys — replaced.
- **Gotcha — same-status transitions**: `wc_create_order(array('status' =>
  'completed'))` then `update_status('completed')` is a same-status no-op — the
  `woocommerce_order_status_completed` hook never fires. Real checkout always
  transitions pending→completed, but test seeds must create pending first.

E2E: seeded 3 orders over products 75/76/77 → partners correct (75→[77,76] by
score); a genuine pending→completed order for 137+139 updated both products'
lists immediately (hook fired, cache dropped, recomputed); negative case
(untouched product 11) renders zero `dk-fbt` markup. Tests: new section 15
(seeds a real completed order, asserts title/button/cards/negative case, then
deletes all orders + transients — asserts clean state).

## 2026-09-02 — Instagram / social proof strip (P2 #14)

Homepage shop-the-grid strip, owner-editable, no third-party JS.

- **Plugin** (lylyrose-core v1.9.0 `includes/class-instagram.php`):
  `ASC_Instagram` stores `{handle, items:[{image_url, post_url}]}` in the
  `asc_instagram` option (max 6 rows) with a Settings → «اینستاگرام لیلی رز»
  page built on the Settings API. `sanitize()` runs `sanitize_text_field` on the
  handle and `esc_url_raw` on both URLs, dropping rows with no image — verified
  that `javascript:` URLs and `<script>` in the handle are stripped.
- **Theme** (front-page.php + style.css, theme v1.5.0): `ASC_Instagram::render()`
  between the brands rail and the magazine section — «اینستاگرام ما» heading,
  handle, «مشاهده پیج» link to instagram.com/<handle>, then a 6-up grid of
  square cells (3-up ≤1024px, 2-up ≤480px). Images are `loading="lazy"` +
  `decoding="async"`; cells link to the post URL with `rel="noopener nofollow"`.
- **No Instagram API on purpose**: instagram.com is unreliable from Iran and an
  API integration would need a token the shop owner can't refresh. The grid
  takes plain image URLs, so any host (CDN, uploads folder, IG proxy) works.
- Renders nothing when the option is empty, so live stays unchanged until the
  owner fills the settings page. Tests: new section 16 (6 checks — markup,
  Persian heading, images from option, lazy attribute, sanitizer hardening,
  unconfigured-renders-nothing; backs up and restores the real option value).

## 2026-09-02 — Persian sales reports + Excel export (P2 #15)

Admin dashboard for accounting, no third-party reporting plugin.

- **Plugin** (lylyrose-core v2.0.0 `includes/class-reports.php`):
  `ASC_Reports` adds a «گزارش فروش» admin menu page — from/to date range over
  completed+processing orders (HPOS-safe via `wc_get_orders`), summary cards
  (order count, revenue, items sold, average order), and a top-10 products
  table. CSV export streams orders with Persian headers and a UTF-8 BOM so
  Excel opens it correctly.
- **Signed export token instead of a nonce**: some active security stack
  regenerates the WP session token on every admin page load, so a
  nonce-bearing export link 403s by the time it's clicked («این لینک منقضی
  شده است»). Export links carry an HMAC-SHA256 over user ID + range + current
  day keyed with `wp_salt('auth')` — verified per request against
  `current_user_can('manage_options')` + `hash_equals`; a bad token dies 403.
  Same day-validity window a nonce has, without the session dependency.
- **Testing auth gotcha**: synthetic admin cookies must be built from a
  session token created via `WP_Session_Tokens::get_instance($id)->create()`
  in the same process that generates the cookie value — tokens created by
  `wp_set_auth_cookie` in CLI weren't verifiable cross-process, and
  hand-picking tokens from the session store without creating them failed
  validation.
- Tests: new section 17 (7 checks — stats aggregation over a seeded order,
  BOM, Persian headers, order row, Persian status, 403 on bad token, order
  cleanup).

## 2026-09-02 — Loyalty wallet (P2 #11)

Wallet + cashback via the woo-wallet plugin («کیف پول برای ووکامرس», ~20k
installs, Persian-native over wallet-system-for-woocommerce at 2k).

- **Install**: `wp plugin install woo-wallet --activate` in Docker + the
  fa_IR language pack via `wp language plugin install woo-wallet fa_IR`
  (front-end strings render Persian — «کل موجودی», «شارژ کیف پول», Persian
  transaction notes; admin stays English, fine).
- **Config** (three options, set via wp-cli):
  `_wallet_settings_general` — topup on (product «شارژ کیف پول لیلی رز»,
  min 100k / max 20M Toman), partial payment on with auto-deduct,
  tax mode `payment`, transfers off, gateway charge off.
  `_wallet_settings_credit` — cashback on, cart rule, 2% of order capped
  5,000,000, min cart 500,000, triggers on `completed` only
  (`process_cashback_status`), refund clawback on (never negative).
  `_wallet_settings_actions` — `product_review__enabled=yes` +
  `product_review__amount=50000` (credit for the first review per
  product per user; registration credit is off since registration is
  closed). Amounts are entered in IRT — no conversion.
- **Action settings shape**: actions read the merged
  `_wallet_settings_actions` option with `{action_id}__{field}` keys
  (e.g. `product_review__amount`); the abstract strips the prefix back
  into `$this->settings`. A missing `comment_approved` int-cast makes
  `new_product_review` silently no-op on `wp_insert_comment`-seeded
  comments (string `"1"` !== int 1) — fire the `comment_post` hook with
  an int to test.
- **Checkout behaviour verified**: topup cart (only the recharge product)
  hides the wallet gateway AND the partial-payment fee by design (no
  paying for wallet credit with wallet credit) — ZarinPal only. A real
  product cart with total > balance gets the auto partial fee
  «از طریق کیف پول» = −balance, remainder via gateway; total ≤ balance
  would show the wallet gateway radio (`پرداخت با کیف پول`).
- **Theme integration**: the Digikala theme's my-account sidebar hardcodes
  its nav, silently dropping woo-wallet's `woocommerce_account_menu_items`
  entry («کیف پول من»). Added a `my-wallet` nav item to
  `woocommerce/myaccount/my-account.php` gated on
  `class_exists('Woo_Wallet_Frontend')` (v1.5.1) pointing at
  `/my-account/my-wallet/` — note the plugin's endpoint is `my-wallet`;
  its menu-item slug `woo-wallet` 404s on this theme.
- **Pre-existing bug fixed en route**: Dokan tables (`wp_dokan_orders`
  etc.) were missing locally, spamming DB errors on every order save —
  created via `WeDevs\Dokan\Install\Installer->create_tables()`.
- Cashback on a completed order is instant (2% of 9,940,000 = 198,800
  credited with note «شارژ کیف پول از طریق بازپرداخت نقدی #ORDER»).
- Tests: new section 18 (11 checks — plugin+recharge product, config
  read-back, cashback credit, review credit, min-topup rejection, partial
  fee, account nav, wallet page balance/history in Persian, guest
  isolation, cleanup). 106 total.

## 2026-09-02 — Test suite determinism hardening

Three recurring flake sources found while shipping the wallet and fixed in
`docker/run-tests.sh`:

- **`grep -q` SIGPIPE trap**: under `set -o pipefail`,
  `printf '%s' "$BIGPAGE" | grep -q needle` fails when grep exits at the
  first match — printf gets SIGPIPE, pipeline exits 141, check fails even
  though the needle exists. Only bites on large (67KB+) pages and only
  intermittently. Fix: the suite's `html_has` helper (`grep -c`, reads to
  EOF) — all big-page greps converted.
- **30s curl timeout on slow windows**: section 13's WebP image conversion
  hammers the container right before section 14, and uncached local pages
  take up to ~18s (bind-mount `stat()` latency), so a `--max-time 30` can
  silently abort the `?add-to-cart=137` request → cart stays empty →
  coupon-form checks fail. Fix: `--max-time 120 --retry 2` everywhere in
  the flow, plus a verify-and-refetch loop that re-reads `/cart/` if the
  item didn't land.
- **Fixture stock drift**: the wallet section's test orders decrement
  product stock (137, 139) and order deletion doesn't restore it, so the
  coupon section could hit out-of-stock. Fix: sections restore fixture
  stock (`set_stock_quantity` + `set_stock_status`) before add-to-cart;
  review-credit guard metas (`_woo_wallet_comment_commission_received_%`)
  are purged before seeding so the strict idempotency check stays honest.

## 2026-09-02 — Mobile OTP login & registration (P0 #1)

In-house build (no third-party OTP plugin): lylyrose-core gains `ASC_OTP`
([class-otp.php]), the theme gains a Digikala-style two-tab login (v1.6.0).

- **Flow**: mobile → `asc_otp_request` (normalize via `PWSMS()->modify_mobile`,
  rate-limit 5/hour, resend cooldown 60s, code hashed with `wp_hash_password`
  into a 120s transient) → SMS via `PWSMS()->send_sms()` → `asc_otp_verify`
  (max 5 attempts) → log in existing `billing_phone` match or auto-register a
  customer (`user_9xxxxxxxx`, random password, `billing_phone` + IR country).
- **Theme**: `form-login.php` two tabs — ورود سریع (OTP, default) and ورود با
  گذرواژه; `assets/js/otp-login.js` drives request/verify/resend countdown,
  nonce carried in `data-otp-nonce` on the login card; `otp-login.js` enqueued
  only on `is_account_page()` when logged out; OTP CSS in style.css 1.6.0.
- **Delivery**: real SMS gateway when credentials exist in persian-woocommerce-
  sms; with none configured the plugin's Logger gateway writes the code to
  `wc-logs/pwsms.log` (dev sink; also what tests read). Live deploy needs real
  gateway credentials before OTP works in production.
- **Registration option**: `woocommerce_enable_myaccount_registration` → yes
  (was off; email registration was effectively unavailable anyway).
- **Normalization lesson**: `PWSMS()->modify_mobile()` returns `+989…` (not
  `09…`) for `+98`/`0098` inputs, and its `preg_replace('/\D/')` strips the
  `+` first — so `ASC_OTP::normalize_mobile()` post-converts `+989xxxxxxxxx`
  → `09xxxxxxxxx` and validates `^09\d{9}$` itself. Persian digits OK.
- Tests: new section 19 (18 checks — nonce/tabs/labels/script render, request
  → SMS sink → verify → customer created with role + session, re-login,
  wrong code, resend cooldown, invalid mobile, bad nonce, rate limit 6th
  request blocked, Persian digits, cleanup). 124 total.
- Live deploy: plugin dir FTP'd (woo-wallet ~3.5MB, chunked), fa_IR pack
  + options via a token-guarded script, deleted after.

## 2026-09-03 — Cart abandonment recovery (P0 #4)

woo-cart-abandonment-recovery v2.1.3 (CartFlows) + in-house Persian SMS glue
in lylyrose-core (`ASC_Cart_Abandonment`, class-cart-abandonment.php).

- **Capture**: plugin JS scrapes the checkout form (email + `#billing_phone`
  among others) and POSTs to `cartflows_save_cart_abandonment_data` as the
  customer types; rows land in `wp_cartflows_ca_cart_abandonment` with
  phone in the serialized `other_fields` (`wcf_phone_number` key).
- **Cron**: `cartflows_ca_update_order_status_action` (15-min tick, 30-min
  cut-off) flips stale `normal` carts to `abandoned`, generates coupon if
  enabled, fires `wcf_ca_process_abandoned_order` per cart, and schedules
  follow-up emails from the template table.
- **Persian SMS glue**: `ASC_Cart_Abandonment` hooks that action; normalizes
  the phone via `ASC_OTP::normalize_mobile`, skips unsubscribed and
  non-abandoned carts, and sends "سبد خرید شما در انتظار تکمیل است…" with the
  tokenized recovery link (`wcf_ac_token`) and coupon (if any) via
  `PWSMS()->send_sms()`. Same Logger-gateway dev sink as OTP.
- **Templates**: the 3 seeded English samples replaced with Persian Aromaland
  ones — 1h یادآوری سبد رهاشده / 6h راهنمای تکمیل خرید / 1d پیشنهاد ویژه بازگشت
  — all activated; from-name لیلی رز; Persian GDPR notice on (consent-labelled
  phone capture); cut-off 30 min (was 15).
- Tests: new section 20 (11 checks — tracking script + nonce + billing_phone
  render on checkout, capture row with phone, cron flip to abandoned, SMS
  delivered with recovery token, follow-up emails scheduled, unsubscribed cart
  gets no SMS, cleanup). 135 total across 20 sections.
- Live deployed 2026-09-03: plugin tar.gz chunk+assembler (106 chunks), Persian
  options + templates via token-guarded installer, lylyrose-core files +
  theme OTP files (form-login, otp-login.js, functions.php, style.css v1.6.0)
  FTP'd/chunked — the OTP theme files had never shipped, caught by live
  verification (missing class-otp.php fatal → fixed in same pass). Verified
  live: /my-account shows OTP tabs + nonce; checkout (with cart) shows
  tracking JS + capture nonce + billing_phone. Deploy artifacts deleted.
- Live note: real SMS gateway credentials still required (same as OTP).

## 2026-09-03 — ZarinPal sandbox e2e payment (P0 #5)
- **Goal**: prove the full purchase → gateway → callback → order-completed flow
  headlessly, then encode it as a permanent test section. Payment is the one
  flow that must not fail silently.
- **Gateway config** (local): `woocommerce_WC_ZPal_settings` = enabled yes,
  sandbox yes, merchant `00000000-0000-0000-0000-000000000000` — the sandbox
  accepts ANY merchant UUID, so no secrets needed; IRT site currency → plugin
  multiplies amount ×10 for Rial; callback registered as
  `woocommerce_api_wc_zpal` → `/wc-api/WC_ZPal/`.
- **Sandbox probing findings**: `request.json` returns fake authority
  `S000…`; StartPay page HTML embeds literal
  `pg/verify/<vtoken>/?status=OK|NOK` links; GETting the OK link marks the
  session paid, after which `verify.json` returns code 100 + ref_id. This
  makes a browser-free payment simulation possible: request → StartPay →
  extract vtoken (regex `pg/verify/([a-z0-9]+)/\?status=OK`) → GET verify-OK
  → hit the local callback.
- **HTTP flow proven end-to-end** (curl, shared cookie jar, no redirect
  following — each hop separate): `?add-to-cart=17` → GET /checkout/ (nonce)
  → POST checkout (PWS state 312 / city 322 numeric term IDs,
  `billing_national_id=0012345679` valid mod-11) → 302 order-pay → GET
  `pay_for_order=1` (pay nonce) → POST `woocommerce_pay=1` → 302 receipt URL
  → GET receipt → 302 `sandbox.zarinpal.com/pg/StartPay/…` → simulate payment
  → GET `/wc-api/WC_ZPal/?wc_order=…&Authority=…&Status=OK` → 302
  order-received → order **processing/paid, transaction_id set, stock
  decremented**. NOK path: callback Status=NOK → redirect to checkout, order
  stays pending/unpaid.
- **Gotchas**: `wc_create_order` line_items yield total 0 → must
  set_product/set_quantity + explicit set_subtotal/set_total per item
  (sandbox rejects amount <1000 Rial); plain order-pay URL (without
  `pay_for_order=1`) silently fires `woocommerce_receipt_WC_ZPal` and burns
  an authority into `_zarinpal_authority_history` (page shows order details,
  in-page redirect dies after headers); pay POST's 302 target IS the receipt
  URL — StartPay redirect only appears on the NEXT GET; PWS (prio 20)
  overwrites persian-woocommerce state codes with numeric `state_city` term
  IDs.
- **Tests**: new section 21 (10 checks — gateway sandbox mode, checkout nonce,
  order creation redirect, pay-form redirect chain, StartPay 302, verify
  token extraction, callback → order-received, order paid with transaction
  id, NOK leaves pending, cleanup deletes orders + restores stock).
  145 total across 21 sections. The receipt GET (which fires requestPayment
  against the sandbox) is latency-prone under rapid test runs, so the test
  retries it up to 6× with 8s backoff — without that the check intermittently
  fails.
- **Live note**: ZarinPal plugin files already on live (verified via FTP);
  option `woocommerce_WC_ZPal_settings` absent → gateway disabled. Going
  live requires real merchant code + `sandbox: no` in WP admin — deliberately
  NOT enabled on production.

## 2026-09-04 — Review incentive ask + coupon reward (P1 #8)

New `ASC_Reviews` class in lylyrose-core (`includes/class-reviews.php`,
plugin bumped to v2.1.0 concept — code already at 2.0.0 constant):

- **Request side**: `woocommerce_order_status_completed` → once per order
  (HPOS meta `_asc_review_requested`) schedules Action Scheduler single
  action `asc_send_review_request` at `time() + rand(3,7) * DAY_IN_SECONDS`.
  Handler sends a Persian email (product list + my-account link + coupon
  teaser) via `wp_mail` and an SMS via `PWSMS()->send_sms()` array signature
  (phone normalized through `ASC_OTP::normalize_mobile`), then flips the meta
  to `sent` and adds an order note.
- **Reward side**: `comment_post` (approved review on a product) resolves the
  author email against `wc_get_orders(customer, status=completed)` and requires
  the reviewed product to be IN that order. First hit issues a single-use 10%
  coupon `REVIEW-XXXXXXXX` (individual use, usage limit 1, per-user 1,
  30-day expiry) and stores it under order meta
  `_asc_review_coupon_{product_id}` — one reward per order+product. The code
  is emailed to the reviewer; a note is added to the order. Non-purchaser
  reviews earn nothing. woo-wallet's independent review credit (50k) still
  applies — the two rewards coexist by design.
- **Tests**: section 22 (7 checks — prerequisites, schedule row 3-7 days out,
  request send, coupon issued 10%/single-use, no duplicate on second review,
  non-purchaser no-coupon, cleanup). 152 total across 22 sections, all green.
- **WC 11 API gotchas found**:
  - `WC_Coupon::set_expiry_date()` does not exist — `set_date_expires(int
    timestamp)` is the correct setter (fatal otherwise).
  - woo-wallet hooks `comment_post` with 3 args; test-fired hooks must pass
    `do_action("comment_post", $id, 1, get_comment($id, ARRAY_A))` or WP
    fatals with ArgumentCountError (production WP always passes all 3, so
    this is test-only).
  - The coupon lookup requires the order to be *actually* completed
    (`update_status("completed")`), not just the hook fired on a pending
    order.
- **Live note**: `class-reviews.php` must be shipped to production for the
  feature to exist there; nothing else needed (no options, no tables —
  Action Scheduler + HPOS meta only).

## 2026-09-04 — Fragrance note pyramid (P2 #13)

New `ASC_Fragrance_Notes` class in lylyrose-core
(`includes/class-fragrance-notes.php`, registered in the plugin's
`includes()` after `ASC_Reviews`). The roadmap entry originally scoped this
as ACF repeatable fields; shipped instead as in-house postmeta — no ACF
dependency, same structured data.

- **Admin side**: product-edit meta box «هرم رایحه (نوت‌های عطر)» with three
  textareas — top (نوت آغازین), heart (نوت میانی), base (نوت پایه) — one
  note per line. Saved on `woocommerce_process_product_meta` with nonce +
  `edit_post` cap check; lines are trimmed, empties dropped, and a fully
  emptied layer deletes its meta (`_asc_notes_top/_heart/_base`) rather
  than storing blank rows.
- **Front-end side**: the digikala theme's `single-product.php` calls
  `ASC_Fragrance_Notes::render_pyramid()` directly before the
  «مشاهده توضیحات کامل» link. The class also registers on
  `woocommerce_single_product_summary` (priority 45) for stock-Woo
  templates, but the digikala template never fires that hook — so exactly
  one render path is active. The «هرم رایحه» card (🌿 top / 🌸 heart /
  🪵 base) renders only when at least one layer has notes; with no notes
  saved the card is absent by design (same behavior as the Django port).
- **Data note**: meta keys are underscore-prefixed (`_asc_notes_*`) so they
  are hidden from the custom-fields UI. As of this entry no product has
  notes yet (feature is new) — the live product page correctly shows no
  card; populate via the WP admin meta box.
- **Tests**: `bash docker/run-tests.sh` — 152 tests across 22 sections, all
  green. The suite has no fragrance-notes section yet; this run proves no
  regressions from the new files (the feature itself is code-reviewed and
  live-verified for the empty case). Adding a dedicated section (meta-box
  save round-trip, card render with seeded meta) is open follow-up work.

## 2026-09-05 — Fragrance note pyramid test section (follow-up to P2 #13)

Dedicated `run-tests.sh` **section 23 — Fragrance note pyramid** (P2 #13's
documented open item), replacing the "152 green, no dedicated section"
caveat in the previous entry.

- **Setup**: verifies `class_exists("ASC_Fragrance_Notes")`, then seeds
  `_asc_notes_top/_heart/_base` on product 17 via `wp-load.php` with
  newline-separated Persian notes (برگاموت/یاس، گل محمدی/عود، مشک
  سفید/وانیل). Assertions curl `?p=17` with the standard
  `--max-time 120 --retry 2`.
- **Assertions (10)**: `dk-notes` card present, «هرم رایحه» heading, the
  three front-end layer titles — plain نوت آغازین/نوت میانی/نوت پایه, NOT
  the parenthesized meta-box labels — note text (برگاموت، وانیل), and the
  three layer icons 🌿🌸🪵. Negative test: noteless product 11 renders
  zero `dk-notes` cards. Cleanup `delete_post_meta` on all three keys with
  a pipe-delimited postmeta re-read verification (empty|empty|empty).
- **Pre-run verification**: every assertion string was live-tested
  manually against the seeded product page (and the negative case) before
  the full-suite run, and the seed round-trip confirmed `\n` storage.
- **Result**: all green — 163 tests across 23 sections, 0 failed (was 152/22).

## 2026-09-05 — Multi-step checkout (P3)
- **Goal**: split classic one-page WooCommerce checkout into two explicit
  steps — 1) اطلاعات ارسال (billing/shipping + کد ملی), 2) پرداخت (payment
  info) — for the Digikala convention of reducing address-stage drop-off.
- **Approach**: purely presentational client-side split, **zero
  server-side change**. The `form.checkout` still posts to
  `wc_get_checkout_url()` exactly as before, so nonce / field validation /
  gateway flow are untouched. Entirely in the digikala theme (v1.7.0); no
  `lylyrose-core` change or version bump.
- **Theme** (`woocommerce/checkout/form-checkout.php`): wrapped billing +
  shipping into a `.dk-checkout-step--1` card (with a `ادامه به پرداخت`
  `type="button"`), and `woocommerce_after_checkout_form` +
  `woocommerce_checkout_order_review` into a `.dk-checkout-step--2` card
  (with a `ویرایش اطلاعات ارسال` back link), both inside `.dk-cs2`.
  Verified no theme/core code hooks `woocommerce_after_checkout_form`;
  ZarinPal renders via WC's standard `woocommerce_checkout_payment` (fired
  by `woocommerce_order_review()` in the sidebar, untouched).
  **Layout decision**: order summary + green place-order stay in the
  persistent sticky sidebar, visible on both steps (Digikala-like) — step 2
  is a compact payment note, the submit button is always reachable.
- **No-JS fallback**: step 2 card renders WITHOUT `is-hidden` in the raw
  HTML; JS (new `assets/js/checkout-stepper.js`, enqueued via
  `lylyrose_checkout_stepper_script()` on `is_checkout()`) adds
  `is-hidden` on load and toggles it. JS-less visitors see both cards
  stacked exactly like the old layout.
- **Error/reload**: always start at step 1 on a fresh load. After a failed
  submit WC reloads and shows notices; the most common error (کد ملی) is on
  step 1 and the summary/place-order is always visible, so no stateful
  persistence needed.
- **CSS**: `.dk-cs2 .dk-checkout-step--2.is-hidden`, `.dk-step-next`,
  `.dk-step-next-btn`, `.dk-step-back`/`link` in the checkout section.
- **Theme version**: style.css `Version: 1.6.0` → `1.7.0` (source of truth
  for asset cache-busting via `lylyrose_version()`).
- **Tests**: new section 24 «Multi-step checkout (P3)» — structural checks
  (`.dk-cs2`, both step cards, next/back buttons, no-JS fallback = step 2
  visible in raw HTML, `billing_national_id` still in step 1, sidebar
  `place-order` preserved) + JS markers (`is-hidden`, `step2-ready`).
  Existing checkout suites (section 8 national-ID/redirect, section 20
  abandonment, ZarinPal e2e) remain the real proof the single-form contract
  is intact.

## 2026-09-05 — Notifications center (P3 #17)
- Custom post type `asc_notification` for scalable notifications with author scoping.
- Bell icon in header (logged-in only) with unread-badge and dropdown of 5 most recent.
- Account endpoint `/my-account/notifications/` lists all notifications newest-first with mark-all-read.
- Two automatic producers: (1) order status change to processing/completed/on-hold/cancelled, (2) product review replies (via comment_post hook).
- HPOS-safe: uses `WC_Order` methods, never `update_post_meta` on orders.
- AJAX: `asc_notifications_unread` (count), `asc_notifications_mark_read` (single/all).
- Rewrite flush self-heals on plugin version bump via `maybe_flush_rewrite()`.
- Test section 25 green (13 checks).

Plugin: lylyrose-core v2.1.0; Theme: digikala v1.8.0

## 2026-09-06 — Gift wrap + gift card (P3 #17)

### Gift wrap (custom, lylyrose-core v2.2.0)
- New `ASC_Gift_Wrap` class (`includes/class-gift-wrap.php`).
- `woocommerce_cart_calculate_fees` adds a 50,000 تومان fee when enabled.
  State via `WC()->session` (`asc_gift_wrap`) or cookie fallback.
- Persists as an order fee line item via `woocommerce_checkout_create_order_line_items`
  (WC_Order_Item_Fee CRUD, HPOS-safe).
- Cart checkbox at `woocommerce_before_cart_table`; checkout status at
  `woocommerce_review_order_before_payment` (fires inside the theme's
  persisted sidebar order-review, so it shows in step 2).
- AJAX toggle `asc_gift_wrap_toggle` (guest + logged-in) + no-JS fallback
  via the checkout form POST (`woocommerce_checkout_update_order_meta`).
- Fee rendered in Persian digits via the theme's `lylyrose_to_persian_digits()`
  (though `wc_price` already Persian-izes).
- Theme CSS `assets/css/gift-wrap.css` (checkbox + fee label), enqueued on
  cart/checkout. Theme version 1.8.0 → 1.9.0.

### Gift card (plugin — deferred)
- `pw-woocommerce-gift-cards` downloaded + PHP 8.2 lint OK via
  `install-plugin.sh`. Activation smoke-test falsely failed (`shop=000`)
  due to site-wide latency (requests now ~19-37s), and auto-deactivated;
  the inactive plugin directory was then removed to keep the tree clean.
  Reinstall + activate once the shop latency is fixed. No compatibility
  blocker (code is PHP 8.2 clean, integrates with ti-woocommerce-wishlist).
- Intended config: Persian email template, `GC-XXXXXXXX` codes, custom
  amounts, 12-month expiry.

### Tests
- New section 26 «Gift wrap + gift card (P3 #17)» — cart checkbox, Persian
  label/digits, AJAX toggle, fee row in totals, checkout status + order
  review, order fee line item (50000), soft gift-card plugin check.
- Server-side fee verified directly (session flag → fee 50000, cart total
  ۸۵۰,۰۰۰ for product 17 + wrap).

## 2026-09-26 — Rebrand: residual Aroma Store DB branding -> Lyly Rose

Follow-up to the 2026-08-31 Digikala->Aromaland pass, which fixed **UI strings only**
and never touched the database. The source project it was forked from was itself branded
"Aromaland" (English), "آرومالند" (Persian), with a misspelled mail domain
`@aromalnd.test` ("aromalnd", not "aromaland"). Those survived in the DB and were
inherited by production on 2026-09-24 — so the claim in `CONTINUATION.md` that
"the production site at `lylyrose.ir` is correctly branded" was **wrong**; the live
`<title>` on every page read `Aromaland`, and the public REST API returned
`{"name":"Aromaland"}` at `/wp-json/`.

The code tree was already clean (`lylyrose` theme and `lylyrose-core` had zero hits);
all residue was data.

### What changed

| Table | Rows | Before | After |
|---|---|---|---|
| `wp_options` | `blogname` | `Aromaland` | `Lyly Rose` |
| `wp_options` | `woocommerce_email_from_name` | `آرومالند` | `لیلی رز` |
| `wp_options` | 5 mail-address options | `admin@aromalnd.test` | `info@lylyrose.ir` |
| `wp_options` | `wp_mail_smtp`, `cartflows_ca_email_admin_settings`, `woocommerce_paypal_settings`, `auto_core_update_notified` | embedded old brand | re-serialized |
| `wp_users` | `admin`, `demo_customer` | `*@aromalnd.test` | `info@lylyrose.ir` |
| `wp_usermeta` | 1 (`billing_email`) | `admin@aromalnd.test` | `info@lylyrose.ir` |
| `wp_comments` | 1 | `riya-test@aromalnd.test` | `riya-test@lylyrose.ir` |
| `wp_wpmailsmtp_debug_events` | 55 | old outbound headers | purged (log) |

The `aromalnd.test` domain also appeared in the **WC session cache**, which re-persisted
the old address on the next request after a naive fix. Fixed by flushing the object cache
and rewriting the session row.

### Deliberately NOT changed

- **`wp_wfconfig`** (`wordpressPluginVersions`, `wordpressThemeVersions`,
  `vulnerabilities_plugin`) still contain the strings `aroma-store*`. These are
  Wordfence inventories of plugin/theme *slugs* that existed on the source project.
  Rewriting them would misreport what is actually installed, and they are never rendered
  to a visitor. A full text-column sweep of every varchar/text/blob/JSON column in the
  schema returns these 3 rows as the only remaining `aroma` hits.
- **`wordpress/wp-content/themes/aroma-store{,-old}` and `digikala-v1.0.0`** — inactive
  dev-look-alikes kept for rollback. Not deployed to production (only `lylyrose` ships).
- Login names, table prefix, slugs, and CSS class names (`aroma-store-rtl` etc.) are code
  identifiers, not visitor-facing branding.

### Method

One helper (`rebrand_aromaland.php`) uploaded to the docroot, fetched, then deleted —
no SSH/phpMyAdmin on this host. It boots WordPress to obtain a correctly configured
`$wpdb` (so no credentials are hard-coded into the webroot), writes a
`rebrand-backup.json` of every row it touches **before** modifying anything, and is
idempotent via a `.rebrand_done` guard.

Serialized options are the real hazard: a blind SQL `REPLACE` leaves the `s:<length>:`
prefixes stale and corrupts the blob. The helper substitutes in PHP and re-serializes
with `maybe_serialize()` so lengths are recomputed. User emails are written straight
through `$wpdb->update()` rather than `wp_update_user()` — the latter fires a
"new user" mail and its cache write restored the old address within the same request.

Full pre-change dump at `/tmp/lylyrose-rebrand-backup.sql` (97 tables).

### Verification

- Sweep of all text columns in every table: only the 3 intentional `wfconfig` rows.
- `<title>` on `/`, `/shop/`, `/cart/`, `/checkout/`, `/my-account/` = `Lyly Rose`.
- `/wp-json/` → `{"name":"Lyly Rose"}`.
- Stable across 3 consecutive page loads (session row does not regress).
- **Test suite: 217 passed / 0 failed** (`.test-logs/full-tests-20260926-0858.log`),
  matching the documented baseline. Re-swept after the suite — branding intact, since
  the suite creates and deletes its own users.

### Applied to production (2026-09-26)

Same helper uploaded to `lylyroseir/` over passive FTP, fetched once over HTTPS, then
deleted — all four files (`rebrand_aromaland.php`, `rebrand-backup.json`, `.rebrand_done`,
`verify_prod.php`) confirmed gone afterwards by HTTP 404 *and* an FTP directory listing
that no longer contains them. The pre-change values were pulled back to
`/tmp/lylyrose-prod-rebrand-backup.json` before deletion, so the change is reversible.

Live verification after the change: `/`, `/shop/`, `/cart/`, `/checkout/`, `/my-account/`,
`/feed/` all HTTP 200 with `<title>` = `Lyly Rose`, zero `aroma` hits in the HTML;
`/wp-json/` returns `{"name":"Lyly Rose"}`; `vegacodex.ir` (primary domain) still 200.
In-DB sweep on production: `options` NONE, `users` NONE, `usermeta` 0, `comments` 0, with
the 3 intentional `wfconfig` inventory rows remaining.

One harmless warning appeared in the live run: `Undefined property: wpdb::$woocommerce_sessions`.
WooCommerce's session table is registered by the WC bootstrap, which does not run when
`wp-settings.php` is loaded this way, so that one UPDATE was skipped. Production had 0 rows
to change in that table anyway, and the object cache was flushed afterwards; verified clean.
Worth noting for any future helper that touches WC tables from a bare bootstrap.

## 2026-09-26 — Fresh-volume setup fixed: WP 7.1 overlay + working entrypoint

Closed the long-standing "a fresh-volume reproducible setup still needs correction"
note in CONTINUATION.md. A new clone (`docker compose up`) could not have produced a
working site, for two independent reasons.

**1. WordPress core too old for WooCommerce.** WooCommerce 11.1.0 requires WP >= 7.0
and calls `WP_Block_Templates_Registry`; the shared `aroma_store-wordpress` image
ships 6.5.5 core, where that class does not exist. The local volume had been
hand-upgraded to 7.1 by copying core files out of the upstream container, so the
broken path was invisible locally — a fresh volume seeded 6.5.5 and would fatal on
the first WooCommerce page. Confirmed by control test: 6.5 fresh volume, class absent.

`docker/Dockerfile` now builds **on** `aroma_store-wordpress:latest` and overlays
current WordPress core into `/usr/src/wordpress`, which is what the official
entrypoint seeds from. Building on the shared image rather than the bare 6.5 base
matters: the shared image carries the compiled redis extension and the
`aroma-entrypoint.sh` wp-content ownership fix. The build asserts the class exists
and the version clears the WooCommerce minimum, so a bad core fails the build instead
of a deploy. Extraction uses PHP's ZipArchive — the image has no `unzip`.

**2. The entrypoint never ran the official one.** `aroma-entrypoint.sh` does
`exec "$@"`, which skips the official `docker-entrypoint.sh` entirely. That script is
what copies core out of `/usr/src/wordpress` and renders `wp-config.php`, so a fresh
volume contained only `wp-content` — no core, no config, no site at all. A separate
symptom: the shared image sets ENTRYPOINT but leaves CMD empty, so there was nothing
in `"$@"` to hand off.

`docker/entrypoint.sh` keeps the chown, then execs the official entrypoint with
`apache2-foreground` as the default argument (it dereferences `$1` unguarded).

**One trap worth recording:** the core overlay must preserve
`/usr/src/wordpress/wp-config-docker.php`. It is an image file, not part of the
WordPress zip, so a blanket `rm -rf /usr/src/wordpress/*` deletes it and fresh
volumes then generate no `wp-config.php` — a failure that looks like a credentials
problem, not a missing file.

**Verification**
- Fresh volume: core 7.1.2, `wp-config.php` generated, installer reachable, WooCommerce
  11.1.0 activates, `class_exists(WP_Block_Templates_Registry)` YES.
- Fresh volume pages: `/` 200, `/shop/` 200, `/cart/` 200, `/checkout/` 302, `/wp-admin/` 302,
  zero fatals in the Apache log.
- Control (original 6.5 image, fresh volume): class absent — reproduces the defect.
- Existing stack after the image swap: volume still 7.1, `/` `/shop/` `/cart/`
  `/checkout/` `/my-account/` all 200, `<title>` still `Lyly Rose` (guards the
  documented stale-instance trap on 8080).
- Suite: **217 passed / 0 failed**, two consecutive clean runs.

One false alarm worth noting for the next person: an intermediate run showed 6 wallet
failures. They were residue from abandoned scratch containers, not a regression — the
control run on the original image was 217/0, and re-running after cleaning up the
scratch databases was also 217/0. Anything that connects to the shared `db` service
should use its own throwaway database rather than the live one.
