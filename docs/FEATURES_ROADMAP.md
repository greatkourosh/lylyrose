# Features Roadmap — Prioritized Recommendations

> **Upstream:** this project is **downstream** of `aroma_store`, which is the source of
> truth. Develop and test features upstream first; mirror here only when needed.
> See [UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md).

> **Feature requests route upstream.** This store is downstream of `aroma_store`,
> which is the source of truth and is always ahead on features. A feature requested
> here is referred to `aroma_store`, built and tested there, and mirrored back only
> once that suite is green. See [FEATURE_REQUEST_POLICY.md](FEATURE_REQUEST_POLICY.md).


Date: 2026-08-29 · Branch: master · Plugin count: 19 active locally (25 shipped
including 6 shipped-but-inactive: Wordfence, Rank Math, WP Super Cache, Gateland,
Persian shipping, Limit Login Attempts — corrected 2026-09-26)

Gap analysis of the current stack (WooCommerce, lylyrose-core 1.1.0, Persian/Rial stack,
Dokan, ZarinPal/Gateland, SMS, Rank Math, Torob feed, Wordfence, Redis, Super Cache,
Autoptimize, UpdraftPlus, Goftino, Clarity, Site Kit) with concrete next features,
ordered by revenue/UX impact vs effort.

Current-state facts this list is built on:
- Guest checkout ON, but **account registration OFF** (`woocommerce_enable_myaccount_registration = no`)
- No image optimization (no WebP/AVIF conversion) — perfume store is image-heavy
- Shop/archive pages have **no faceted filters** (no brand/gender/price/attribute sidebar)
- Search is a plain `?s=` title match — no SKU/code search, no fuzzy matching
- No coupons marketing surfaces, no cart-abandonment capture, no loyalty points
- No transactional **email** templates beyond WP defaults; SMS exists
- Missing pages: تماس با ما, درباره ما, پیگیری سفارش, FAQ

---

## P0 — Do first (direct revenue / checkout UX)

### 1. ✅ DONE 2026-09-02 — Mobile OTP login & registration (ورود با شماره موبایل)
- Built in-house instead of a third-party plugin: lylyrose-core `ASC_OTP` — two AJAX
  endpoints (`asc_otp_request` / `asc_otp_verify`), 6-digit code hashed with
  `wp_hash_password` and stored as a 120s transient, resend cooldown 60s, max 5 attempts,
  5 requests/mobile/hour. Unknown mobile auto-registers a customer with `billing_phone`
  set; known mobile logs straight in. Delivery via `PWSMS()->send_sms()` — real gateway
  when configured, Logger sink (`wc-logs/pwsms.log`) otherwise.
- Theme v1.6.0: Digikala-style two-tab login (ورود سریع / ورود با گذرواژه) in
  `form-login.php`, `assets/js/otp-login.js` handles the flow, my-account registration
  option enabled. Persian-digit/+98/0098 mobiles all normalize.
- **Acceptance met**: test suite green incl. section 19 (register→SMS→verify→customer,
  re-login, wrong code, cooldown, rate limit, bad nonce, Persian digits, cleanup).
- Note for live deploy: the SMS gateway (Kavenegar etc.) still needs real credentials in
  پیامک settings before OTP works in production; until then codes land in the logger.

### 2. ✅ DONE 2026-09-01 — Image optimization: WebP delivery (see DEVELOPMENT_LOG)
- Investigation changed the plan: the media library is already 100% WebP from the
  Digikala import (no image over 40 KB), so Converter for Media was **not** installed —
  no conversion gain, and it would have added ~100 FTP files + .htaccess rewrites on the
  BitNinja host. Instead lylyrose-core v1.6.0 `ASC_Images` converts **future**
  JPEG/PNG uploads to WebP via WP core's image editor (2560px cap, original kept).
- **Acceptance met**: test suite green incl. new section 13 (sideloaded JPEG/PNG land as
  WebP, all sizes WebP, library 0 non-WebP); LCP product image already ships WebP with
  `fetchpriority="high"` + srcset.

### 3. ✅ DONE 2026-09-01 — Shop faceted filters (see DEVELOPMENT_LOG, entries "Faceted shop filters" + "Gender/concentration/volume facets")
- Shipped in-theme (not widgets): `archive-product.php` renders the Digikala
  filter rail — availability/discount toggles, برند checkboxes, جنسیت/غلظت/حجم
  attribute facets, price range form, sort dropdown, result count, auto-submit
  on checkbox change. URL-driven GET params (shareable). `wp_robots` filter
  sets noindex,follow on any filtered permutation (verified: filtered page
  `noindex, follow`, clean shop indexable). Re-verified live 2026-09-05:
  `dk_brands[]=27&[]=28` → 7 کالا (شنل+دیور only), price 1-2M → ۱ کالا,
  `in_stock=1` → ۱۲۹, `discount=1` → ۸۶.

