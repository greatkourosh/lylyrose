# `/perfume/` landing page — design and implementation plan

**Status:** plan only. The page exists as a pre-plan WIP, checkpointed at
**`2be8cefa`**; the design pass described here has **not** started.
**Date:** 2026-10-23

> **2026-10-25 — two defects fixed, plan otherwise unchanged.** The page defined
> its own colour literals (`--pf-rose: #ad5568`, blush `#fdf1f2`, mint `#e8f3f2`),
> so the buy pill and panels ignored the active palette and stayed pink on all
> four presets — light panels on Night's dark ground. Those six tokens now derive
> from `--dk-*`, and 40 more literals in the same file (scrims, borders, badges,
> checkbox, stars) were re-pointed at tokens. Separately, the third quick-entry
> card `6a3100b9` deleted is restored, and the row is `1.38fr 1fr 1fr` — the comp's
> 312 px quiz card against 225 px others. See §4's token table and the log entry.
**Page:** WordPress page **6544**, slug `perfume`, title «عطر و ادکلن» —
<https://lylyrose.local/perfume/>
**Comp:** [attached reference](assets/perfume-landing-comp.jpg) (915×1280 screenshot,
≈1,200 px design width)

> **Policy.** This is a store-specific landing page: it does not exist in
> `aroma_store`'s `digikala` theme, and it introduces no `ASC_` class. It is
> therefore downstream-only. If the page is ever promoted to a shared template,
> it goes upstream first — see [FEATURE_REQUEST_POLICY.md](FEATURE_REQUEST_POLICY.md).

## 1. Goal

Rebuild the **presentation** of `/perfume/` to match the comp — full-bleed hero,
three quick-entry cards, 7-tile scent-family strip, filter rail + 4×2 product
grid, guide banner, best-seller rail — while keeping the real WooCommerce
catalogue and the working filter / add-to-cart logic.

The page already looks approximately like the comp, so this is a **design-fidelity
and structure pass**, not a new build. Keep the data layer; replace the
presentation and fix the broken assets.

## 2. Current state

### Files

| File | State | Notes |
| --- | --- | --- |
| [`page-perfume.php`](../wordpress/wp-content/themes/lylyrose/page-perfume.php) | WIP, checkpointed | 682 lines; hero, quick cards, families, facets, grid, guide, best-seller rail |
| [`assets/css/perfume-landing.css`](../wordpress/wp-content/themes/lylyrose/assets/css/perfume-landing.css) | WIP, checkpointed | 621 lines, scoped under `.pf-` |
| [`assets/js/perfume-landing.js`](../wordpress/wp-content/themes/lylyrose/assets/js/perfume-landing.js) | WIP, checkpointed | 138 lines; AJAX add-to-cart, brand search, rail, sort |
| [`functions.php`](../wordpress/wp-content/themes/lylyrose/functions.php) | WIP, checkpointed | +28 lines: `lylyrose_perfume_landing_assets()`, page-only enqueue |
| [`style.css`](../wordpress/wp-content/themes/lylyrose/style.css) | WIP, checkpointed | `Version: 1.27.10` → **1.27.11** |

The page is served by slug resolution (`page-perfume.php`); no page template meta
is set. The five files above are the whole checkpoint commit **`2be8cefa`**.

### What works

- `/perfume/` returns **200**.
- Facet parsing, shareable GET URLs (`pf_gender`, `pf_family`, `pf_occasion`,
  `pf_longevity`, `pf_brand`, `pf_ml`, `pf_stock`, `pf_sale`, `pf_sort`, `paged`).
- Volume parsing from titles, sorting, pagination, empty state.
- AJAX add-to-cart from the card button (`?wc-ajax=add_to_cart` verified).
- Brand search, rail arrows, toast, cart-badge bump.

### What is wrong

- **Hero:** a product packshot inside a white box, copy on the right, h1 30 px —
  the comp is a full-bleed floral/bottle photo with the copy on the left.
- **Quick cards:** reverse RTL order (the quiz card is on the left; comp has it on
  the right) and no photo strips.
- **Family tiles:** all six render a **bare first letter** instead of an image.
  `lylyrose_term_image()` only falls back to a product image for `product_cat`
  terms; attribute terms have no `thumbnail_id`.
- **Layout:** guide and best-seller sections are full-width below the 2-column
  layout; the comp keeps them inside the content column while the filter rail
  spans alongside.
- **Sidebar:** shows counts, renders all **51** brand checkboxes open (49 of them
  used by perfumes), has an
  «اعمال فیلترها» button; the comp has no counts, four brands + search, and an
  outlined «پاک کردن فیلترها» at the bottom.
