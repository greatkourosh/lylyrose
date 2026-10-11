# Plugin split plan: lylyrose-core into a generic plugin

Status: **not started. Do not execute until a second site needs the generic code.**
Source: class inventory of `wordpress/wp-content/plugins/lylyrose-core`, 2026-10-11.
The brand-hit counts below are grep counts for `lyly|perfume|finder|stives|عطر`. They are a rough signal, not a verified classification.

## Why split at all

The theme and plugin are reusable only if a second store can run the generic parts without the perfume data. Until a second site exists, the split is cost with no payoff.

## Classification

**Stays in `lylyrose-core` (store-specific)**

| File | Brand hits | Reason |
|---|---|---|
| `class-perfume-finder.php` | 80 | Scored recommender over this catalogue |
| `class-finder-tiers.php` | 31 | Finder price tiers |
| `class-finder-weights.php` | 25 | Finder scoring editor |
| `class-fragrance-notes.php` | 11 | Perfume note pyramid |
| `class-needs-finder.php` | 37 | Skin/hair quiz tied to this catalogue |
| `class-store-pages.php` | 20 | This store's about/contact copy |
| `lylyrose-core.php` | 75 | Plugin header and bootstrap |

**Candidates to move to a neutral plugin (low brand hits)**

| File | Brand hits | Notes |
|---|---|---|
| `class-flash-sales.php` | 0 | Carousel and offers |
| `class-coupons.php` | 0 | |
| `class-frequently-bought.php` | 0 | |
| `class-gift-cards.php` | 0 | |
| `class-images.php` | 0 | |
| `class-avif.php` | 1 | Check the one hit |
| `class-fuzzy-search.php` | 1 | Check the one hit |
| `class-gift-wrap.php` | 4 | Check the hits |
| `class-national-id.php` | 6 | Iranian checkout field, generic for Iran |

**Need a read before deciding (mixed)**

| File | Brand hits | Question |
|---|---|---|
| `class-palette.php` | 26 | Mechanism is generic; default colours and presets are brand |
| `class-emails.php` | 0 | "Branded" emails: check whether the brand is a setting or hard-coded |
| `class-reviews.php` | 11 | Incentive logic generic; coupon text may be brand |
| `class-stock-notifier.php` | 9 | Probably generic; check copy |
| `class-cart-abandonment.php` | 3 | Depends on PWSMS, not brand |
| `class-otp.php` | 14 | Generic flow; check copy and the Digikala-style wording |
| `class-notifications.php` | 15 | Check copy |
| `class-reports.php` | 17 | Generic reporting; check labels |
| `class-instagram.php` | 14 | Depends on whose handle is hard-coded |
| `class-product-code.php` | 4 | Generic SKU codes; check the URL prefix |

## Step 1 results (read 2026-10-11)

Grep hits were mostly noise. Real findings:

- **Every file uses the text domain `'lylyrose-core'`.** This is the biggest blocker, not the brand copy. Moving a class to a new plugin without keeping the text domain breaks translations. Keep `'lylyrose-core'` as the domain in the neutral plugin, or load the old `.mo` files under it.
- **Only comments, no brand copy (move freely):** `class-avif.php` (`@package` line only), `class-fuzzy-search.php` (`@package` only), `class-gift-wrap.php`, `class-national-id.php`, `class-coupons.php` (comment), `class-frequently-bought.php` (comment), `class-images.php` (comment), `class-product-code.php` ("Digikala-style" comments only; the `asc_rewrite_version` option stays).
- **Hard-coded «لیلی رز» in user-facing text (must become a setting before a second site can use the plugin):**
  - `class-cart-abandonment.php` (SMS body)
  - `class-reviews.php` (email body, subject uses `get_bloginfo`)
  - `class-stock-notifier.php` (SMS body)
  - `class-reports.php` (menu title «گزارش فروش لیلی رز» and CSV filename prefix `lylyrose-sales-`)
  - `class-instagram.php` (alt and aria text «اینستاگرام لیلی رز»; the handle itself is already a setting, so the mechanism is generic)
- **Mechanism generic, default data brand:**
  - `class-palette.php`: the `lylyrose` preset is the default; the cookie `lylyrose_palette` and the `lylyrose-style` handle are fixed names that other code depends on. Keep them as they are, and make the preset list data, not code.
  - Palette editor location (decided 2026-10-11): a Settings page (`Settings → پالت رنگی`, slug `asc-palette`) in the plugin, not the Customizer. The Customizer holds only hero photos, and a theme switch would orphan palette settings there. The page moves with the plugin on split.
  - `class-otp.php`: copy is neutral Persian; the "Digikala-style" wording is in comments only. Generic.
- **Store-specific, stays:** the 4 finder classes, `class-needs-finder.php`, `class-store-pages.php`, `class-fragrance-notes.php`, `lylyrose-core.php` (as already listed).

