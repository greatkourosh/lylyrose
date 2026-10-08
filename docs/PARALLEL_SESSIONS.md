# Parallel sessions — ownership, blockers, roadmap

Written 2026-10-05 by the session fixing product-card links and the offers-rail
name. Several Claude sessions share this one working tree. This file is the
handover surface: who owns what, what is blocked, and what must not be committed
until a decision lands.

Keep it current. If you change something listed here, change it here in the same
pass — a stale version of this file is worse than none.

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
reads `--dk-ink`". The mutation was reverted and the suite returned to 347/0. See
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
FAILs, 345/2. Restoring the file returned it to **347/0**, and the stale entry
self-healed on the next request, which is the purge working as written.

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

**These fixes are in `lylyrose` only.** If production runs navy, they are not on
the live site until navy is merged or the change is mirrored.

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
| `lylyrose-core/includes/class-finder-tiers.php` | Untracked, belongs to the peer session |
| `lylyrose-core/includes/class-finder-weights.php` | Untracked, belongs to the peer session |
| `lylyrose-core/lylyrose-core.php`, `class-perfume-finder.php` | Peer's in-flight work |
| **`lylyrose-navy/` (31 files)** | **Staged as deleted, uncommitted, unowned.** Do not stage or unstage this casually — see §4. It is what unblocks the 500, but it also deletes the theme production runs, so it is the user's decision. |

Nothing of mine is staged. `lylyrose-core.php` is byte-identical to HEAD.

---

## 4. Session ownership

| Session | Owns |
|---|---|
| this one | **done** — product-card photo links, offers-rail product name, `lylyrose` theme markup. All shipped in `2a9c2ee2`; nothing left staged |
| New product rollout | `class-palette.php`, `class-finder-tiers.php`, `class-finder-weights.php`, `docker/run-tests.sh`, the perfume-finder CSS layout |
| عطرت رو انتخاب کن در صغحه | idle |
| https://lylyrose.local/perfume-finder/ ... | idle |

The two finder classes are untracked, so they are invisible to `git status`
review by anyone who has not seen them.

**The navy deletion has no owner.** It was not staged by any session listed
here. Treat it as an orphaned change until someone claims it — it is 31 files
and it is what unblocks the 500, so nobody should "clean up" the index and
discover it was load-bearing.

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

The same ownership also blocks the `lylyrose-navy` parity fixes: all 31 navy
files are `nobody`-owned, so navy still carries both original bugs.
*(Superseded: navy has since been deleted. The ownership problem itself
outlived it — see the paragraph above.)*

**No docker socket from the shell.** `docker` is denied in the bash sandbox, so
container-side inspection (`docker exec`, `docker logs`) is unavailable here. The
browser preview runs outside the sandbox and does work.

---

## 6. Roadmap

Immediate, in order. Steps 1–2 are new and supersede the old plan; 3–5 are the
original sequence, trimmed to what still applies.

1. **Commit the navy deletion, or decide against it.** All 31 navy files are
   staged as deleted and the directory is gone from disk, but nothing records
   it. Until it is committed, `git checkout` resurrects a theme that is half
   switched and is what made the 500 in §1. Committing it makes the 500
   structurally impossible. This is the user's call, not this session's — it
   destroys the theme production runs.
2. ~~**Restore ownership** … then delete the `LRPROBE` block.~~ **DONE**
   (`LRPROBE` removed in `8932318b`; ownership already restored to `www-data`).
   No `chown` needed — see the correction at the top of §5.
3. **Production still runs navy.** `lylyrose`'s fixes reach the live site only
   once navy is gone there too, or the two fixes are mirrored into it. Which of
   those is right depends on step 1 and is a user decision.
4. **Re-check the palette JS only after the 500 is gone**, then confirm with a
   fresh fetch rather than a cached body.
5. **Re-verify the offers rail in the browser after the theme settles.** The
   verification in §2 was done against `lylyrose`; if navy is what ends up
   serving, both fixes need re-verifying there or they were never live.

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

## 7. Dead ends, so nobody repeats them

- **A mu-plugin probe writes nothing.** `mu-plugins` is not loaded by this
  container's config; a file placed there is silently ignored.
- **Editing `docker/uploads.ini` needs a container restart to take effect** — and
  it is mounted into every request, so a malformed edit fatals the whole site.
  An edit there that drops the trailing newline took the front page down and
  invalidated a bisect already in progress.
- **Bisect results collected while the site was globally broken are void.** With
  every page 500ing, "the fatal survives this mutation" proves nothing. Re-verify
  the baseline before trusting a negative.