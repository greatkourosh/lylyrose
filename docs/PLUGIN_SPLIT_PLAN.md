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

## Rules for the split (when it happens)

1. Keep every option key, post meta key, and hook name exactly as is. Live data depends on them. Renaming silently breaks live settings.
2. Keep the plugin class names unless renaming is needed. Renaming is a separate step.
3. Load order: the neutral plugin must load before `lylyrose-core`, because `lylyrose-core` depends on its classes.
4. Move one class per commit. Run the local suite after each move.
5. Deploy the neutral plugin and the updated `lylyrose-core` together, in one step. Then verify on live with `docker/diff-host.py` (see the "green suite is not deployed" memory).

## Steps

1. Read each "need a read" file's copy, and each low-hit file's brand hits, and confirm the classification. Record the result in this doc.
2. Pick a neutral name with the owner (`asc-core` is the working name in CONTINUATION.md). Do not invent one without asking.
3. Create the neutral plugin skeleton with an empty bootstrap. Confirm it activates on local with no effect.
4. Move the 0-hit classes first, then the low-hit ones, one commit per class.
5. Run the local suite after each move. Expect the same pass count as before the split.
6. Deploy both plugins in one step, then check live with `docker/diff-host.py` and a page render.
7. Update CONTINUATION.md with the new layout and date.

## Ceiling

This plan assumes WordPress keeps loading both plugins at the same priority. If the neutral plugin is missed by the load order, the store-specific classes fatal on a missing class. Check the require graph before deploying (see "plugin requires files it never tracked" in memory).
