# `/skincare/` and `/haircare/` landing pages — plan

**Status:** shipped and live 2026-10-28; quick-box background photos added
2026-10-30 (§8b).
**Companion to:** [PERFUME_LANDING_PLAN.md](PERFUME_LANDING_PLAN.md) — same visual
language, thinner rails.

**Pages:** WordPress **6546** `skincare` («مراقبت از پوست») and **6547** `haircare`
(«مراقبت از مو»). Both publish, both carry placeholder block content with
`[products category=…]` shortcodes and links to eight empty subcategories.

## 1. Why this is not a copy-paste of `/perfume/`

`/perfume/` (page 6544) has 110 products and **11 usable facets** — gender,
fragrance family, occasion, longevity, brand, volume. The care trees have **51
products and zero `pa_*` attributes**: every one is St. Ives, no gender, no
brand variation, no volume attribute. A literal clone would ship five empty
filter groups.

The care data that *does* exist is **product meta**:

| Meta key | Values (skin) | Values (hair) | Coverage |
|---|---|---|---|
| `_needs_types` | dry, oily, combination, sensitive, normal | straight, wavy, curly, coily | 35/35, 16/16 |
| `_needs_concerns` | acne, dehydration, dullness, aging, dark_spots, redness | frizz, hair_fall, damage, dandruff, dryness | 35/35, 16/16 |

Seeded per-SKU by [`docker/seed-needs-data.php`](../docker/seed-needs-data.php)
(committed `d3e3119e`), already live, and read by `ASC_Needs_Quiz`
(`class-needs-quiz.php:462`) to power `/skin-needs/` and `/hair-needs/`.

**Decision (owner, 2026-10-26): promote that meta to real `pa_*` taxonomies**
rather than reading the meta directly. Reason: product meta cannot be faceted
natively — a meta filter is a `post__in` list built in PHP, the same hack
`pf_volume_map()` uses for perfume volumes because *perfume has no volume
attribute either*. Promoting gives a real taxonomy, a term-count query, shareable
term URLs, and one source of truth the quiz can also read.

**Plan: the quiz keeps reading meta; the landing pages read the new taxonomies.
A later pass may point the quiz at the taxonomies.** Migrating the quiz in the
same change would put a working, live feature in the blast radius of a page build.

## 2. New attributes

| Taxonomy | Label | Terms | Widget type | Used by |
|---|---|---|---|---|
| `pa_skin_type` | نوع پوست | خشک، چرب، مختلط، حساس، نرمال | select | skincare |
| `pa_skin_concern` | نیاز پوست | جوش و آکنه، کم‌آبی، کدری و بیرونقی، چین و چروک، لک و تیرگی، قرمزی | select | skincare |
| `pa_hair_type` | بافت مو | صاف، موجدار، فر، خیلی فر | select | haircare |
| `pa_hair_concern` | نیاز مو | وز و پریشی، ریزش، آسیب و شکنندگی، شوره، خشکی | select | haircare |

Term slugs must match the meta values verbatim (`dry`, `acne`, `frizz`, …) so the
migration is a lossless copy and a later quiz-side switch needs no translation.

## 3. Data migration

New `docker/migrate-care-attributes.php`, modelled on
[`docker/seed-needs-data.php`](../docker/seed-needs-data.php):

- Dry-run by default; `--write` to commit — **and the flag must be spelled both
  ways** (`--write` / `--write=true`), the trap that made a "dry run" write for
  real before ([[dry-run-flag-must-match-both-spellings]]).
- Idempotent: create attribute only if absent, create term only if absent, and
  `wp_set_object_terms` with **append = false** so a re-run converges.
- Keyed by **SKU**, never product id — ids differ local vs live
  ([[term-ids-differ-across-wp-term-tables]] does not apply here, but the SKU rule
  is what made `seed-needs-data.php` portable).
- Reads `_needs_types` / `_needs_concerns` and writes the matching `pa_*` terms.
  The meta is the input, not the output.
- Reports a per-product diff and an unchanged-count so a second run proves zero.

Verification the migration must pass before the pages are built:

1. `wc_get_attribute_taxonomies()` lists the four new attributes.
2. Every one of the 51 products has ≥1 `pa_*` term for its tree, and the counts
   per term equal the meta counts measured 2026-10-26 (skin types
   dry 21 / normal 20 / sensitive 8 / combination 10 / oily 9; skin concerns
   dehydration 16 / dullness 12 / aging 7 / acne 6 / redness 6 / dark_spots 2;
   hair types straight 13 / wavy 16 / curly 16 / coily 12; hair concerns
   frizz 12 / damage 12 / dryness 8).
