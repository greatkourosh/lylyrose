# Parallel sessions — ownership, blockers, roadmap

Written 2026-10-05 by the session fixing product-card links and the offers-rail
name. Last reconciled 2026-10-18 against git and the live host (St. Ives Digikala matching widened to 44/51 in §0j). Several Claude
sessions share this one working tree. This file is the handover surface: who
owns what, what is blocked, and what must not be committed until a decision lands.

**Current state, in one line:** no code blocker remains. The only open items are
human-blocked per-host credentials and content decisions. The live list is §6.
Sections 0–5 are the record of how the work got here; where they disagree with §6,
§6 wins.

---

## 0k. Verification pass — the four "never checked on live" features all work (2026-10-20)

Session goal: check the live features the docs recorded as unverified, settle the
uncommitted files, and correct the docs. Commit `7c2beda5`.

**All four unverified features are confirmed working on `lylyrose.ir`:**

| Feature | How it was checked | Result |
|---|---|---|
| Typo-tolerant search | `/?s=ژاوورو&post_type=product` | 3 product cards; a nonsense query returns 0. **Works.** |
| National-ID checkout field | added product 1299 to a live cart, read the checkout HTML | `billing_national_id` renders, `maxlength=10`, `pattern="[0-9]{10}"`, required. **Works.** The cart was emptied afterwards and no order was created. |
| AVIF upload path | side-loaded a generated WebP through `media_handle_sideload` on live | both the original and the `-100x100` size got an `.avif` sibling. **Works.** The probe attachments (2026, 2027) and their files were deleted, and the upload dir was confirmed clean. |
| Palette switcher | read the served HTML and the Autoptimize bundle | 4 swatch buttons render, `lylyrosePalette` ships as a base64 data URI with 4 choices, and the bundle `986f2682` is the minified local `palette.js`. **Works.** |

**Two traps that cost time, both worth keeping:**

- **A search URL without `post_type=product` returns 0 cards even on a site that
  works.** The first probe looked like a dead feature. The suite's own section 38
  uses the parameter, and the count must be `grep -o` — the HTML is one line, so
  `grep -c` reports 1 whether there are 3 cards or 12.
- **`grep -c 'palette.js'` is a false positive.** The `.` matched inside a base64
  blob. Count the exact string in Python, or the count means nothing.

**Live and local serve different palettes — and that is not a bug.**

`lylyrose.ir` serves `ivory`; `lylyrose.local` serves the teal `lylyrose` preset.
Same code, different data: the `asc_palette` option is **unset on live**, so it
falls through to the hardcoded `'ivory'` fallback in `class-palette.php`. Local has
the option explicitly set to `lylyrose`.

Live's `ivory` tokens are `--dk-bg:#f8f3ee`, `--dk-ink:#302522`, `--dk-red:#9c5c5f`
— exactly the recorded brand decision (Ivory `#F8F3EE` 60%, Espresso `#302522` 15%,
Dusty Rose `#C98F91` 20%). The teal preset was made the *theme* default by
`2827066c` on 2026-10-10, but the **live site was never switched to it**, because
that commit only touched `style.css` and the plugin's preset list, never the DB
option. **Decide which palette the owner actually wants** — this is a content
decision, not a bug to fix.

**`diff-host.py` did not walk `wp-content/mu-plugins`.** That is why §0i's claim that
the Digikala mu-plugin guard was "in git only" survived so long: the one
first-party tree the drift check could not see. It walks mu-plugins now. The
baseline went from 67 to 68 files and the guard turned out to be live all along.

**Settled:** `docker-compose.yml` (curl `8.22.0`→`8.23.0`, redis
`8.10.1-alpine`→`8.10.2-alpine`) is committed — the registry no longer carries the
old tags, so the old pins cannot be pulled again. `docker/run-tier-tests.sh` stays
**untracked**; it is a peer's file. Its header says "ONLY section 35" but it now
runs sections 35–47 (83 assertions) — stale, but not ours to rewrite.

---

## 0j. Digikala matching widened to 44 of 51 St. Ives SKUs — deployed (2026-10-18)

- **The route that works for Digikala is `api.digikala.com` from inside the container**, not the website: `/v1/search/?q=` for candidates, `/v2/product/{id}/` for images + description. The Browser pane refuses digikala.com and WebSearch returns nothing for it. `docker/digikala-find.php` (search only, writes nothing) and `docker/digikala-enrich.php` (curated map, applies it) are the two halves.
- Galleries are **appended** to the existing packshot, never replacing it, and the enrichment is idempotent via `_digikala_image_urls` so a re-run downloads nothing.
- **The 7 left unlinked are unlinked on purpose** — no counterpart exists there: two exfoliant toners, the SPF50, the three curl products, the argan oil. `_meta.digikala_match_note` in `new_products/stives-products.json` records this and the fact that the remaining matches are best-effort, not 1:1.
- **Do not trust a probe that prints `img=kept`.** The 2026-10-18 sync wrote all the meta and downloaded zero images, because the guard skipped every product that already had a thumbnail. Verify image work by comparing the host's gallery count with `_digikala_image_urls`, not by the probe's own log line.
- **The probe must not run `sanitize_title()` on an incoming slug.** On the live host it transliterates Persian to ASCII (`ژل` → `uou`); it left 12 of 51 products with slugs like `uou-uu-ooo-oo-ouou` before this was caught. Write the payload's slug through verbatim. Audit with "slugs containing no `%`" — 0 is the target on both hosts.
- **Read the served page, not just the probe's echo.** After a sync, fetch a product permalink and count `wp-content/uploads/` hits. A probe can report success for data that renders as a 404 or a placeholder.

---

## 0i. St. Ives range: brand-name titles, Digikala galleries, live sync — deployed (2026-10-17)

51 St. Ives products (local 4684–4783 / live 1894–1907) synced on both hosts.
Owner decisions embedded here so the next session does not re-do them:

- **Title order:** brand comes AFTER the product type, not first. `فوم شستشو صورت سنت ایوز حاوی بابونه` — not `سنت ایوز فوم شستشو صورت ...`. A previous pass that prefixed the brand was wrong.
- **Size suffixes are part of the title** on the moisturizing-cream SKUs (`... ۹۰ میل`, `... ۶۲۱ گرم`, `... ۱۷۰ گرم`). Do not strip them.
- **Slug convention:** `post_name = strtolower(rawurlencode(title with spaces→hyphens and parens removed))`. The `sku-{ID}` segment comes from the permalink structure — never put it into `post_name` (double-prefixes every URL).
- **Digikala link field** lives in `wp-content/mu-plugins/digikala-product-link.php` (blue box on product edit page + icon column in product list), fed by `_digikala_id` / `_digikala_url` / `_digikala_title` meta. Tracked in git since `9b1f8bbb`; guarded with `ABSPATH` + `is_admin()` and reading the id via `get_the_ID()` since `fe5e32b2` (2026-10-19). 44 of 51 products have Digikala data (2026-10-18); the other 7 have no counterpart there. **Corrected 2026-10-20: the guard IS live.** The live file is 3,041 B, which is the guarded size (the pre-guard file was 2,939 B) — so the earlier "in git only" line was wrong. `docker/diff-host.py` could not see this because it did not walk `wp-content/mu-plugins`; it does now (`7c2beda5`).
- **Live deploy used the standard probe route** (§7 below): FTTP probe + JSON payload, php -l local first, random name, deleted after, 404-confirmed. Live already had its galleries, so zero images were downloaded.
- §7's "mu-plugins not loaded" trap is **outdated** — mu-plugins load fine in the container now. The §7 text stays as history; this note supersedes it.
- Brand archive URL is `/{برند}/st-ives/` (taxonomy slug is `برند`, not `product-brand`). Verified: local 200, live 200 (2026-10-17).


Keep it current. If you change something listed here, change it here in the same
pass — a stale version of this file is worse than none.

---

## 0h. Finder admin, quiz menu, needs quizzes — committed in both repos, deployed (2026-10-12/13)

Built in `aroma_store` first, then mirrored. Upstream suite **357/0 + 1 new assertion**
(`8e1821ae`). Downstream suite **391/0** (`1129f40c`). Deployed to lylyrose.ir on
2026-10-13: 10 files uploaded, `diff-host.py` 67 identical / 0 differing / 0 absent,
LiteSpeed and Autoptimize purged. Pushed: downstream `78d5b674` and upstream
`cf361e0d`, which includes the accounting commits `3f14a96c` and `cf361e0d`. Re-checked
2026-10-13: upstream HEAD `991704a2` (balance sheet and VAT, pushed 2026-10-13) passes
**373/0**, so the 11 accounting assertions are no longer red. Re-run 2026-10-14: still 373/0. Quiz CSS needs no bump: it is
cache-busted by plugin `LYLYROSE_CORE_VERSION` 2.4.0 and the live copy matches local by md5.

- **Quiz menu** (`ASC_Quiz_Menu`): header and drawer links to the finder pages, with
  editable rows and five rules. Dead rows are dropped.
- **Finder settings** (`ASC_Finder_Settings`, `ASC_Finder_Weights`): quiz options,
  longevity scale, result count, and axis weights with a live share column.
- **Needs quizzes** (`ASC_Needs_Quiz`): skin and hair, searching each category tree
  (root plus children). An empty tree answers 404 and is not recreated, so the quiz
  comes back by itself when products return. The theme templates `page-skin-needs.php`
  and `page-hair-needs.php` are removed; the plugin renders via `the_content`.
- **Admin hub** (`ASC_Admin_Hub`): one Settings page with perfume, skin and hair tabs.
  The skin and hair tabs are editable: root category, per-question weights (type and
  concern, 0–10, default 1) and question/option labels. Option keys stay fixed because
  they are stored in product meta. Old standalone finder URLs are
  hidden from the menu and redirect to the perfume tab.
- **Catalogue:** aroma_store's product catalogue was replaced with lylyrose's (165
  published + 1 private, 39 categories, 500 media files reused). Gift-card and wallet
  products on aroma are kept by decision. Category parents were restored after the
  copy flattened them (19 reparented).
- **Theme:** `page.php` article background is `var(--dk-white)`, not `#fff`, so pages are
  readable in night palette.

Deployed 2026-10-13: accounting mirror `62ce02b7` (`class-accounting.php` = upstream
`991704a2`) uploaded via `deploy-targeted.py`, size-verified 10,734 → 24,327 B. `diff-host.py`
**67 identical / 0 differing / 0 absent**. `/wp-json/aroma/v1/balance-sheet` answers 401 (auth
gate). Downstream suite **412/0** on the next two runs and again 2026-10-14. The first run had one flake, `orders leaked: 1`.
Likely cause: a second `run-tests.sh` was running against the same `lylyrose-wp` DB (PID 94493, started by
another session), so one run's orders showed up in the other's cleanup count. Not proven; the suite has
no lock against concurrent runs.

---

## 0g. Typo search + AVIF session — committed and live (2026-10-10)

Built in `aroma_store` first, then mirrored. Suites: upstream **324/0**, downstream
**356/0**. Full record in `CONTINUATION.md` (2026-10-10 entry).

| Commit | Repo | Change |
|---|---|---|
| `c7f139ee` / `332382fb` | lylyrose / aroma_store | `ASC_Fuzzy_Search` typo fallback |
| `eea32ea7` / `3f5cc874` | lylyrose / aroma_store | `ASC_Avif` AVIF delivery + `Vary: Accept` |
| `9cb24580` / `07b8cc2a` | lylyrose / aroma_store | AVIF encoder uses GD when Imagick is absent |

**Trap worth keeping:** the live host has GD with AVIF but no Imagick. Any encoder
written against the local Imagick stack would fatal on every upload there.

**Still uncommitted and not this session's:** `docker/host-dryrun.py` in lylyrose.
Leave it unstaged unless its owner says otherwise.

## 0f. Brand palette + mega menu session — committed here (2026-10-10)

Suite **351 passed, 0 failed**. Theme `Version:` → **1.26.0**.

| File | Change |
|---|---|
| `lylyrose/style.css` | `:root` recoloured to the new Lyly Rose brand (deep teal `#064048`); `.dk-mega-featured` rule added; `Version:` → **1.26.0** |
| `lylyrose-core/includes/class-palette.php` | new first-shipped `lylyrose` preset, and `:root` now matches it |
| `lylyrose/functions.php` | `lylyrose_mega_menu()` — the curated four-section tree; `lylyrose_old_cat_redirect()` — 301s the five categories that moved under عطر و ادکلن |
| `lylyrose/header.php` | catnav renders the mega menu; drawer unchanged |
| `docker/seed-finder-data.php` | locate the data file by glob (the docroot uploader randomises its name) |

### The fatal that started this session, and what it cost