- **Grid:** 12 products (3 rows) vs the comp's 8 (2 rows); no brand line under the
  title; static five stars; smaller type than the comp.
- **Guide:** no photo, wrong four badges, full-width.
- **Width:** site `.dk-container` is **1,332 px**; the comp is ≈1,220 px.
- **Mobile:** filters are ordered *after* the products and there is no filter
  trigger; the page has no mobile filter drawer.

## 3. Data reality

| Fact | Value |
| --- | --- |
| Published perfumes in `perfume` (cat 23) | **110** |
| Fragrance families | **6** — آکواتیک، ادویه، گل، چوب، میوه، شرقی |
| Brands | **49** used by the 110 perfumes (108 brand assignments; 2 products have no brand). Top brands: 12, 11, 8, 7, 6, 6, 5 … |
| Genders | **3** — زنانه، مردانه، یونیسکس |
| Occasions | **6** — روزمره، محل کار، مهمانی، مراسم رسمی، ورزشی، سفر |
| Longevity | **3** — متوسط، بلند، خیلی بلند |
| Product thumbnails | **110 / 110** |
| Volumes parsed from titles | 100 ml ×59, 50 ml ×16, 30 ml ×8, plus a 25–125 ml tail |
| `pa_volume` attribute | none — the size only exists in the title |

**The comp's facet labels do not exist in the catalogue.** Its «نوع رایحه» lists
خنک/گرم/شیرین/تلخ/گلی/مرکباتی/چوبی and its volume filter lists 20/50/100 ml.
Those are a different taxonomy. The plan below keeps the real six families and
real occasion/longevity terms; adding the comp's taxonomy would be a separate
data task (new attribute + tagging 110 products) and is **not** recommended.

## 4. Target design

### Tokens

| Token | Value | Use |
| --- | --- | --- |
| Teal | `--dk-red` `#064048` | headings, primary CTA, active nav |
| Rose (CTA) | `var(--dk-red)` | buy pill, discount badge, active filters |
| Blush | `color-mix(--dk-red 7%, --dk-bg)` | product media, soft panels |
| Mint | `color-mix(--dk-red 13%, --dk-bg)` | quiz card, guide banner |
| Ink / muted / border | `--dk-ink` / `--dk-muted` / `--dk-border` | existing tokens |
| Radius | 12 px cards, 8 px controls, 999 px pills | existing |

**Superseded 2026-10-25.** This table originally fixed Rose at `#ad5568`, Blush at
`#fdf1f2` and Mint at `#e8f3f2` as literals. That graded contrast against one
palette only: on ivory, navy and night the page kept teal-rose pills on a white
page, and on Night the three pastel panels stayed light against `#12151c`. Every
token here now derives from the active palette, so the page follows all four
presets — see the status note at the top and the log entry for the same date.

| Container | **≈1,220 px**, page-scoped | comp ≈1,200 at 1440; global stays 1,332 |
| Type | IRANYekanX; h1 38/800, section 20/700, card title 14/700, body 12.5–14 | |

### Sections

| # | Section | Spec |
| --- | --- | --- |
| 1 | **Hero** | Full-bleed photo: pale flowers left, Miss Dior among roses right. Copy block left, h1 «دنیای عطرها، به سلیقه تو», one line, teal pill «مشاهده همه عطرها ←». ~264 px desktop. |
| 2 | **Quick cards** | 3 columns, RTL: **quiz first (right, mint, wide)**, then «می‌خوام خودم بگردم», then «عطر رو می‌شناسم». First two: photo strip at the top, line icon, title, 1–2 line description, `‹`. Quiz: bottle image right + dark pill CTA. |
| 3 | **Families** | 7 tiles: گلی، مرکباتی، میوه‌ای، چوبی، گرم و چوبی، گل‌یاس، شیرین. Image top, white label bar, teal label + `‹`. |
| 4 | **Layout** | Right filter rail ≈280 px; content column left. Guide and best sellers **inside** the content column; rail spans alongside. |
| 5 | **Filters** | 6 collapsible groups — جنسیت، نوع رایحه، موقعیت استفاده، ماندگاری، برند، حجم. Label + checkbox, **no counts**. Brand search, 4–6 brands, «نمایش همه برندها». Outlined «پاک کردن فیلترها». Small «برندهای پیشنهادی» card below. |
| 6 | **Grid** | 4×2. Card: blush media, heart left, rose discount right, bold title, **brand line**, tags, gold partial stars + `(4.5)` + count, old price struck + bold price, full-width rose pill with cart icon. |
| 7 | **Guide** | Inside content column; mint gradient; copy right; four badges center (ضمانت اصالت، ارسال سریع، مشاوره تخصصی، پرداخت امن); photo left fading into mint. |
| 8 | **Best sellers** | Inside content column; 5 visible cards + circular arrows; image, name, brand/family, stars, price — no buy button. |
| 9 | **Footer** | Global site footer; out of scope. |