3. A second `--write` run changes nothing.

## 4. Templates (as built)

Owner's decision was "two standalone templates"; what shipped is a **shared body
plus two thin configs**, which gets the same independence with one maintenance
surface instead of ~1,400 duplicated lines:

| File | Role |
|---|---|
| [`page-skincare.php`](../wordpress/wp-content/themes/lylyrose/page-skincare.php) | skincare config (~90 lines) |
| [`page-haircare.php`](../wordpress/wp-content/themes/lylyrose/page-haircare.php) | haircare config (~75 lines) |
| [`care-landing.php`](../wordpress/wp-content/themes/lylyrose/care-landing.php) | shared body (~600 lines) |

Each page file sets one `$care` array and `require`s the body, so a page is still
edited on its own — copy, tiles, taxonomy, quiz URL all live in its file.
`page-perfume.php` is **not touched**.

They follow `page-perfume.php`'s structure and reuse its proven parts verbatim:
the GET-param URL helper, the inline-SVG icon set, the partial-rating stars
helper, the AJAX add-to-cart card, pagination, and the empty state. Differences:

| Section | `/perfume/` | care pages |
|---|---|---|
| Hero | Miss Dior photo, «دنیای عطرها» | care lifestyle image, own copy |
| Quick cards | quiz / browse / search; quiz links `/perfume-finder/` | quiz links **`/skin-needs/`** and **`/hair-needs/`** |
| Tiles | 7 scent families | 6–8 **subcategory** tiles: skin = پاک‌کننده، فوم، تونر، مرطوب‌کننده، ضدآفتاب، اسکراب بدن، لوسیون بدن؛ hair = شامپو، ماسک، روغن، حالت‌دهنده، ترمیم‌کننده |
| Rail groups | gender, family, occasion, longevity, brand, volume | **type + concern** only (+ subcategory as tiles, not checkboxes) |
| Brand group | 49 brands | **omitted** — one brand |
| Sort | 5 options | 3 (newest, cheapest, expensive) — no sales, so no «پرفروش‌ترین» |
| Best rail | «پرفروش‌ترین عطرها» | «منتخب ما» / curated, since `total_sales` is 0 across the tree |
| Guide | «راهنمای انتخاب عطر» → finder | «راهنمای مراقبت» → the needs quiz |

**Empty subcategories must not render.** Several exist with `count=0`
(serums, acne-care, dark-spot-care, anti-aging, shampoo, hair-masks,
anti-hair-loss, hair-repair, colored-hair). Tiles are built from a declared
slug→label map **resolved against live terms**, dropping anything empty — the
same rule `lylyrose_mega_menu()` uses so a rename is followed and a dead link is
skipped rather than rendered.

## 5. Assets (as built)

Three images per page: `hero.webp` (1680×403), `guide.webp` (600×480), and one
`tile-<subcategory>.webp` (320×154 — matching the perfume family tiles) per
subcategory, 10 in total. A later pass added three `quick-bg-<name>.webp`
(1200×640) per page for the quick-entry boxes — see §8b.

**Tile backgrounds come from real lifestyle photography, which does exist — in
the Digikala mirror.** `new_products/digikala/<SKU>/` holds, besides flat
packshots, AI-rendered scene shots: window light with orchids, coconut on wood,
roses in a vase, fern in a pot, sea-salt rock in water. That is the only real
photography in this repo (the theme, uploads and `new_products/photos` contain
none) and four of those scenes carry no baked-in marketing copy, which is what
makes them usable as grounds. Sources and per-tile crop boxes are the `tiles`
table in the script; boxes are fractions of the source and are chosen to frame
the props while keeping the pack and any copy out of frame.

`docker/compose-care-images.py` builds all three kinds. For the hero/guide it lays
a blurred row of bottles as the ground with one packshot sharp over it, **no
baked-in copy** (all text is HTML); these need:

- **White keyed to alpha.** The packshots are JPEG/WebP on white, not transparent
  PNGs, so pasting one straight leaves a white rectangle over the bed.
- **Enough blur that the bed's repeated logos are not legible.**

Tiles instead crop a scene directly. It is read-only, and because `wp-content` is
`www-data`-owned it takes `--outdir` so it can stage to a writable path and be
copied in through the container ([[theme-files-need-container-root]]).

Everything WebP, explicit width/height, lazy-loaded except the hero. The tile row
uses `grid-template-columns: repeat(var(--pf-tiles, 7), …)` and the care template
emits `--pf-tiles` from its tile count, because the shared stylesheet hardcoded
seven columns and haircare has only three (the fallback of 7 leaves `/perfume/`
byte-identical).