**Every page 500'd with `PHP Parse error: syntax error, unexpected token "<",
expecting end of file in header.php on line 250`.** `header.php` carried an
orphaned `<?php` at line 247, and line 250 opened PHP again. PHP cannot re-open
inside PHP, and `header.php` loads on **every** page, so the whole site was down
rather than one template.

**The lesson is the one that keeps recurring here: lint every PHP file after
writing it.** `run-tests.sh` never went red for this — a parse error in
`header.php` is a 500, and the suite's own fixtures read pages it controls. The
suite is not a syntax check. `php -l` over the whole theme, plugin and
mu-plugin trees is the check that catches it:

```bash
docker compose exec -T -u root wordpress sh -lc \
  'for f in $(find /var/www/html/wp-content -name "*.php"); do php -l "$f" || echo "FAIL $f"; done'
```

Note the `-u root`. See §5 — the Edit tool cannot write under `wp-content` at
all, so this session's one-line fix was applied from inside the container.
Ownership is unaffected: a rewrite from root leaves the file `www-data`-owned
(`php -r` writes in place, it does not recreate the inode), and
`test -w` as `www-data` still passes afterwards.

### Why the palette values differ from the brief in two places

`--dk-muted` is **`#737373`, not the requested `#8A8A8A`** — `#8A8A8A` is
**3.45:1** on white and fails AA; `#737373` is **4.74:1**. And the brief's Soft
Pink / Blush have **no token to land in**: they would be a second near-identical
surface next to `--dk-red-light`, so they stay available to CSS as literals
rather than becoming two tokens that always render the same. Every other value is
the brief's, and all clear AA — worst is the muted text on a `#fafafa` card at
**4.54:1**.

**`--dk-red` keeps its name while being a teal.** ~96 rules read it. Renaming it
to `--dk-primary` is pure churn across three CSS files for no visual gain; the
token map in `class-palette.php` is the one place that would have to change.

### The mega menu resolves its slugs on every render, on purpose

`lylyrose_mega_menu()` is a declared spec, not stored navigation. It is resolved
against live terms each render, so **a slug this shop does not have is skipped
rather than rendered as a dead link**, and a category the owner renames follows
the rename. `hide_empty` is deliberately *not* set: this is the shop's
navigation, not a report, and an empty آرایش still leads somewhere real. Dropping
it would silently undo the agreed structure — which is exactly what `header.php`'s
drawer already does, and must keep doing, because it groups by slug and its
catch-all has to pick up whatever the rows did not claim.

Verified against the served HTML: four sections, 23 rows, and one ⭐ featured row
(عطرت رو پیدا کن → the finder page).

---

## 0e. Announce-bar + demo-banner palette session — committed here (2026-10-07)

The announce strip and the demo banner now have a real palette. Suite **347 passed, 0
failed**, and the new section-36 assertions are **proven to go red** on the broken
build (see below). Theme `Version:` → **1.25.0**.

| File | Change |
|---|---|
| `lylyrose-core/includes/class-palette.php` | `bar`/`on-bar` added to `EXTRA` and `CSS_PROP`; every palette supplies both |
| `lylyrose/style.css` | `--dk-bar` / `--dk-on-bar` added to `:root`; `.dk-announce` reads them instead of `--dk-ink`; the `.dk-demo-banner` literal hexes (`#fff3cd`/`#fffbe6`/`#ffe69c`/`#664d03`) became `--dk-red-light` / `--dk-red` mixes |
| `docker/run-tests.sh` | new **§36** — 9 assertions for the bar tokens, the demo banner, and its contrast, plus 3 added 2026-10-08 for the thumbnail size-cache purge (see the correction below) |

### Why `--dk-bar` exists rather than reusing `--dk-ink`

The strip above the header is **the one surface that must stay dark in every
palette**, and `--dk-ink` does exactly the opposite: Night's `--dk-ink` is `#f2f4f8`,
which painted a near-white bar across a dark page. A role token states the intent and
lets each palette supply its own value. Night's `bar` is now `#08090d` — the *darkest*
surface in that palette, asserted — with `#d5d9e2` on it at **14.08:1**.

### Contrast is measured, and the comments' numbers are correct

The demo banner's text reads `--dk-red` on a `--dk-red-light` stripe. Verified in all
three palettes: **ivory 4.69:1, navy 17.41:1, night 5.92:1** (worst 4.69 clears 4.5).
The code comments claim 4.58 / 5.83 for `--dk-red` on plain `--dk-red-light`; both were
recomputed and match. §36 asserts the 4.5 floor directly rather than trusting a comment.

### §36 is proven, not assumed

Reverting `.dk-announce` to `--dk-ink` (the original bug) flips **three** §36
assertions red: "does not use `--dk-bar`", "does not use `--dk-on-bar`", and "still
reads `--dk-ink`". The mutation was reverted and the suite returned to 351/0. See
[[assert-fails-on-broken-build]].

### RESOLVED 2026-10-08 — the thumbnail filter was never the blocker. The 29 unscaled
### images are narrower than 300px, so WordPress will not scale them up.

Re-measured against the running container and the served HTML. **The OPEN block this
replaces gave the right symptom and the wrong cause**, and would have sent the next
person to run a bulk regen that provably cannot change the output.

`functions.php` filters `woocommerce_get_image_size_thumbnail`, and that filter is
**correct and does fire** — `wc_get_image_size('woocommerce_thumbnail')` resolves to
`{"width":300,"height":"","crop":0}`, and `wp_get_registered_image_subsizes()` reports
`woocommerce_thumbnail` as `300x0` (uncropped). Two traps cost real time here; both are
now documented in the code comment above the filter:

1. **The hook name is a lie, and that is correct.** WooCommerce strips the
   `woocommerce_` prefix from the size name before firing the filter
   (`wc-core-functions.php`), so the size `woocommerce_thumbnail` fires
   `woocommerce_get_image_size_thumbnail`. Renaming the hook to spell out
   `woocommerce_thumbnail` silently stops it working. The **cache key**, though, is
   built from the *full* name (`size-woocommerce_thumbnail`) — the one place the
   prefix survives — so the peer's original `wp_cache_delete( 'size-thumbnail' )` was
   the actual bug: it cleared a key nothing writes. The uncommitted +10 in
   `functions.php` deletes **both** spellings, which is correct.

2. **The filter cannot change what the product grid renders**, and no data operation
   can either. Product cards call `$product->get_image( 'woocommerce_thumbnail' )`,
   and `wp_get_attachment_image_src()` builds `srcset` from the **stored attachment
   metadata**, not from `wc_get_image_size()`. But regenerating that metadata is
   **not** the fix, for a reason this block previously got wrong.

### The census, measured (2026-10-08)

164 attachments with metadata. **128 have no `woocommerce_thumbnail` size at all** and
render the full original — 1,154 KB that a thumbnail would have shrunk.