## 5. Gap analysis

| Area | Comp | Current WIP | Change |
| --- | --- | --- | --- |
| Hero | Full-bleed photo, copy left, h1 38 | White card, packshot, copy right, h1 30 | New full-bleed section + composed photo; type scale |
| Quick cards | Quiz right, image headers | Quiz left, no images | Reorder markup; add image strips; restyle |
| Families | 7 photo tiles | 6 letter tiles | Curated 7-tile map; images; icon fallback |
| Container | ≈1,220 | 1,332 | Page-scoped shell |
| Sidebar | No counts, 4 brands, clear outlined, suggestion card | Counts, 51 brand checkboxes, apply button, no card | Restructure; auto-apply; limit + show more; drawer |
| Grid | 4×2, brand line, 8/page | 4×3, no brand line, 12/page | 8/page, brand, stars, price order, sizing |
| Layout | Guide/best in content column | Full-width below layout | Move inside `.pf-content` |
| Guide | Photo left, 4 standard badges | No photo, wrong badges | Asset + labels/icons/order |
| Best | 5 cards in content column | 10 cards, full width | Move inside; 5 visible; rail polish |
| Mobile | Not in comp | Filters after products, no trigger | Sticky filter button + drawer |
| A11y/perf | — | Partial | Landmarks, aria, focus, contrast, preload |

## 6. Open decisions

These are **not** approved. The recommended option is first.

1. **Imagery.** The repo has one usable high-res lifestyle image:
   [`hero-banner.webp`](../wordpress/wp-content/uploads/2026/10/hero-banner.webp)
   (2000×403, Miss Dior + roses). There are no family/guide photos. **Recommended
   default:** compose hero/quick/guide from that banner and use seven interim
   comp-cropped family tiles (replaceable via a slug→asset map). **Better:** the
   owner supplies the original comp images.
2. **Header/footer.** The comp's chrome differs from the global header/footer.
   **Recommended:** keep global; do not fork site chrome for one page. Optional:
   add «عطر و ادکلن» → `/perfume/` to the primary nav — it is currently missing, so
   the page is not discoverable from the nav.
3. **Taxonomy.** **Recommended:** real six families + real occasion/longevity
   terms, 30/50/100 ml (exact, from titles), plus a 7th «همه رایحه‌ها» tile to keep
   the 7-column rhythm. The comp's unused facets are a separate data task.
4. **Pagination.** The comp shows 8 products and no pagination, but the page has
   110. **Recommended:** 8 per page + compact pagination. Alternative: 8 only +
   «مشاهده همه» to `/shop/`.
5. **Hero side.** The comp places the copy on the **left** (unusual for RTL).
   **Default:** follow the comp; a single CSS switch flips it to the RTL start.

## 7. Implementation plan

### Phase 1 — assets

New `assets/images/perfume/`:

- `hero.webp` 2000×560 — compose a soft floral bed on the left from the banner's
  white-flower area, a sharp bottle/rose crop on the right, **no baked-in copy**.
- `quick-search.webp`, `quick-browse.webp`, `guide.webp` — high-res crops from the
  same banner, masked into their panels.
- Seven family images 240×160 (interim comp crops) + inline SVG ingredient icons
  for any missing one. Never fall back to a letter.
- WebP, explicit dimensions, alt text, lazy-load everything except the hero.

### Phase 2 — template (`page-perfume.php`)

- **Keep:** facet parsing, `pf_url()`, volume map, sorts, `WP_Query`,
  best-seller query, pagination, empty state.
- Set `posts_per_page = 8`; add «پرامتیازترین» sort (`_wc_average_rating`);
  exact volume options 30/50/100 + «سایر».
- Family strip: six real terms + «همه رایحه‌ها» = 7 tiles; slug→label/image map;
  fallback icon.
- Sidebar: six collapsible groups, no counts, top six brands + search +
  show-more, always-visible outlined clear link, suggested-brands card. Remove
  the stock/sale toggles from this page (they remain on the shop).
- Grid: brand line under title; star fill width from rating; strong price then
  struck old price; out-of-stock badge; proper aria labels.
- Hero: `<img>` with `fetchpriority="high"`, full-bleed wrapper, copy block, CTA
  to `#pf-grid`.
- Quick cards: reorder (quiz → browse → search), image-strip markup, no nested
  links.