### 4. ✅ DONE 2026-09-03 — Cart abandonment recovery (بازیابی سبد خرید رهاشده)
- woo-cart-abandonment-recovery v2.1.3 (CartFlows) installed: tracks checkout form
  (email + phone captured via JS before order completes), stores carts in
  `wp_cartflows_ca_cart_abandonment`, cron (`every_fifteen_minutes`, 30-min cut-off)
  flips stale carts to `abandoned`, schedules follow-up emails.
- Three English sample templates replaced with Persian Aromaland ones (1h یادآوری /
  6h راهنمای تکمیل خرید / 1d پیشنهاد ویژه بازگشت), all activated; from-name لیلی رز;
  Persian GDPR notice enabled so phone capture is consent-labelled.
- lylyrose-core `ASC_Cart_Abandonment` hooks `wcf_ca_process_abandoned_order` and
  sends the Persian SMS reminder via `PWSMS()->send_sms()` — normalizes the captured
  phone through `ASC_OTP::normalize_mobile`, skips unsubscribed/non-abandoned carts,
  includes the tokenized recovery link (`wcf_ac_token`) and any generated coupon.
- **Acceptance met**: test suite green incl. section 20 (tracking script + nonce +
  `#billing_phone` on checkout, capture row with phone, cron flip to abandoned, SMS
  delivered to sink with recovery token, follow-up emails scheduled, unsubscribed cart
  gets no SMS, cleanup). Same live caveat as OTP: real SMS gateway credentials needed.

### 5. ✅ DONE 2026-09-03 — ZarinPal sandbox e2e payment test (تست پرداخت واقعی) (see DEVELOPMENT_LOG)
- **Why**: Gateway is installed but a full sandbox purchase→IPN→order-completed flow
  has not been proven. Payment is the one flow that must not fail silently.
- **How**: ZarinPal sandbox merchant code, test purchase, verify order status transitions,
  SMS on `processing`, stock decrement, Gateland fallback configured.
- **Effort**: Low. **Acceptance**: Scripted sandbox order completes; refund path known.
- **What was done**: gateway configured (sandbox=yes, dummy UUID merchant accepted by
  sandbox); full HTTP flow proven headlessly — checkout (Persian state/city/national-ID
  validation) → order-pay → pay POST → receipt → 302 StartPay → sandbox payment simulated
  via verify-token GET → callback `/wc-api/WC_ZPal` → order processing/paid with
  transaction id, stock decremented; NOK path leaves order pending. Encoded as test
  section 21 (10 checks). Live needs real merchant code + `sandbox: no` in WP admin.

---

## P1 — High value, next sprint

### 6. ✅ DONE 2026-09-01 — Store pages: تماس با ما / درباره ما / پیگیری سفارش / FAQ (see DEVELOPMENT_LOG)
- **Why**: Trust pages measurably lift checkout conversion for Iranian shoppers;
  order-tracking page reduces support load (Goftino handles chat, not async status).
- **How**: 4 pages + Digikala-style templates; پیگیری سفارش uses order-lookup form
  (order no. + email/phone — no login required).
- **Effort**: Low-medium. **Acceptance**: Pages live, linked in footer, RTL-clean.

### 7. ✅ DONE 2026-09-01 — Search upgrade: search by product code / SKU (see DEVELOPMENT_LOG)
- `ASC_Product_Code::search_redirect()` on `pre_get_posts`: a site search of
  `sku-<digits>`, `sku <digits>` or bare digits resolving to a product 301s to
  its canonical URL; non-numeric and unmatched searches fall through to normal
  results. Re-verified live 2026-09-05: `?s=sku-5`/`?s=5` → Chanel #5,
  `?s=17` → ID-code product, miss → 200 results page.

### 8. ✅ DONE 2026-09-04 — Reviews incentive (تشویق به ثبت نظر) (see DEVELOPMENT_LOG)
- lylyrose-core v2.1.0 `ASC_Reviews`: on `woocommerce_order_status_completed` schedules
  a Persian review-request email + SMS 3–7 days out via Action Scheduler
  (`asc_send_review_request`, once per order via `_asc_review_requested` meta). When the
  customer then posts an **approved** review on a product they actually purchased (email
  matched against a completed order containing that product), a single-use 10% coupon
  `REVIEW-XXXXXXXX` (30-day expiry, individual use) is generated and emailed to them —
  once per order+product via `_asc_review_coupon_{product_id}` meta. Non-purchaser
  reviews earn nothing.