| | with `woocommerce_thumbnail` | without |
|---|---|---|
| square / landscape originals | 20 | 99 |
| portrait originals | 16 | **29** |

**All 29 portrait originals that lack a thumbnail are 246–258px wide.** WordPress
never scales an image *up* to a registered size, so for those the full original **is**
the smallest image that exists — `-300x300` is unobtainable, and asking for it would
mean *enlarging* the bottle. The remaining 99 are 300x300 originals, where serving the
original costs nothing at all.

Verified on attachment **4683** (`STIVES-FC-TEATREE-ACNE`, 250x812), with a backup
taken first and the metadata restored afterwards: `wp_generate_attachment_metadata()`
emits `medium`, `thumbnail`, `woocommerce_gallery_thumbnail` and **not**
`woocommerce_thumbnail`, and `wp_get_attachment_image_src()` keeps returning the full
`250x812` original before and after. Confirmed back at `(none)` sizes on restore.

So the earlier note's "29 still square" was wrong twice: they are portrait, and they are
not croppable at all. The grid emitting `gucci-300x300.webp` is likewise not a stale
metadata artifact — **gucci's original is genuinely 600x600**.

**What would actually change the bytes** is replacing the 29 narrow originals with
wider source images (or reshooting them), which is a content task, not a code one.
Whether narrow cards should be padded to a uniform grid box is a design call, not a
bug fix.

### CORRECTION 2026-10-08 — only the *per-attachment* assertion is unwriteable. The
### registered-size one is writable, and §36 now asserts it (3 assertions).

The line above read "§36 deliberately still asserts nothing about thumbnails — the
assertion would be unwriteable, not merely fragile." That was true of the assertion
that claim was reasoned about, and it was then generalised to the whole topic, which
made §36 look unjustifiably bare.

**The unwriteable one is per-attachment `srcset`.** For those 128 attachments the
correct output *is* the un-scaled original, so there is no expected filename or
dimension to assert. That still stands.

**The writable one is the registered size**, and it is the part that belongs to the
code rather than to the data. §36 now asserts three things:

| Assertion | What it pins |
|---|---|
| the purge drops both key spellings | the peer bug that shipped is the thing being guarded |
| the thumbnail size is registered uncropped | `300x0`, so grids never hard-crop |
| the size filter is still attached | the size above is not correct by accident |

**Why the middle one is the load-bearing claim, measured two ways.** The filter runs
inside `wc_get_image_size()` **before** `wp_cache_set`, so a cache *miss* stores the
correct uncropped value on its own — the stale entry only predates the filter's own
commit. Plant a stale `{width:300,height:300,crop:1}` and, with the purge removed,
`add_image_sizes()` registers **300x300 crop=true**; with it, **300x0 crop=false**.
That is the entire reason the purge is load-bearing rather than decorative.

**Both new assertions were proven red, not assumed.** Reverting the block to the
committed version (which drops only the prefixed key) flips the purge assertion.
Planting the stale entry on top of that flips the registered-size assertion too — two
FAILs, 345/2. Restoring the file returned it to **347/0** — the figure this run
measured — and the stale entry self-healed on the next request, which is the purge
working as written. (The suite now stands at 351/0 after §23, §34 and §37 grew by
four assertions; the 345/2 and 347/0 figures above are left as the run recorded them.)

### This is a deploy gap on production for a different reason than it looks

`CONTINUATION.md` calls the uncommitted `+10` the entire deploy gap. True, and worth
shipping — but **production has no persistent object cache**
(`wp_using_ext_object_cache()` is `false` there, verified by probe), so the stale
entry this purge repairs **cannot survive a request** on the live host, and production
already registers `300x0` uncropped and renders uncropped grids. Verified on
`lylyrose.ir`: `size-thumbnail` = `{"width":300,"height":"","crop":0}`,
`woocommerce_thumbnail` registered `{"width":300,"height":0,"crop":false}`.

So the deploy is a **latent-fault fix, not a live bug fix**: it removes a footgun that
only bites a host with a persistent object cache (local dev, and any host where Redis
gets enabled later). Nothing on the live site is broken today by its absence. That
distinction was missing from the "commit it or discard it" framing and should travel
with the commit.

**Shipped 2026-10-08** as `911fa1ad`, uploaded via `deploy-targeted.py`, and
`diff-host.py` now reads **57 identical / 0 differing / 0 absent**. Post-deploy checks:
`/`, `/shop/` and `/product-category/perfume/` all 200, three palettes still emitted,
and the `300×300` in `/shop/` HTML traced back to a 600×600 original — a correct
downscale, not a crop.

---

## 0d. Night palette + price decimals session — shipped to production (2026-10-06)

Three visitor-reported faults, all fixed, all **on production**. Suite 335/0.

Theme `Version:` is now **1.24.0**, and that is what production serves.

| File | Change |
|---|---|
| `lylyrose-core/includes/class-palette.php` | `body-text` and `on-ink` added to `CSS_PROP`; night defaults gained `body-text`, a dark `white` surface, and the three `on-*` label colours |
| `lylyrose/style.css` | `:root` gained `--dk-on-accent` / `--dk-on-gold` / `--dk-on-ink`; 53 `background:#fff` → `var(--dk-white)`; 35 `color:#fff` on accent fills → the `on-*` tokens; 15 translucent footer whites → `color-mix(in srgb, var(--dk-on-accent) N%, transparent)`; `.dk-brandcell` literal → `var(--dk-muted)`. `Version:` → **1.24.0** |
| `lylyrose/assets/css/flash-sales.css` | 4 surfaces + accents to tokens |
| `lylyrose/assets/css/perfume-finder.css` | 4 surfaces to tokens, **and** the never-defined `--dk-rose` / `--dk-rose-dark` replaced with `--dk-red` / `--dk-red-dark` |
| `lylyrose/functions.php` | `lylyrose_price_args()` on `wc_price_args` sets `decimals: 0` |

### Three things that will bite the next person

**`--dk-white` is a surface token, not a constant.** 60 rules read it, so on a dark
page it must be a lighter dark (`#1b1f28` in night) or every card renders as a white
slab on `#12151c`. Do not "tidy" it back to `#ffffff`.

**`color-mix()` amounts need percentages.** `color-mix(in srgb, var(--dk-on-accent) .88, transparent)`
is invalid — `.88` silently drops the declaration, the rule falls back to the
inherited colour, and the footer text lands at **1.86:1**. It must be `88%`. This
happened once during this session and the audit caught it; a green suite did not.