- Move guide + best sellers inside `.pf-content`; correct guide badges; keep rail.
- Add `<noscript>` apply button; keep every filter a shareable GET URL.

### Phase 3 — CSS (`perfume-landing.css`)

- Page shell max-width ≈1,220 px; full-bleed hero; 7-col families; 280 px rail +
  content; 4-col grid; guide 3-col; best 5-col.
- Match comp radii, borders, blush media, rose buy pill, mint panels; type scale;
  10–16 px rhythm.
- Rose CTA `#ad5568` (4.91:1) and badge likewise; focus-visible rings;
  `prefers-reduced-motion`.
- Responsive: 1100 → 3-col grid; 900 → drawer / static; 768 → 2-col grid, 3
  family tiles; 480 → 2-col, compact toolbar, hidden rail arrows, family rail
  scroll-snap.
- Check all four palette presets (teal active; Night especially).

### Phase 4 — JS (`perfume-landing.js`)

- Auto-submit filters on change (debounced), preserving `pf_sort`; no-JS fallback.
- Collapsible groups with `aria-expanded` (remember in `sessionStorage`).
- Brand search across hidden rows + show-more.
- Mobile filter drawer: trigger above grid, overlay, focus trap, ESC, scroll
  lock, apply/close.
- Keep AJAX add-to-cart, toast, cart-badge bump, wishlist toggle, brand search,
  rail arrows, sort redirect.
- Smooth scroll to `#pf-grid`; respect reduced motion.

### Phase 5 — enqueue/version

- Preload the hero for `/perfume/` only, inside the existing enqueue callback.
- Bump theme `Version` **1.27.11 → 1.27.12** so cached CSS/JS refresh.
- No new `ASC_` class. The page is downstream-only.

### Phase 6 — QA

- `php -l`; `bash docker/run-tests.sh` must stay green.
- Filter matrix: each group, combinations, clear, sort, pagination preserves
  query.
- Add-to-cart (simple/variable/out-of-stock), toast, cart count; brand search;
  show-more; rail.
- Screenshots at 1440/1024/768/390, side by side with the comp.
- Keyboard pass, focus trap, ARIA, contrast; no-JS pass; `debug.log` and console
  clean; LCP/CLS spot check.
- Optional: add a `/perfume/` smoke check (200 + `pf-hero`/`pf-grid`/`pf-guide`)
  to the suite.

### Phase 7 — docs/commit

- Commit only theme files + assets, not the unrelated dirty language/plugin files.
- Update [DEVELOPMENT_LOG.md](DEVELOPMENT_LOG.md) with the dated entry and
  verification results.
- Optional follow-up: «عطر و ادکلن» in the primary nav with `aria-current`.

## 8. Definition of done

- [ ] At 1440 the page reads like the comp: full-bleed hero, three quick cards
      (quiz right), seven family tiles, 280 px rail, 4×2 grid, guide inside the
      content column, five-card best-seller rail.
- [ ] No broken/letter family tiles; every image has alt text and dimensions.
- [ ] Filters, sort, pagination, clear, brand search, mobile drawer and
      add-to-cart work and stay shareable.
- [ ] 390/768 usable: filter trigger + drawer, 2-col grid, family rail.
- [ ] Suite green; no PHP notices or console errors; theme version bumped.
- [ ] Only intended files committed.

## 9. Verification status

**Done for this plan (2026-10-23):**

- `/perfume/` returns 200 on `https://lylyrose.local`.
- `php -l` on `page-perfume.php` and `functions.php` — no syntax errors.
- AJAX add-to-cart returns a WooCommerce JSON fragment.
- Rendered at 1440 and 390; compared with the comp section by section.
- Read the catalogue data from the local DB (counts in §3).
- Full suite: **412 passed, 0 failed** at this checkpoint.
- The five theme files in `2be8cefa` are the only theme changes; the checkpoint
  commit contains exactly them.

**Not done:**

- No design pass; no asset was generated; no CSS/JS/template was changed.
- No `/perfume/` assertion in the suite yet; adding one is optional Phase 6 work.
- No mobile/tablet visual sign-off; no accessibility audit on the rendered page.
- The comp's family photos and guide photo are not in the repo.

## 10. Housekeeping

- The worktree carries unrelated dirty language files, UpdraftPlus updates and
  many untracked probe scripts. They are **not** part of this page and must not
  be swept into its commit.
- Only the five theme files listed in §2 belong to the landing page.
- The comp image is stored at [assets/perfume-landing-comp.jpg](assets/perfume-landing-comp.jpg)
  so the plan is readable without the chat attachment.