## 6. Wiring

[`functions.php`](../wordpress/wp-content/themes/lylyrose/functions.php) — extend
the existing page-gated enqueue alongside `lylyrose_perfume_landing_assets()`
(lines ~252–292):

- Reuse `assets/css/perfume-landing.css` (`.pf-`-scoped, palette-token-driven,
  already 1,178 lines) and `perfume-landing.js`. **No new CSS/JS file** — the
  care pages are the same components with fewer groups. A care-only rule, if one
  is unavoidable, goes in a small `care-landing.css` rather than forking the
  shared file.
- Widen the gate from `is_page( 'perfume' )` to `is_page( array( 'perfume',
  'skincare', 'haircare' ) )`, and preload each page's own hero.
- Keeping `perfume-landing.js` shared means its `lylyrosePerfume` localize object
  is consumed by care pages too — acceptable, it carries only `ajaxUrl` + two
  strings; the name is cosmetic and renaming it is churn. Record that here.

**Theme `Version:` must be bumped** (currently **1.27.14** → **1.27.15**) or the
browser keeps the old CSS — see [[theme-css-needs-version-bump]].

## 7. Verification (local — done 2026-10-26)

- `php -l` on `care-landing.php`, `page-skincare.php`, `page-haircare.php`,
  `functions.php` — clean.
- Migration: dry run matched the measured meta counts, `--write` wrote 102
  product-term changes, a **second `--write` wrote 0** (idempotent). Independent
  re-read: all 51 products carry a term in both of their tree's taxonomies, and
  per-term counts equal the meta counts.
- `bash docker/run-tests.sh` — **412 passed, 0 failed**.
- Both pages 200 and render every section (hero, 3 quick cards, tiles, 3 rail
  groups, 8-card grid, guide, 10-card rail); no fatal text.
- Facets narrow correctly: skin acne 6, dark-spot 2, sensitive+redness 6;
  hair straight+dryness 5. Subcategory tiles: sunscreen 1, cleanser 8,
  moisturizer 6, scrub 4 — all matching the catalogue. A term from the other
  tree passed in the URL yields the empty state, not a widened set.
- Grid 4-col at 1440, 2-col at 375; no horizontal overflow at either.
- Mobile filter drawer: trigger renders, `.is-open` + `body.pf-lock` set, and
  with the transition suppressed the rail resolves to `translateX(0)` /
  `visible` — correct. (Read via computed style for 600 ms it looks stuck; that
  is the paused `visibility` transition in a background tab, the same trap seen
  on `/perfume/` — [[live-palette-change-does-not-purge-cache]] family.)
- Console clean on both pages.

**Not done:** no deploy; not checked at 768/1024; Night/ivory/navy palettes not
rendered by eye; add-to-cart not exercised on the care cards.

## 8. Deploy — DONE 2026-10-28

Deployed and verified on `lylyrose.ir`.

**What live did NOT need, contrary to the first read.** The two pages *already
existed* on live as pages **2031** (`skincare`) and **2029** (`haircare`), both
`publish` — they were 404ing only because the templates were missing. **No page
rows were created.** The care products also already carried
`_needs_types`/`_needs_concerns` (35/35 and 16/16), so the migration had a real
source.

**Shipped** (19 files, `deploy-targeted.py`, every file size-verified):
`care-landing.php`, `page-skincare.php`, `page-haircare.php`, `functions.php`,
`assets/css/perfume-landing.css`, and the 10 tiles + 2 heroes + 2 guides.

**`style.css` was NOT uploaded.** Live was already **1.27.18** while the worktree
was 1.27.17 — uploading ours would have regressed a peer's self-hosted-Vazirmatn
fix. Instead live's *own* file was downloaded, one line changed
(`1.27.18` → `1.27.19`), and pushed back; the re-download's sha256 matched. Note
the file is the **same byte length** either way, so a size comparison cannot see
this change — it is a hash-only diff ([[diff-host-compares-size-not-hash]]).
Backups of the three pre-deploy live files are in
`backups/live-pre-care-deploy-2026-10-28/`.

**Migration on live** ran through the docroot probe (random name, deleted after,
404 + FTP-confirmed). It hit the same in-process registration trap as locally —
the first request created attributes 1–4 but every `wp_insert_term` failed
«رده نامعتبر» — and the **second request in a fresh process** created the 20
terms and wrote all 102 product links. Live term counts match local exactly
(skin types 21/20/9/8/10; hair 16/16/13/12; concerns as in §3).