**`color-mix()` and gradients are invisible to the contrast audit.** `getComputedStyle
().backgroundColor` reports `rgba(0,0,0,0)` for both, so the audit compares those
elements against the page instead of the fill they sit on. The audit now walks to the
nearest ancestor with a real fill, which fixed the footer false positives but **cannot**
see a `linear-gradient` background — `.dk-offers` and the hero photos still report
their children's contrast against the wrong backdrop. The 16 remaining rows in
`docker/night-audit.py` output are all of this kind and are **known good**, not
outstanding work.

### Why `wc_price_args` and not `wc_get_price_decimals`

Prices are whole تومان, so `.۰۰` is noise. But `wc_get_price_decimals()` also feeds
tax, shipping and refund arithmetic — and via `wc-deprecated-functions.php` it builds
a SQL `DECIMAL(10,n)` column type, where the precision is real. Filtering
`wc_price_args` changes the *display* string only.

`'woocommerce_price_trim_zeros'` is **not** the same fix: it strips trailing zeros, so
a price genuinely landing on `.5` would still show one digit.

### Measured, not assumed

`docker/night-audit.py` reports computed contrast, because night is applied by
rewriting `:root` in JS — there is no night block in the stylesheet to grep for.
Night went **140 → 16** rows under 4.5:1; the 16 are the false positives above.
Reverting one of the `color-mix` lines took it back to 43, so the audit does detect
its own regressions.

The palette values are also worth asserting directly, since the CSS proves nothing
about the preset: **night** `white=#1b1f28` `body=#d5d9e2` `on-accent=#0d1016`
`red=#789df9` `bg=#12151c`; **ivory** keeps `white=#ffffff`. All three confirmed on
the live host, and live night audit matches local exactly.

---

## 0b. Homepage redesign session — what it touched, and what is still loose (2026-10-06)

> **Header restored 2026-10-06.** The finder session's edit above had consumed this
> section's heading; its body text was untouched.

**Uncommitted work on `master`, present when this session started.** Two files, and
they belong together: the card's content is wrapped in `.asc-finder__stack`, and the
CSS splits that wrapper into a two-column grid at `>= 900px` with the buy button
beside it at the bottom.

| File | Change |
|---|---|
| `lylyrose-core/includes/class-perfume-finder.php` | wraps head/coverage/buy/rating/meta/notes/factors in `<div class="asc-finder__stack">`; `.asc-finder__actions` stays a **sibling** inside `.asc-finder__body` |
| `lylyrose/assets/css/perfume-finder.css` | new `.asc-finder__stack` rule; the `>= 900px` block now makes `.asc-finder__body` `flex-direction: row` + `align-items: flex-end`, and lays the stack out as `repeat(2, minmax(0, 1fr))` with the factor list on `auto-fit, minmax(190px, 1fr)` |

Measured with CDP at a real viewport (`docker/measure-finder.py`), **not** from the
CSS: 1440 → `row`/`flex-end`, stack 938px + button 132px, factors 2×214px, no
horizontal overflow. 900 → still `row`, factors correctly collapse to one 230px
track rather than clipping. 768 and 420 → `column`, stack and body both full width,
no overflow. **No width regresses.**

### The two things that will bite the next person

**The button must stay outside the stack.** Inside it, the flex row cannot put it
beside anything and it renders under the factor list — the exact wide empty band
the redesign removed. This is not visible from the CSS alone, so
`run-tests.sh` §34.2b now asserts the *shape* (the stack opens, then `.asc-finder__actions`
appears after its closing `</div>`), not just that the class exists. Verified the
assertion goes red: moving the actions block back inside the stack flips it to
`bad`. See [[assert-fails-on-broken-build]].

**`grep -o 'dk-top-banner-grid.\{0,4000\}'` in `run-tests.sh:213` hung forever and
is now fixed.** `grep` here is ugrep, and that bounded repeat over the ~100KB
one-line homepage spins at 100% CPU — and a hung suite prints no `FAIL`, so
`grep -c FAIL` reads **0** and the run looks green. Replaced with a `python3`
`find` + `html[i:i+20000]` slice: same coverage, no backtracking. Recorded in
[[run-tests-grep-hangs-on-homepage]]; **any assertion in this suite with a payload
over 64KB is still suspect until rewritten.**

---

Landed: `dk-top-banner-grid` (5 cells), brand-coloured footer, `Version:` → 1.17.0.
`dk-story-row` was **restored** — an earlier pass of this same session removed it by
mistake and the owner corrected it — and `dk-service-row` is **parked**, not deleted:
its markup sits inside an HTML comment in `front-page.php` and its CSS is untouched,
so uncommenting brings it back.

**The one thing to know before staging:** all three files this session edited also
contain **another session's uncommitted work**, and the working tree cannot separate
them by file. Whoever commits them will sweep in the peer work unless it is split out
by hand:

| File | This session's change | The other session's work in the same file |
|---|---|---|
| `front-page.php` | top-banner markup, story row, parked service row | hero carousel rebuilt to `$dk_hero_slides` + generated dots |
| `style.css` | story CSS restored, footer recolour, top-banner CSS, `Version:` | **`Version:` 1.17.0 → 1.18.0** bumped later by another session (2026-10-06). This column is no longer clean. |
| `docker/run-tests.sh` | story/service/top-banner/footer assertions | weight-editor and price-tier sections; the ASC_ class count 19 → 22 |

`class-perfume-finder.php` is **not** this session's work and was left uncommitted —
it is the tier/weights counterpart to the peer's `run-tests.sh` sections.
*(Resolved: committed in `007a36e9`, and `lylyrose-core/` is clean in `git status`.)*

Suite after all of it: **330 passed, 0 failed**.

**New trap, worth knowing before you trust a green suite here:** `run-tests.sh` sets
`set -o pipefail`, so `printf '%s' "$BIG" | grep -q PATTERN` reports **141** whenever
grep finds a match early and `printf` takes SIGPIPE — the assertion fails on a correct
build. `style.css` is 91KB, past the 64KB pipe buffer, so it hit this. Use a
herestring (`grep -q PATTERN <<< "$VAR"`) for large payloads. Any assertion in this
suite with a payload over 64KB is suspect until rewritten.

---

## 0c. Banner-photo session — `lylyrose_promo_image()` and the hero nav (2026-10-06)

Added `lylyrose_promo_image()` to `lylyrose/functions.php` (just above
`lylyrose_mega_cats_menu`). Each promotional cell now wears a photo pulled from
the **same query its own link already sorts by**, so "پرفروش‌ترین‌ها" is not
captioned over a random bottle. Returns a root-relative path, cached 12 hours in
a transient — same reasoning as `lylyrose_term_image()`: the site answers on more
than one host, so an absolute URL cached from whichever request won the race
would pin every later visitor's photos to that host.