- **Acceptance met**: automated request fires once per order; test section 22 (7 checks:
  prerequisites, schedule row, send, coupon issue + 10%/single-use, no duplicate on
  second review, non-purchaser no-coupon, cleanup).

### 9. ✅ DONE 2026-09-01 — Order-status email templates in Persian (ایمیل‌های فارسی)
- **Why**: SMS exists, but emails still ship with English WP defaults; wp-mail-smtp is
  active and unused to its potential.
- **How**: WooCommerce email settings → translate subject/body templates, Digikala-style
  HTML email template in theme (red accent, IRANYekan).
- **Effort**: Medium. **Acceptance**: New-order/processing/completed emails render RTL Persian.

### 10. ✅ DONE 2026-09-01 — Discount/coupon strategy surfaces (see DEVELOPMENT_LOG)
- Coupon field now always visible in the cart payment summary (Digikala-style, teal
  اعمال button; posts to WC core with the woocommerce-cart nonce) — hidden once a
  discount applies. Campaign banner strip (`dk-campaign`) driven by the
  `asc_campaign_banner` plugin option renders site-wide under the announcement bar.
- WELCOME10 seeded (10% off, individual use, 1×/user). **Acceptance met**: real HTTP
  POST applies it (discount row + Persian success notice); invalid code rejected;
  tests section 14.

---

## P2 — Scale & retention

### 11. ✅ DONE 2026-09-02 — Loyalty points / wallet (see DEVELOPMENT_LOG)
- Shipped as woo-wallet («کیف پول برای ووکامرس» / TeraWallet, **v1.6.14 at the
  time, updated to v1.7.0 on 2026-09-26** — the "v2.4.x" previously written here was
  wrong; fa_IR translation from translate.wordpress.org, installed and loading):
  2% cart
  cashback on completed orders capped at 5M Toman, min cart 500k; topup product «شارژ کیف
  پول لیلی رز» with 100k–20M limits; partial payment auto-deduct (fee = balance, remainder
  via gateway); product-review credit 50k once per product/user; transfers + gateway charge
  off; clawback on refund on. Theme account nav links «کیف پول». Tests section 18.

### 12. ✅ DONE 2026-09-02 — Related-products "Frequently bought together" (see DEVELOPMENT_LOG)
- Shipped as lylyrose-core v1.8.0 `ASC_Frequently_Bought`: co-purchase query over
  the last 500 completed orders (`wc_get_orders`, HPOS-safe), scored by co-occurrence,
  top 8 partners, 1-day transient cache invalidated inline when an order completes.
- Theme v1.4.0 renders the Digikala-style «اکثراً با هم خریداری شده‌اند» section on the
  single product page (pair row + «خرید هر دو» button + partner cards); renders nothing
  until real order data exists. Tests section 15.

### 13. ✅ DONE 2026-09-04 — Fragrance note pyramid (see DEVELOPMENT_LOG)
- Shipped as in-house postmeta, NOT ACF (pivot: same structured data, zero plugin
  dependency). lylyrose-core `ASC_Fragrance_Notes`: product-edit meta box with
  three layers — top (نوت آغازین), heart (نوت میانی), base (نوت پایه) — one note
  per line, stored as `_asc_notes_top/_heart/_base` (hidden keys, empty layer =
  meta deleted).
- Theme `single-product.php` renders the «هرم رایحه» card (🌿🌸🪵) before the
  full-description link; card hidden when no layer has notes. Test section 23
  (dedicated): seeds meta on product 17, asserts card + layer titles/icons/note
  text on the front end, negative test on a noteless product, cleanup verified.

### 14. ✅ DONE 2026-09-02 — Instagram feed / social proof strip (see DEVELOPMENT_LOG)
- lylyrose-core v1.9.0 `ASC_Instagram`: `asc_instagram` option (handle + up to 6
  image/post URL rows) with a Persian Settings page; theme v1.5.0 renders a lazy-loaded
  6-up grid («اینستاگرام ما») on the homepage between brands and magazine.