Revised move list: the 0-hit and comment-only group above is ready now. The brand-copy group needs a settings change first, and each change is a deploy in its own right.

## Step 1 re-check (2026-10-11, after `fb91b619`)

- **Already filtered, not hard-coded:** SMS brand in `class-cart-abandonment.php`, `class-reviews.php`, `class-stock-notifier.php` now goes through `apply_filters('asc_sms_brand', 'لیلی رز')`. The default equals the old text, so output is unchanged. The list above is out of date for these three.
- **Reports:** menu title and CSV prefix are filtered (`asc_reports_csv_prefix`, `61451ba5`). Check the menu title is also filtered before calling it done.
- **Still hard-coded, `class-instagram.php`:** `aria-label` and `alt` use «اینستاگرام لیلی رز» and «پست اینستاگرام لیلی رز» (lines 59, 70, 83). The settings label (line 7 comment, 83) is brand too. Not yet filtered.
- **Clean, no brand copy:** `class-emails.php`, `class-notifications.php`, `class-otp.php` (Digikala wording is in a comment only).
- **`class-product-code.php`:** Digikala wording in comments only (grep, not yet read in full).
- **`class-palette.php`:** brand only in the `lylyrose` preset and the fixed cookie and handle names. Those stay.

**Checked 2026-10-11, code-side brand-copy work is done:** `class-instagram.php` alt/aria/menu strings go through `asc_instagram_*` filters (`1dab3b72`), and the reports menu title goes through `asc_reports_menu_title`. Still hard-coded and admin-only: the Instagram settings page `<h1>` (`class-instagram.php:129`). Not storefront copy, so it does not block the split.

## Rules for the split (when it happens)

0. Keep the text domain `'lylyrose-core'` (see Step 1). Changing it silently drops Persian translations.

1. Keep every option key, post meta key, and hook name exactly as is. Live data depends on them. Renaming silently breaks live settings.
2. Keep the plugin class names unless renaming is needed. Renaming is a separate step.
3. Load order: the neutral plugin must load before `lylyrose-core`, because `lylyrose-core` depends on its classes.
4. Move one class per commit. Run the local suite after each move.
5. Deploy the neutral plugin and the updated `lylyrose-core` together, in one step. Then verify on live with `docker/diff-host.py` (see the "green suite is not deployed" memory).

## Steps

1. Read each "need a read" file's copy, and each low-hit file's brand hits, and confirm the classification. Record the result in this doc.
2. Pick a neutral name with the owner (`asc-core` is the working name in CONTINUATION.md). Do not invent one without asking. **Done 2026-10-11: owner chose `kstore`.**
3. Create the neutral plugin skeleton with an empty bootstrap. Confirm it activates on local with no effect.
4. Move the 0-hit classes first, then the low-hit ones, one commit per class.
   - **2026-10-11: `class-coupons.php` moved to `kstore/includes/` (`c2f9eddf`).** Local suite 356/0 with the pin at 24 in `docker/run-tests.sh`. Not deployed.
     - `kstore` is active on local. Deploy it together with `lylyrose-core` (the require fatals if `kstore` is missing on live).
     - `aroma_store/docker/run-tests.sh:1777` still pins 21, not 25. Its pin is already out of step with this repo, so reconcile it when that repo is next committed.
     - Plugin dirs are `www-data`-owned. Use `docker exec -u root` for writes. Bind mount means the host files change too.
     - Suite showed 354/2 once on an earlier tree (callback redirect and order state), then 356/0 on rerun. Not diagnosed; treat as flaky until it recurs.
   - **2026-10-11: `class-frequently-bought.php` moved to `kstore/includes/`.** Required from `lylyrose-core.php` by `WP_PLUGIN_DIR` path, same as coupons. Pin in `docker/run-tests.sh` is now 23. Local suite 356/0. Not deployed; deploy with `kstore` in the same step.
   - **2026-10-11: `class-flash-sales.php` and `class-gift-cards.php` moved to `kstore/includes/`.** Required from `lylyrose-core.php` by `WP_PLUGIN_DIR` path. Both were self-contained (no plugin path refs). Class-count pin in `docker/run-tests.sh` is now 19. Local suite 359/0. Not deployed; deploy with `kstore` and `lylyrose-core` in one step. Plugin dirs need `docker exec -u root` to write.
5. Run the local suite after each move. Expect the same pass count as before the split.
6. Deploy both plugins in one step, then check live with `docker/diff-host.py` and a page render.
7. Update CONTINUATION.md with the new layout and date.

## Ceiling

This plan assumes WordPress keeps loading both plugins at the same priority. If the neutral plugin is missed by the load order, the store-specific classes fatal on a missing class. Check the require graph before deploying (see "plugin requires files it never tracked" in memory).