| File | Change |
|---|---|
| `lylyrose/functions.php` | new `lylyrose_promo_image()` |
| `lylyrose/front-page.php` | `'q'` on all 4 static banners + 4 hero slides; `'photo'` on the 2 finder cells; `--dk-banner-cols` resolved in PHP (`< 6` → one row, `>= 6` → `ceil(n/2)`); `--dk-banner-photo` / `--dk-hero-photo` emitted inline |
| `lylyrose/style.css` | photo `::before` + scrim `::after` on both; `isolation: isolate` on `.dk-hero-slide`; `.dk-hero-nav` flex-centred so the **chevron inside** each button is centred (the button pair stays on the hero's left/right edges) |

**`isolation: isolate` on `.dk-hero-slide` is load-bearing, not decoration.**
Without it the slides have no stacking context, the `z-index: -1` photo layers
paint behind the hero's own gradient, and the photos are invisible. They must
also stay at `-1`, not `0`: a positioned pseudo-element at `z-index: 0` paints
*after* non-positioned block children in the same context and would cover the
copy. `h1HitAtCentre: "copy"` is the assertion that catches this — it hit-tests
the middle of the h1 and reports what actually painted there.

**A wrong query is a silent content bug.** Slide 1 and slide 5 both rendered
`dior-300x300.webp` because both reached for "any product on sale". Reading
`class-flash-sales.php` showed the offers page defaults to `orderby => date DESC`,
so slide 5's query was aligned to that. Compare rendered images across cells, not
just "is a photo set".

Measured, not assumed: grid 5 cols × 1 row at 1440 (`justify-content: center`,
all cells 249×237 at an identical `y`), hero nav 40×40 flex with an 11×11 chevron
and both margins `0px`. Suite **330 passed, 0 failed** — unchanged.

**≤1024px collapses to 2 columns and 3 rows, and that is pre-existing, not
regression.** `style.css:2160` declares `.dk-banner-grid { grid-template-columns:
repeat(2, 1fr); }` — a literal, in a later media query, deliberately left alone.
It is the only mobile rule for this grid, so 5 cells at ≤1024px give rows of
2/2/1 and the `>= 6 → two rows` promise does not hold there. Honoring the rule at
every width means `repeat(var(--dk-banner-cols), minmax(0, 1fr))` in the
media query too, which would put 5 phone-sized cells across at 420px. **Left as
a deliberate simplification.** Change it when a mobile grid lands with a design
for it. Photos, scrims, hero photo and centred chevrons were re-measured at
1024/900/768/420 and survive every width with no horizontal overflow.

---

## 0. READ FIRST — status changed after sections 1–5 were written (2026-10-05, later)

Both original bugs are **fixed and committed** (`2a9c2ee2`), and the navy theme
has since been **deleted**, which changes what is left to do. Sections 1, 2 and
5 still describe the world as it was before those two events; each one carries an
inline correction saying what superseded it, so read the section, then its note.

**The fixes are in `lylyrose`, and `lylyrose` is the only theme.** Verified
against the worktree and history, not from the earlier note:

| Claim | State now |
|---|---|
| Both bugs fixed in `lylyrose` | ✅ `2a9c2ee2`, confirmed in history — 3 files, 14 insertions |
| Navy still on disk | ❌ **gone.** `wordpress/wp-content/themes/lylyrose-navy/` does not exist |
| Navy deletion committed | ❌ **not yet.** All 31 files are staged as deleted; no commit records it |
| Both bugs live in navy | **moot** — there is no navy to fix |

The fixes are therefore *not* blocked on a theme decision any more — option (a)
in §1 is moot (navy no longer exists to switch back to), and option (b) has
already been executed on disk, though not committed. **What is left is narrower
than §6 implies: commit the navy deletion, and decide whether production, which
still runs navy, needs the two fixes mirrored there.**

`LRPROBE` was still in `lylyrose/functions.php:879` when this was written, and
`wp-content` was still `nobody`-owned. **Both are now false** — see the correction
at the top of §5 and the resolved row in §3.

> **Superseded 2026-10-06, again.** The navy deletion *is* committed: `50a8c651`
> "Merge lylyrose-navy into lylyrose: one theme, palette as data", preceded by
> `732a2b46` preserving the full-width finder redesign before the removal. So
> §0's §6.1 blockers ("navy's enqueue + cache TTL", "navy's style.css Version",
> "navy's integration tests") describe work that **no longer applies to any
> checkout** — navy is not just gone from disk, it is merged and gone from
> history's tip. §0's last row also still says production runs navy; that is now
> false too, see [[production-now-runs-lylyrose]].
>
> **This section is kept only as a record of the sequence.** Do not use it to
> decide what is left. Read §6 and verify on the live host.

## 1. The blocker: every WooCommerce archive returns 500

> **RESOLVED.** The navy theme was merged and deleted (`50a8c651`), so the
> lylyrose-navy mismatch below cannot recur. Both options in the table are
> moot. Kept as the record of the diagnosis.

`/shop/`, `/product-category/perfume/`, `/product-category/gift-cards/` and `/`
all render the page header and then die with WordPress's generic
«یک خطای مهم در این وب سایت رخ داده است» page. Zero product cards render.

**Root cause** (established by the "New product rollout" session, confirmed
against the served bytes):

```
Uncaught Error: Call to undefined function lylyrosenavy_discount_percent()
  in wp-content/themes/lylyrose-navy/woocommerce/content-product.php:14
```

The `stylesheet` option says `lylyrose`, so `lylyrose/functions.php` loads and
defines the `lylyrose_*` functions. But WooCommerce is resolving
`content-product.php` out of the **navy** directory, which calls the
`lylyrosenavy_*` prefix that nothing defines. It is a half-finished theme switch,
not a code defect in either theme.

Cause of the half-switch: the peer session set the active theme to `lylyrose`
locally about two hours ago to test the palette switcher, not yet knowing navy
was live on production.

### Why it looks like several different bugs

- **Header and filters render, then the page dies.** PHP flushed output before
  reaching the loop, so the failure looks like a rendering bug rather than a
  fatal.
- **Every queued footer script disappears.** The request dies before
  `wp_footer()`, so `hero.js`, `palette.js` and `main.js` are all missing. This
  is what made the palette JS look "stripped" — it is a symptom, not a cause.
  Expect the scripts to come back once the 500 is fixed; do not chase
  `window.lylyrosePalette` separately until then.