- No Instagram API (unreliable from Iran, token the owner can't refresh) — the grid takes
  plain image URLs from any host. Silent until configured. Tests section 16.

### 15. ✅ DONE 2026-09-02 — Advanced reporting (see DEVELOPMENT_LOG)
- lylyrose-core v2.0.0 `ASC_Reports`: «گزارش فروش» admin page — date-range stats
  (orders, revenue, items, avg order), top-10 products, and CSV export with Persian
  headers + UTF-8 BOM for Excel. Export link uses a day-scoped signed token (session
  tokens rotate per request under the security stack, breaking nonces). Tests section 17.

### 16. ✅ DONE 2026-09-05 — Multi-step checkout (تسویه‌حال چندمرحله‌ای) (see DEVELOPMENT_LOG)
- digikala theme v1.7.0 splits the classic checkout into two presentational steps
  (اطلاعات ارسال → پرداخت) inside the single WooCommerce form — pure client-side,
  no server change. Order summary + place-order stay in the persistent sidebar.
  No-JS fallback stacks both cards. Tests section 24.

### 17. ✅ DONE 2026-09-05 — Notifications center (اعلان‌ها) (see DEVELOPMENT_LOG)
- lylyrose-core v2.1.0 `ASC_Notifications`: custom post type `asc_notification`
  (author-scoped), bell icon + unread-badge + dropdown in header (logged-in only),
  `/my-account/notifications/` account endpoint via `add_rewrite_endpoint` +
  `woocommerce_get_query_vars` filter. Producers: order status change
  (processing/completed/on-hold/cancelled) + product review replies. AJAX
  `asc_notifications_unread` / `mark_read`. Rewrite flush self-heals on version bump.
  digikala theme v1.8.0 bell + account nav + CSS. Tests section 25.

### 18. ✅ DONE 2026-09-06 — Gift wrap / gift card (کارتابل هدیه) (see DEVELOPMENT_LOG)
- Gift wrap: lylyrose-core v2.2.0 `ASC_Gift_Wrap` — cart fee (50,000 تومان) via
  `woocommerce_cart_calculate_fees`, persisted as order fee line item (HPOS-safe),
  checkbox on cart + status in checkout, AJAX `asc_gift_wrap_toggle` + no-JS fallback.
  digikala theme v1.9.0 CSS. Tests section 26.
- Gift card: **deferred**. `pw-woocommerce-gift-cards` is PHP 8.2-compatible
  (lint OK) and integrates with `ti-woocommerce-wishlist`, but the shop's
  ~27-39s page latency made the installer's smoke-test false-negative (auto-
  deactivate). Reinstall + activate once the shop latency is fixed; then
  Persian email template + `GC-XXXX` codes.

---

## P3 — Later / optional

- **Back-in-stock notifier** (موجود شد به من خبر بده): SMS on restock per product.
- **Back-in-stock notifier** (موجود شد به من خبر بده): SMS on restock per product.
- **Vendors onboarding docs**: if Dokan vendors become real, seller FAQ + commission policy.
- **AMP / PWA**: low priority; cache stack already fast, PWA only if mobile-app feel needed.

---

## Explicitly NOT recommended

- **Second cache plugin / page builder** — stack is complete; conflicts guaranteed.
- **Jetpack / Mailchimp / Google Feed** — blocked or pointless for Iran traffic.
- **Drag-drop theme builders** — the custom `digikala` theme is the product's identity.

## Process for adding any feature

**This store is downstream — features do not start here.** The full procedure, including
the store-specific exceptions, is in
[FEATURE_REQUEST_POLICY.md](FEATURE_REQUEST_POLICY.md).

1. **Refer the request to `aroma_store`** (upstream, source of truth). Nothing is
   installed or built in this repo yet.
2. Implement in `aroma_store` on `master`; add test cases to that repo's
   `docker/run-tests.sh` when the feature has an HTTP surface.
3. Upstream suite must be green. **A red upstream test is never mirrored.**
4. Mirror the change here with the rename map
   ([UPSTREAM_RELATIONSHIP.md](UPSTREAM_RELATIONSHIP.md)):
   `aroma-store-core` → `lylyrose-core`, `digikala` → `lylyrose`, آرومالند → لیلی رز,
   ports 8010 → 8080. The `ASC_` prefix is unchanged.
5. `bash docker/run-tests.sh` in this repo (localhost:8080) must stay green — currently
   **217 checks across 26 sections**.
6. Update `docs/DEVELOPMENT_LOG.md` and commit on `master`.

Store-specific changes (branding, a campaign page, host config) skip steps 1–3 and are
built here directly — but say so explicitly rather than quietly building shared code
here.
