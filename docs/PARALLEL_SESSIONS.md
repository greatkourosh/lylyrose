# Parallel sessions — ownership, blockers, roadmap

Written 2026-10-05 by the session fixing product-card links and the offers-rail
name. Several Claude sessions share this one working tree. This file is the
handover surface: who owns what, what is blocked, and what must not be committed
until a decision lands.

Keep it current. If you change something listed here, change it here in the same
pass — a stale version of this file is worse than none.

---

## 0b. Homepage redesign session — what it touched, and what is still loose (2026-10-06)

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
| `style.css` | story CSS restored, footer recolour, top-banner CSS, `Version:` | — (clean; all hunks are this session's) |
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

`LRPROBE` is still in `lylyrose/functions.php:879` and `wp-content` is still
`nobody`-owned, so §5 stands unchanged: 42 files in the `lylyrose` theme are
not writable by this session.

---

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
| `lylyrose/functions.php` | Carries my leftover `LRPROBE` shutdown block. Inert — it echoes an HTML comment only on a fatal — but it is debugging scaffolding and must not ship. I lost write access before I could remove it (see §5). **Confirmed still present at line 879 on 2026-10-05.** |
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
2. **Restore ownership** with the `chown` in §5, then delete the `LRPROBE`
   block from `lylyrose/functions.php:879`. It is inert (an HTML comment on a
   fatal) but it is debugging scaffolding and must not ship.
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