- **A mu-plugin shutdown tracer writes nothing.** The fatal is after
  `template_redirect`, so WordPress's shutdown handler never runs.

### Two ways out — needs the user's decision

| | Fix | Cost | Risk |
|---|---|---|---|
| (a) | Set the active theme back to `lylyrose-navy` | one option row, 500 clears immediately | Reverts the local palette work; the mismatch can recur |
| (b) | Delete `lylyrose-navy` | makes the mismatch impossible | Destroys files git does not fully track, and production runs navy |

Both are held until the user decides. (b) is the merge that was originally asked
for; (a) is the one-line un-break.

---

## 2. What is committed

`2a9c2ee2` — three files, 14 insertions. Both user-reported bugs:

- **Product photos link to their product** in every card
  (`lylyrose/woocommerce/content-product.php:24`). The photo was the last
  unlinked element; title and add-to-cart already pointed at the permalink.
- **The offers rail shows product names** (`lylyrose/front-page.php:225`),
  two-line clamped, with `style.css` bumped to the cache-busting `Version:`.

Verified in the browser: all 10 rail cards carry a name, and the price row sits
at an identical y across all 10 despite six of the titles clamping — uneven title
lengths do not misalign the grid.

**These fixes are in `lylyrose`, which is the only theme and the one production
serves** (see [[production-now-runs-lylyrose]]).

**Independently re-verified 2026-10-05 (later session), and `lylyrose` now has
every product image linked — not just the one that was broken.** Enumerating
every `get_image()` call site rather than reading one template:

| File | Image is inside a permalink anchor |
|---|---|
| `lylyrose/woocommerce/content-product.php:24` | ✅ `.dk-product-photo` |
| `lylyrose/woocommerce/single-product.php:289,295,318` | ✅ all three (FBT row + FBT cards) |
| `lylyrose/woocommerce/cart/cart.php:44` | ✅ conditional on `is_visible()`, correct |
| `lylyrose/page-incredible-offers.php:69` | ✅ `.dk-offer-product` wraps image *and* `<h3>` |
| `lylyrose/front-page.php:222` | ✅ whole `.dk-offer-card` is the anchor |
| `lylyrose-core/.../class-perfume-finder.php:619` | ✅ `.asc-finder__media` |

So "every card on every page" holds, and `dk-offer-name` renders at
`front-page.php:225`. No remaining gap in the surviving theme.

**One correction to §1's framing, for anyone who goes looking.** There is no
mega menu in this codebase. `.dk-mega` CSS exists in three themes and **no PHP
ever emits it** — the nav is a flat `dk-catnav` list plus a mobile drawer. The
owner reported the unnamed row as being "in main menu"; the row that actually
lacked a name was the front-page offers rail. If a mega menu is genuinely
wanted, that is a build, not a missing label.

---

## 3. Do not commit these

| Path | Why |
|---|---|
| ~~`lylyrose/functions.php`~~ | **RESOLVED — no longer applies.** The `LRPROBE` shutdown block was removed in `8932318b`. Verified 2026-10-06: `grep -rn LRPROBE --include=*.php` matches nothing outside `docker/reveal-fatal.php`'s comment, and `git log -S LRPROBE` names that commit as the only one. It is gone from HEAD *and* from the working tree, so it was never lost by a later patch. |
| ~~`lylyrose-core/includes/class-finder-tiers.php`~~, ~~`class-finder-weights.php`~~ | **RESOLVED** — tracked in `8f175393`; `git status` is clean for `lylyrose-core/`. |
| ~~`lylyrose-navy/` (31 files)~~ | **RESOLVED** — deleted in `50a8c651`. |
| `docker/run-tier-tests.sh` | **Not ours.** Owned by a peer session; still untracked. Its header says "ONLY section 35" but it now runs 35–47 (83 assertions) — stale, but leave it to its owner. |

Stray scratch `header.php.fixed` removed 2026-10-10 (root-owned; deleted via
`docker exec -u root lylyrose-wp`).

---

## 4. Session ownership

| Session | Owns |
|---|---|
| needs pages seed + SKU keying (2026-10-10) | **done** — `docker/seed-needs-data.php` keyed by SKU; live tags written |
| brand palette + mega menu (2026-10-10) | **done** — `style.css` `:root`, `class-palette.php`, `lylyrose_mega_menu()`, `header.php` catnav, `docker/seed-finder-data.php` |
| New product rollout | idle — `class-finder-tiers.php`, `class-finder-weights.php`, `class-perfume-finder.php`, and the perfume-finder CSS layout are committed (`8f175393`, `007a36e9`) |
| عطرت رو انتخاب کن در صغحه | idle |
| https://lylyrose.local/perfume-finder/ ... | idle |

**Two rules that a shared worktree makes necessary, both learned the hard way:**

- **Read `git diff` before you take anything.** A peer edits the same files
  live. Do not `git checkout` or `git restore` a path on the strength of a memory
  of its last state — that is how work gets thrown away. If a peer's change is
  entangled with yours, `git add -p` only your lines, or leave the file
  uncommitted and say so in the session entry.
- **The `index` is shared, so a staged-but-uncommitted change outlives the
  session that staged it.** Check `git diff --cached` *before* staging as well as
  before committing. `BACKUP_CHECKLIST.md` sat staged for two sessions that way.

The `docker/_probe*.php` and root `.txt` scratch files were removed 2026-10-10. The
untracked `docker/check-*.py` helpers remain **scratch, not deliverables**. Do not commit them.

---

## 5. Environment blockers

**⚠ CORRECTION 2026-10-06 — both blockers below are gone.** Do not plan around
them. Verified directly, not inferred:

- `wp-content` is **`www-data`-owned**, not `nobody`:
  `stat -c '%U:%G' wordpress/wp-content` → `www-data:www-data`.
  `functions.php` and `style.css` are `www-data`-owned too.
- **The docker socket works from the shell.**
  `docker exec -u www-data lylyrose-wp php -r '…'` returns normally.

The consequence is the opposite of what §5 warns about: those files are *not*
writable by the `kourosh` session user, so the Edit/Write tools fail with
`EACCES`. **Every write goes through the container as `www-data`:**

```bash
docker exec -u www-data lylyrose-wp php < /tmp/patch.php
```

Write the patch as a heredoc'd file with a `rep()` helper that asserts
`substr_count($haystack, $needle) === 1` and aborts the whole patch when an anchor
is not unique. Inline `php -r` one-liners are not survivable here — nested
quoting plus emoji produce `Parse error: Unclosed '('`.

The original text follows for history.