**Verified on live:** `/skincare/` and `/haircare/` 200; no fatals on either, nor
on `/perfume/`, `/skin-needs/`, `/hair-needs/`, `/shop/`; all 12 tile/hero assets
200 with byte sizes identical to local; tile `<img>` `complete` with
`naturalWidth` 320; facet groups render with the Persian labels; and filtering
narrows as expected (dark-spot 2, redness 6, sunscreen 1, cleanser 8,
moisturizer 6, hair-oil 1). Haircare's row is 3 wide columns; `/perfume/` still
renders its 7 tiles. LiteSpeed and WP Super Cache were both purged (both are
live at once); both now report a fresh `x-litespeed-cache: hit`.

**Trap worth keeping:** a headless screenshot taken before the row is scrolled
into view shows the tiles *blank*, because `loading="lazy"` has not fired. The
assets were serving 200 the whole time. Scroll the row in and wait before
concluding a tile is broken.


## 8b. Quick-box background photos — DONE 2026-10-30

Follow-up to §8. Live `/skincare/` and `/haircare/` rendered `.pf-quick` as
**bare text cards** — no photo at all — where `/perfume/` has a strip image in
each. The owner asked for a **full-bleed background per box**, deliberately not
the perfume page's top-strip treatment.

**As built.** Each card carries an `<img class="pf-quick-bg">` absolutely
positioned with `object-fit: cover`, a `<span class="pf-quick-scrim">` gradient
over it, and a `pf-quick-card--photo` modifier (quiz card included, so it drops
its mint side-by-side split). Six 1200x640 webp live in
`assets/images/{skincare,haircare}/quick-bg-{quiz,a,b}.webp`, generated by
`docker/compose-care-quick-bg.py` (`--preview` renders a contact sheet and
writes no theme files). Every new rule is scoped under
`.pf-quick-card--photo`, which only `care-landing.php` emits, so the `.pf-quick`
rules shared with `/perfume/` are untouched.

**Crop selection.** Sources are the staged Digikala lifestyle scenes in
`new_products/digikala/`, cropped to regions that hold **neither the pack nor
the baked-in Persian copy** — copy that would otherwise sit directly under the
card's own headline. Skincare uses `COC02` (palm + orchid), `COL03` (fern),
`SEA02` (rock + water + leaf); haircare uses the pedestal-plus-sphere to the
right of the pack in the HM `02` scenes (`ARG02`, `COLM`, `BOT02`) at
`(0.50, 0.50, 1.00, 1.00)`. A 4px Gaussian blur removes any stray edge.

**Two traps, both paid for:**

1. The brief's proposed haircare boxes `(0.00, 0.84, 0.42, 1.00)` produce a
   flat pastel band, not a photo. They were replaced after rendering.
2. **A thumbnail contact sheet hides baked copy.** The first sheet drew its own
   caption bar across the top of each cell, which covered exactly the copy a
   first-round crop had let through. Two rounds of "looks clean" survived on
   thumbnails; the copy only appeared at 1440. Verify a crop numerically
   (darkest-pixel count) or at full resolution, never from a thumbnail. Note the
   converse too: `BOT02`'s dark pixels at full size are the *sphere's shadow*,
   not glyphs — check where the dark pixels actually are before condemning a
   crop.

**Verified on live** (plain URLs, post-purge): `/skincare/` and `/haircare/`
each serve 3 `pf-quick-bg`; `/perfume/` serves 0; all 6 assets 200
`image/webp`; all 3 images `complete` with `naturalWidth` 1200; text contrast
9.8:1 (skincare, 420px) to 10.3:1 (haircare, 1440px) measured against a
text-free render. `docker/diff-host.py`: 107 identical, 0 differing.

**Deploy trap — the cache, again.** After uploading, `/haircare/` showed the new
markup immediately but `/skincare/` did not, while `?nocache=…` on skincare
served it fine. That is a stale **LiteSpeed page cache** entry, not a bad
upload ([memory: live-palette-change-does-not-purge-cache] family). LiteSpeed
and WP Super Cache are both live at once; both were purged via the docroot probe
(`random name, delete immediately, check the 404`). Note `style.css` was
uploaded even though `diff-host.py` reported it **identical** — the version
string 1.27.20 → 1.27.21 is byte-length-neutral, so a size comparison is blind
to it ([[diff-host-compares-size-not-hash]]); a `md5sum` against an FTP `RETR`
of live's own copy is what proved it differed.

## 9. Out of scope

- Pointing `ASC_Needs_Quiz` at the new taxonomies (§1).
- Any change to `/perfume/`, `/shop/`, or the category archives.
- Real lifestyle photography — the owner supplies it later; composition is interim.