**`wp-content` was chowned to `nobody` mid-session** (2026-10-04 ~22:53). This
session can no longer write anywhere under
`wordpress/wp-content/`, which means it cannot place a diagnostic probe and
cannot clean up after itself. **Still true on 2026-10-05, and it now blocks the
surviving theme too:** 42 of the files in `wordpress/wp-content/themes/lylyrose`
are `nobody`-owned, including `functions.php` and `woocommerce/content-product.php`
— the two files a session would need to touch to clean up after the fixes. The
navy theme's 31 files were never writable either; they are now deleted instead.
Undo with:

```bash
sudo chown -R kourosh:$(id -gn) /media/kourosh/DEVNVME/projects/lylyrose/wordpress/wp-content
```

*(Superseded: navy has since been deleted, and `wp-content` ownership is
`www-data` — see the 2026-10-06 correction at the top of this section.)*

**Docker socket: superseded.** The 2026-10-06 correction above is current — `docker`
and `docker exec` work from the shell. The browser preview also works.

**Write access to `wp-content` varies by session.** Check with `test -w <file>` before
editing. If it fails, write through `docker exec -u root` or `-u www-data`, as in the
correction above.

---

## 6. Roadmap

> **2026-10-12 update (accounting).** Accounting REST API (`ASC_Accounting`, `/wp-json/aroma/v1/`) is in `lylyrose-core` at `f85af88f`/`adaf57e6`, mirrored from aroma_store `493c324c`. Covers sales, summary, invoices, unit cost, and expenses with operating profit. Deployed to lylyrose.ir 2026-10-13 (`diff-host.py` 67/0/0 on 2026-10-14). Digikala adapter is blocked until seller file formats or an API are available. Full notes in `docs/DEVELOPMENT_LOG.md`.

> **2026-10-12 update.** Hero empty strip fixed in `lylyrose/style.css` with `min-height: 300px` on `.dk-hero-slide` (the padding edits were reverted; they only masked it). Theme `Version:` → **1.27.4**. Uploaded and verified on lylyrose.ir. Render checked at 1440px and 375px via `?nocache=` URLs. The plain homepage was a stale LiteSpeed hit until a `litespeed_purge_all` on 2026-10-12; it now serves `ver=1.27.4`.
>
> **2026-10-11 update.** Finder result panels (`.asc-finder__meta`, `.asc-finder__notes`) had a hardcoded `#fdfcfa` surface, so night palette rendered light text on a light panel. Fixed to `var(--dk-white)` in `perfume-finder.css` (3 lines), committed 2026-10-11, and **deployed to lylyrose.ir 2026-10-11** with theme `Version:` 1.27.1. Verified on the served asset. Not checked: ivory palette, narrow viewports.
>
> **Decision 2026-10-11: no padding for the 29 narrow portrait photos.** The product card media box is already fixed at 170px with `object-fit: contain`, so narrow originals render small but sharp. Real fix is reshooting or sourcing wider images, which is a content task. Revisit only if the owner wants a uniform box as a design call.
>
> **Corrected 2026-10-19.** The earlier "WP Super Cache is deactivated; LiteSpeed is the sole page
> cache" line was **wrong**. A read-only FTP read 2026-10-19 shows WP Super Cache still wired up
> (`WP_CACHE` true, `advanced-cache.php` present, `$cache_enabled`/`$super_cache_enabled` true,
> its footer in the served HTML) *and* LiteSpeed active — two page caches at once. And
> `wp-content/cache/supercache-old-20261011/` **does not exist**: a sweep of 504 docroot dirs
> found no `supercache-old*`, so **the removal step is moot, nothing to delete**. Clean-check on
> the 5 routes passed (all 200; LiteSpeed hit on `/` and `/shop/`). Full detail in
> CONTINUATION.md / PRODUCTION_UPDATE_RUNBOOK.md. Open items: per-host
> credentials on `lylyrose.ir` (ZarinPal merchant, PWSMS gateway, WP Mail SMTP, Wordfence). Scratch files
> (`header.php.fixed`, `docker/_probe*`, root `.txt`) were removed 2026-10-10. Done 2026-10-10: the 1.27.0 teal palette was checked on the live
> homepage by eye, the new products were confirmed, the 51 St. Ives needs tags were
> reviewed and marked done by the owner, and local and live match: 60 first-party files
> identical, 165 products on both.

Steps 1–5 are history. Navy is gone from production and from git; nothing is pending
there.

1. ~~**Commit the navy deletion.**~~ **MOOT.** Navy was never tracked in git; merge
   `50a8c651` removed it. Production serves `lylyrose` (checked 2026-10-10).
2. ~~**Restore ownership** ... then delete the `LRPROBE` block.~~ **DONE**
   (`LRPROBE` removed in `8932318b`; ownership already restored to `www-data`).
   No `chown` needed — see the correction at the top of §5.
3. ~~**Production still runs navy.**~~ **FALSE NOW.** Live serves `lylyrose`.
4. **Re-check the palette JS only after the 500 is gone**, then confirm with a
   fresh fetch rather than a cached body.
5. ~~**Re-verify the offers rail on navy.**~~ Offers rail is on `lylyrose`, the theme
   production runs.

Old step 4 from the previous list — *mirror both fixes into navy* — is dropped.
There is no navy on disk to mirror into.

Standing rules for anyone working here:

- `git diff --cached` before staging — another session's rules will land in your
  commit otherwise.
- Read served HTML before calling something stripped or unenqueued. Two separate
  "missing scripts" turned out to be one 500, and one "nav renders
  alphabetically" was a bad CLI probe.
- Never `rm -rf wp-content/cache`; from a root shell it returns root-owned and
  four assertions fail for no real reason.

---

## 7. Dead ends, so nobody repeats them (most are superseded; read §0i before relying on the mu-plugins entry)

> **2026-10-17:** §7's mu-plugins claim is outdated — mu-plugins load fine in the container now.
> `digikala-product-link.php` in mu-plugins is the proof. All §7 traps below are historical;
> treat them as "was true, may not be now." §0i and the live state are authoritative.

- **A mu-plugin probe writes nothing.** `mu-plugins` is not loaded by this
  container's config; a file placed there is silently ignored.
- **Editing `docker/uploads.ini` needs a container restart to take effect** — and
  it is mounted into every request, so a malformed edit fatals the whole site.
  An edit there that drops the trailing newline took the front page down and
  invalidated a bisect already in progress.
- **Bisect results collected while the site was globally broken are void.** With
  every page 500ing, "the fatal survives this mutation" proves nothing. Re-verify
  the baseline before trusting a negative.